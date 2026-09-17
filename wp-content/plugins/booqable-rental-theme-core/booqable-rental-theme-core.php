<?php
/**
 * Plugin Name: Booqable Rental Theme Core
 * Plugin URI: https://github.com/
 * Description: Companion plugin for the Booqable Rental Theme. Talks directly to the Booqable API (v4) — live categories, a native product catalog block, a browser-local cart, and manual/offline checkout — no Booqable-hosted widget or shortcode plugin required. Survives theme switches — the theme is presentation only, this plugin owns the data and functionality.
 * Version: 0.4.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Staging Co.
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: booqable-rental-core
 *
 * @package Booqable_Rental_Core
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'BOOQABLE_RENTAL_CORE_VERSION', '0.4.0' );
define( 'BOOQABLE_RENTAL_CORE_FILE', __FILE__ );
define( 'BOOQABLE_RENTAL_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOOQABLE_RENTAL_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-client.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-collections-cache.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-products-cache.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-product-groups-cache.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-company-cache.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-money.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-settings.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-webhook.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-blocks.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-availability-rest.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-cart-widget.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-checkout.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-checkout-rest.php';
require_once BOOQABLE_RENTAL_CORE_DIR . 'includes/class-order-expiry.php';

/**
 * Boot the plugin. Every class is self-registering (hooks in its own
 * constructor) so this stays a simple, readable composition root.
 */
function booqable_rental_core_boot() {
	new Booqable_Rental_Core\Settings();
	new Booqable_Rental_Core\Webhook();
	new Booqable_Rental_Core\Blocks();
	new Booqable_Rental_Core\Availability_Rest();
	new Booqable_Rental_Core\Cart_Widget();
	new Booqable_Rental_Core\Checkout_Rest();
	new Booqable_Rental_Core\Order_Expiry();
}
add_action( 'plugins_loaded', 'booqable_rental_core_boot' );

/**
 * Stop the order-expiry cron on deactivation — it's rescheduled
 * automatically (see Order_Expiry::maybe_schedule()) the next time the
 * plugin is active, so nothing else needs to run on activation.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( Booqable_Rental_Core\Order_Expiry::HOOK );
	}
);
