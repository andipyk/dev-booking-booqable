<?php
/**
 * Caches Client::get_products() — the flat product list used to resolve
 * which real, bookable Product id sits behind each Product Group card.
 * Same pattern as Collections_Cache: transient + webhook-triggered flush.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Products_Cache {

	const TRANSIENT_KEY = 'booqable_core_products';
	const TTL           = 15 * MINUTE_IN_SECONDS;

	/**
	 * @return array|\WP_Error
	 */
	public static function get() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$client   = new Client();
		$products = $client->get_products();

		if ( is_wp_error( $products ) ) {
			return $products;
		}

		set_transient( self::TRANSIENT_KEY, $products, self::TTL );
		return $products;
	}

	/**
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
