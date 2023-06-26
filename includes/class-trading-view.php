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

        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'ws_tradingview_fields' )); 
        // Following code Saves  WooCommerce Product Custom Fields
        add_action( 'woocommerce_process_product_meta', array( $this, 'ws_tradingview_fields_save' ));

        add_filter( 'woocommerce_review_order_before_payment', array( $this, 'ws_tradingview_username_field'), 10, 1);

        add_filter( 'woocommerce_checkout_update_order_meta', array( $this, 'ws_tradingview_save_tradingview_username'), 10, 1 );
            
        add_filter( 'woocommerce_after_checkout_validation', array( $this, 'ws_tradingview_woocommerce_after_checkout_validation'), 10, 2 );
            
        add_filter( 'woocommerce_process_shop_order_meta', array( $this, 'ws_tradingview_admin_save_order_meta'), 10, 1 );

        add_filter( 'manage_users_columns', array( $this, 'ws_tradingview_add_new_user_column' ));

        add_filter( 'manage_users_custom_column', array( $this, 'ws_tradingview_add_new_user_column_content' ), 15, 3 );

    }

    /**
	Remove all possible fields
	**/
	public function ws_tradingview_save_tradingview_username( $order_id ) {
        if ( isset( $_POST['tradingview_username'] ) ) {
            $tradingview_username = sanitize_key( wp_unslash( $_POST['tradingview_username'] ) );
            update_post_meta( $order_id, 'tradingview_username', $tradingview_username );
            
            $order = wc_get_order( $order_id );
            $user_id = $order->get_user_id();
            if (get_user_meta( $user_id, 'tradingview_username', true )) {
                update_user_meta( $user_id, 'tradingview_username', $tradingview_username );
            } else {
                add_user_meta( $user_id, 'tradingview_username', $tradingview_username );
            }
        }
    }

    /**
     * Validate TradingView username.
    */
    public function ws_tradingview_woocommerce_after_checkout_validation( $data, $errors ) { 
        if ( empty( $_POST['tradingview_username'] ) ) {
            $errors->add( 'required-field', __( 'TradingView UserName is a required field.', 'woocommerce' ) );
        } else {
            $tradingview_username = sanitize_key( wp_unslash( $_POST['tradingview_username'] ) );
            
            $is_validate_username = self::validate_username($tradingview_username);
            if (!$is_validate_username) {
                $errors->add( 'required-field', __( 'TradingView UserName is invalid.', 'woocommerce' ) );
            }
        }
    }

    /**
     * Add TradingView username.
     */
    public function ws_tradingview_username_field()
    {
        woocommerce_form_field('tradingview_username', array(
            'type' => 'text',
            'label' => __('TradingView UserName') ,
            'class' => array( 'update_totals_on_change' ),
            'placeholder' => __('TradingView UserName') ,
            'required' => true
        ));
    }


    /**
     * Save meta data of custom fields.
     */
    public function ws_tradingview_fields_save($post_id) {
        // TradingView Chart Product Text Field.
        $trading_view_chart_ids = $_POST['_trading_view_chart_ids'];

        if (!empty($trading_view_chart_ids)) {
            update_post_meta($post_id, '_trading_view_chart_ids', $trading_view_chart_ids);
        }
    }

    /**
     * Add custom fields on product woocommerce.
     */
    public function ws_tradingview_fields() {
        global $woocommerce, $post;

        $terms = get_the_terms($post, 'product_type');
        $product_type = (!empty($terms)) ? sanitize_title(current($terms)->name) : 'simple';
        if ( 'subscription' != $product_type) {
            return;
        }

        echo '<div class="product_custom_field">';
        $chart_ids = self::get_private_indicators();

        woocommerce_wp_select(
            array(
                'id' => '_trading_view_chart_ids',
                'name' => '_trading_view_chart_ids[]',
                'label' => __('Chart Ids', 'woocommerce'),
                'desc_tip' => 'true',
                'class' => 'cb-admin-multiselect',
                'options' => is_array($chart_ids) ? $chart_ids : [],
                'custom_attributes' => array('multiple' => 'multiple')
            )
        );
        
        echo '</div>';
    }



    public function ws_tradingview_add_new_user_column( $columns ) {
        $columns['tradingview_username'] = 'TradingView UserName';
        return $columns;
    }

    public function ws_tradingview_add_new_user_column_content( $content, $column, $user_id ) {

        if ( 'tradingview_username' === $column ) {
            $content = get_the_author_meta( 'tradingview_username', $user_id );
        }

        return $content;
    }

    function ws_tradingview_admin_save_order_meta( $order_id ){
        
        $tradingview_username = get_post_meta( $order_id, 'tradingview_username', true );
        $new_tradingview_username = wc_clean( $_POST[ 'tradingview_username' ] );
        if ($new_tradingview_username && $new_tradingview_username != $tradingview_username) {
            update_post_meta( $order_id, 'tradingview_username', $new_tradingview_username);
            
            $wps_subscription_id = get_post_meta( $order_id, 'wps_subscription_id', true );
            update_post_meta( $wps_subscription_id, 'tradingview_username', $new_tradingview_username);
            
            //wps_tdv_remove_account_tradingview($wps_subscription_id, $tradingview_username);
            
            //wps_tdv_add_account_tradingview($wps_subscription_id, $new_tradingview_username);
            
            $order = wc_get_order( $order_id );
            $user_id = $order->get_user_id();
            if (get_user_meta( $user_id, 'tradingview_username', true )) {
                update_user_meta( $user_id, 'tradingview_username', $new_tradingview_username );
            } else {
                add_user_meta( $user_id, 'tradingview_username', $new_tradingview_username );
            }
        }
    }

    public function ws_tradingview_admin_order_data_after_billing_address($order)
    {
        $tradingview_username = get_post_meta( $order->get_id(), 'tradingview_username', true );
        ?>
          <div class="address">
              <p<?php if( ! $tradingview_username ) { echo ' class="none_set"'; } ?>>
                  <strong>TradingView UserName:</strong>
                  <?php echo $tradingview_username ? esc_html( $tradingview_username ) : '' ?>
              </p>
          </div>
          <div class="edit_address">
              <?php

                  woocommerce_wp_text_input(
                  array(
                      'id'            => 'tradingview_username',
                      'value'         => $tradingview_username,
                      'label'         => __( 'TradingView UserName', 'woocommerce' ),
                      'placeholder'   => '',
                      'desc_tip'      => 'true',
                      'wrapper_class' => 'form-field-wide',
                      'custom_attributes' => array( 'required' => 'required' ),
                  )
              );
              ?>
          </div>
      <?php
    }

    /**
     * Add username to TradingView (removed).
     * @param int $lid input value.
     * @param array $data input value.
     * @return void
     */
    public function add_username_to_trading_view( $lid, $data ) {
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


    /**
     * Get private scripts.
     */
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
