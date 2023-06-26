<?php
/**
 * Setting class.
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
class Setting {

	use Singleton;

	/**
	 * Constructor of Setting class.
	 */
	private function __construct() {

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );

		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page.
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			__( 'Settings Admin' ),
			__( 'WooCommerce Subscription TradingView Settings' ),
			'manage_options',
			'woo-subscriptions-tradingview-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		// Set class property
		$this->trading_view_option = get_option( 'trading_view_option' );
		?>
		<div class="wrap">
			<h1><?php echo __( 'WooCommerce Subscription TradingView Settings' ); ?></h1>
			<form method="post" action="options.php">
			<?php
				// This prints out all hidden setting fields
				settings_fields( 'real_estate_option_group' );
				do_settings_sections( 'woo-subscriptions-tradingview-setting-admin' );
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings.
	 */
	public function page_init() {
		register_setting(
			'real_estate_option_group', // Option group
			'trading_view_option', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'setting_section_id', // ID
			__( 'TradingView Settings' ), // Title
			array( $this, 'print_section_info' ), // Callback
			'woo-subscriptions-tradingview-setting-admin' // Page
		);

		add_settings_field(
			'session_id',
			__( 'TradingView Session Id' ),
			array( $this, 'session_id_callback' ),
			'woo-subscriptions-tradingview-setting-admin',
			'setting_section_id'
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys.
	 */
	public function sanitize( $input ) {
		$new_input = array();

		if ( isset( $input['session_id'] ) ) {
			$new_input['session_id'] = sanitize_text_field( $input['session_id'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text.
	 */
	public function print_section_info() {
		print __( 'Enter your settings below:' );
	}

	/**
	 * Get the session_id settings option array and print one of its values.
	 */
	public function session_id_callback() {
		printf(
			'<input style="width: 240px;" type="text" id="session_id" name="trading_view_option[session_id]" value="%s"></input>',
			isset( $this->trading_view_option['session_id'] ) ? esc_attr( $this->trading_view_option['session_id'] ) : ''
		);
	}

}
