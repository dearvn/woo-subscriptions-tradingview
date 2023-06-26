<?php
/**
 * Enqueue class.
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
class Enqueue {

	use Singleton;

	/**
	 * Constructor of Enqueue class.
	 */
	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add JS scripts.
	 */
	public function admin_enqueue_scripts() {
		// Check if we are on the admin page and page=react_real_estate.
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_script( 'woo-subscriptions-tradingview', plugins_url( 'assets/admin/js/script.js', dirname( __FILE__ ) ) );
		wp_localize_script(
			'woo-subscriptions-tradingview',
			'wprs_plugin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ajax-nonce' ),
			)
		);
	}

	/**
	 * Add CSS files.
	 */
	public function enqueue_styles() {
		global $pagenow;

	}
}
