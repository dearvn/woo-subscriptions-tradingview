<?php
/**
 * Bootstrap class.
 *
 * @package WSTDV
 */

namespace WSTDV;

use WSTDV\Ajax\Woo_Subscriptions_Tradingview_Ajax;
use WSTDV\Traits\Singleton;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load core functionality inside this class.
 */
class Bootstrap {

	use Singleton;

	/**
	 * Constructor of Bootstrap class.
	 */
	private function __construct() {
		// Include custom function files.
		$this->custom_functions();

		if ( is_admin() ) {
			// Include category classes.
			//$this->load_hook_category();

			$this->load_hook_setting();
		}

		// Include category classes.
		//$this->load_hook_taxonomy();

		// Include category classes.
		//$this->load_hook_post();

		// Include asset method.
		$this->load_scripts();

		// Include ajax classes.
		$this->load_ajax_classes();

		// Include trading view classes.
		$this->load_trading_view_classes();
	}

	/**
	 * Load custom functions.
	 */
	private function custom_functions() {
		require_once __DIR__ . '/woo-subscriptions-tradingview-functions.php';
	}

	/**
	 * Load hook category.
	 */
	private function load_hook_category() {
		require_once __DIR__ . '/class-category.php';

		\WSTDV\Category::instance();
	}

	/**
	 * Load hook setting.
	 */
	private function load_hook_setting() {
		require_once __DIR__ . '/class-setting.php';

		\WSTDV\Setting::instance();
	}

	/**
	 * Load hook taxonomy.
	 */
	private function load_hook_taxonomy() {
		require_once __DIR__ . '/class-taxonomy.php';

		\WSTDV\Taxonomy::instance();
	}

	/**
	 * Load hook post.
	 */
	private function load_hook_post() {
		require_once __DIR__ . '/class-post.php';

		\WSTDV\Post::instance();
	}

	/**
	 * Load scripts and styles.
	 */
	private function load_scripts() {
		require_once __DIR__ . '/class-enqueue.php';

		\WSTDV\Enqueue::instance();
	}

	/**
	 * Load ajax classes.
	 */
	private function load_ajax_classes() {
		require_once __DIR__ . '/ajax/class-woo-subscriptions-tradingview-ajax.php';

		Woo_Subscriptions_Tradingview_Ajax::instance();
	}

	/**
	 * Load TradingView api.
	 */
	private function load_trading_view_classes() {
		require_once __DIR__ . '/class-trading-view.php';

		\WSTDV\TradingView::instance();
	}

}
