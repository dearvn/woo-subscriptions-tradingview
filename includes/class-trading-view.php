<?php
/**
 * Post class.
 *
 * @package WSTDV
 */

namespace WSTDV;

use WSTDV\Traits\Singleton;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add styles of scripts files inside this class.
 */
class TradingView {

	use Singleton;

    /**
     * Token Id
     *
     * @var string
     */
    private $token = '';
    
    /**
     * Session Id
     *
     * @var string
     */
    private $sessionid = '';
    
    private $urls = array(
        "tvcoins" => "https://www.tradingview.com/tvcoins/details/",
        "username_hint" => "https://www.tradingview.com/username_hint/",
        "list_users" => "https://www.tradingview.com/pine_perm/list_users/",
        "modify_access" => "https://www.tradingview.com/pine_perm/modify_user_expiration/",
        "add_access" => "https://www.tradingview.com/pine_perm/add/",
        "remove_access" => "https://www.tradingview.com/pine_perm/remove/",
        "pub_scripts" => "https://www.tradingview.com/pubscripts-get/",
        "pri_scripts" => "https://www.tradingview.com/pine_perm/list_scripts/",
        "pine_facade" => "https://pine-facade.tradingview.com/pine-facade/get/",
    );
    
    /**
     * Returns single instance of the class
     *
     * @return \Subscriptions_For_TradingView_Api
     * @since 1.0.0
     */
    /*public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }*/

    /**
     * Constructor
     *
     * Initialize plugin and registers actions and filters to be used
     *
     * @since  1.0.0
     */
    public function __construct() {
        $options = get_option( 'trading_view_option' );
        $session_id = !empty($options['session_id']) ? $options['session_id'] : '';

        if ($session_id) {
        
            $headers = array(
                'cookie' => 'sessionid='.$session_id
            );
        
            $test = wp_remote_get( $this->urls["tvcoins"], 
                array(
                    'headers' => $headers
                )
            );
            if ( wp_remote_retrieve_response_code( $test ) == 200 ) {
                $this->sessionid = $session_id;
            }
        }

        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'woocommerce_product_trading_view_username_fields' )); 
        // Following code Saves  WooCommerce Product Custom Fields
        add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_product_trading_view_username_fields_save' ));

        //add_action( 'ihc_admin_edit_save_level_after_submit_form', array( $this, 'add_username_to_trading_view' ), 10, 2 );

        add_filter( 'woocommerce_review_order_before_payment', $tdv_plugin_public, 'wps_tdv_add_tradingview_id_field', 10, 1);

        add_filter( 'woocommerce_checkout_update_order_meta', $tdv_plugin_public, 'wps_tdv_save_tradingview_id', 10, 1 );
            
        add_filter( 'woocommerce_after_checkout_validation', $tdv_plugin_public, 'wps_tdv_woocommerce_after_checkout_validation', 10, 2 );
            
    }

    public function woocommerce_product_trading_view_username_fields_save($post_id) {
        // Trading View User Name Product Text Field
        $trading_view_username = $_POST['_trading_view_username'];
        if (!empty($trading_view_username)) {
            update_post_meta($post_id, '_trading_view_username', esc_attr($trading_view_username));
        }

        $trading_view_chart_ids = $_POST['_trading_view_chart_ids'];

        if (!empty($trading_view_chart_ids)) {
            update_post_meta($post_id, '_trading_view_chart_ids', $trading_view_chart_ids);
        }
    }

    public function woocommerce_product_trading_view_username_fields() {
        global $woocommerce, $post;

        $terms = get_the_terms($post, 'product_type');
        $product_type = (!empty($terms)) ? sanitize_title(current($terms)->name) : 'simple';
        if ( 'subscription' != $product_type) {
            return;
        }

        echo '<div class="product_custom_field">';
        // Trading View Username Text Field
        woocommerce_wp_text_input(
            array(
                'id' => '_trading_view_username',
                'placeholder' => 'Trading View Username',
                'label' => __('Trading View Username', 'woocommerce'),
                'desc_tip' => 'true'
            )
        );

        $chart_ids = self::get_private_indicators();

        woocommerce_wp_select(
            array(
                'id' => '_trading_view_chart_ids',
                'name' => '_trading_view_chart_ids[]',
                'label' => __('Chart Ids', 'woocommerce'),
                'desc_tip' => 'true',
                'class' => 'cb-admin-multiselect',
                'options' => $chart_ids,
                'custom_attributes' => array('multiple' => 'multiple')
            )
        );
        
        echo '</div>';
    }

    /**
     * Add username to TradingView.
     * @param int $lid input value.
     * @param array $data input value.
     * @return void
     */
    function add_username_to_trading_view( $lid, $data ) {
        if (empty($data['trading_view_subscription_chart_ids']) || empty($data['trading_view_username'])) {
            return;
        }

        $chart_ids = json_decode($data['trading_view_subscription_chart_ids']);
        if (empty($chart_ids)) {
            return;
        }
        $trading_view_username = $data['trading_view_username'];

        $access_type = !empty($data['access_type']) ? $data['access_type'] : '';
        $expired_date = null;
        if ($access_type != 'unlimited') {
            //'limited'
            if ($access_type == 'limited') {
                $access_limited_time_value = $data['access_limited_time_value'];
                $access_limited_time_type = $data['access_limited_time_type'];
                $susbcription_end = date('Y-m-d');

                if ($access_limited_time_type == 'D') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_limited_time_value day"));
                } elseif ($access_limited_time_type == 'W') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_limited_time_value week"));
                } elseif ($access_limited_time_type == 'M') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_limited_time_value month"));
                } elseif ($access_limited_time_type == 'Y') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_limited_time_value year"));
                }
                $expired_date = (string)date_i18n( 'Y-m-d\T23:59:59.999\Z', strtotime($susbcription_end));
            } elseif($access_type == 'regular_period') {
                //'regular_period'
                $access_regular_time_value = $data['access_regular_time_value'];
                $access_regular_time_type = $data['access_regular_time_type'];
                $susbcription_end = date('Y-m-d');

                if ($access_regular_time_type == 'D') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_regular_time_value day"));
                } elseif ($access_regular_time_type == 'W') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_regular_time_value week"));
                } elseif ($access_regular_time_type == 'M') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_regular_time_value month"));
                } elseif ($access_regular_time_type == 'Y') {
                    $susbcription_end = date('Y-m-d', strtotime($susbcription_end. " + $access_regular_time_value year"));
                }
                $expired_date = date_i18n( 'Y-m-d\T23:59:59.999\Z', strtotime($susbcription_end));
            } elseif($access_type == 'date_interval') {
                //'date_interval'
                $access_interval_end = $data['access_interval_end'];
                $expired_date = date_i18n( 'Y-m-d\T23:59:59.999\Z', strtotime($access_interval_end));
            }
        }

        $is_validate_username = self::validate_username($trading_view_username);
        if (!$is_validate_username) {
            return;
        }
        
        foreach($chart_ids as $pine_id) {
            $access = self::get_access_details($trading_view_username, trim($pine_id));
            $result[] = self::add_access($access, $expired_date);
        }
    }

    
    public function get_private_indicators() {
        $headers = array(
            'cookie' => 'sessionid='.$this->sessionid
        );

        $resp = wp_remote_get( $this->urls["pri_scripts"], 
            array(
                'headers' => $headers
            )
        );
        $datas = [];
        if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
            $data = wp_remote_retrieve_body($resp);
            $datas = (array)json_decode( $data );
        }

        if (empty($datas)) {
            return [];
        }
        
        $headers['origin'] = 'https://www.tradingview.com';
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        $resp = wp_remote_post( $this->urls["pub_scripts"], 
            array(
                'headers' => $headers,
                'body' => [
                    'scriptIdPart' => implode(",", $datas),
                    'show_hidden' => true]
            )
        );
        $indicators = [];
        if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
            $data = wp_remote_retrieve_body($resp);
            $items = (array)json_decode( $data );
            foreach($items as $item) {
                $indicators[$item->scriptIdPart] = $item->scriptName;
            }
        }
        
        return $indicators;
    }
    
    public function get_name_chart($pine_id) {
        $headers = array(
            'cookie' => 'sessionid='.$this->sessionid
        );

        $resp = wp_remote_get( $this->urls["pine_facade"]."{$pine_id}/1?no_4xx=true", 
            array(
                'headers' => $headers
            )
        );
        $datas = [];
        if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
            $data = wp_remote_retrieve_body($resp);
            $datas = (array)json_decode( $data );
        }
        
        return !empty($datas['scriptName']) ? $datas['scriptName'] : '';
    }
    
    /**
     * 
     * @param type $pine_id
     */
    public function get_list_users($pine_id) {
        $payload = array(
            'pine_id' => $pine_id
        );

        $headers = array(
            'origin' => 'https://www.tradingview.com',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Cookie' => 'sessionid='.$this->sessionid
        );
        $resp = wp_remote_post( $this->urls['list_users'].'?limit=30&order_by=-created', 
            array(
                'body'    => $payload,
                'headers' => $headers
            )
        );
        
        $datas = [];
        if ( wp_remote_retrieve_response_code( $resp ) == 200 ) {
            $data = wp_remote_retrieve_body($resp);
            $datas = (array)json_decode( $data );
        }
        
        /*
            * [id] => 44513150
            * [username] => donaldit
            * [userpic] => ''
            * [expiration] => 2022-11-30T23:59:59.999000+00:00
            * [created] => 2022-11-06T03:25:32.982881+00:00
            */
        return $datas;
    }
    
    public function validate_username($username) {
        $resp = wp_remote_get( $this->urls["username_hint"]."?s={$username}");
        if( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) != 200 && wp_remote_retrieve_response_code( $resp ) != 201) {
            return false;
        }
        
        $data = wp_remote_retrieve_body($resp);
        $usersList = (array)json_decode( $data );
        
        $validUser = false;
        foreach( $usersList as $user) {
            $item = (array)$user;
            if (strtolower($item['username']) == strtolower($username)) {
                $validUser = true;
                break;
            }
        }
        return $validUser;
    }
    
    public function get_access_details($username, $pine_id) {
        $payload = array(
            'pine_id' => $pine_id,
            'username' => $username,
        );

        $headers = array(
            'origin' => 'https://www.tradingview.com',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Cookie' => 'sessionid='.$this->sessionid
        );
        $resp = wp_remote_post( $this->urls['list_users'].'?limit=10&order_by=-created', 
            array(
                'body'    => $payload,
                'headers' => $headers
            )
        );
        
        $access_details = array(
            'hasAccess' => false,
            'currentExpiration' => '',
            'noExpiration' => false,
            'pine_id' => $pine_id,
            'username' => $username,
        );
        if( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) != 200 && wp_remote_retrieve_response_code( $resp ) != 201) {
            return $access_details;
        }
        
        $data = wp_remote_retrieve_body($resp);
        $usersList = (array)json_decode( $data );
        
        $users = $usersList['results'];

        if (empty($users)) {
            return $access_details;
        }
        
        $hasAccess = false;
        $noExpiration = false;
        $expiration = '';
        foreach( $users as $user) {
            $item = (array)$user;
            if (strtolower($item['username']) == strtolower($username)) {
                $hasAccess = true;
                if (!empty($item["expiration"])) {
                    $expiration = $item['expiration'];
                } else {
                    $noExpiration = true;
                }
                break;
            }
        }
        $access_details['hasAccess'] = $hasAccess;
        $access_details['noExpiration'] = $noExpiration;
        $access_details['currentExpiration'] = $expiration;
                
        return $access_details;
    }
    
    public function add_access($access_details, $expiration) {
        
        //$noExpiration = $access_details['noExpiration'];
        $access_details['expiration'] = $access_details['currentExpiration'];
        $access_details['status'] = 'Not Applied';
        $payload = array(
            'pine_id' => $access_details['pine_id'],
            'username_recip' => $access_details['username']
        );
        if (!empty($expiration)) {
            $payload['expiration'] = $expiration;
        } else {
            $payload['noExpiration'] = true;
        }
            
        $enpoint_type = $access_details['hasAccess'] ? 'modify_access' : 'add_access';

        $headers= array(
            'origin' => 'https://www.tradingview.com',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'cookie' => 'sessionid='.$this->sessionid
        );
        
        $resp = wp_remote_post( $this->urls[$enpoint_type], 
            array(
                'body'    => $payload,
                'headers' => $headers
            )
        );
        
        if ( wp_remote_retrieve_response_code( $resp ) == 200 || wp_remote_retrieve_response_code( $resp ) == 201 ) {
            return true;
        }
            
        return false;
    }

    public function remove_access($access_details) {
        $payload = array(
            'pine_id' => $access_details['pine_id'],
            'username_recip' => $access_details['username']
        );
        
        $headers = array(
            'origin' => 'https://www.tradingview.com',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'cookie' =>  'sessionid='.$this->sessionid
        );
        
        $resp = wp_remote_post( $this->urls['remove_access'], 
            array(
                'body'    => $payload,
                'headers' => $headers
            )
        );
        if ( wp_remote_retrieve_response_code( $resp ) == 200 || wp_remote_retrieve_response_code( $resp ) == 201 ) {
            return true;
        }
        
        return false;
    }
}
