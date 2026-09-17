<?php
/**
 * Caches Client::get_collections() in a transient so the "Category Grid"
 * block doesn't hit Booqable's API on every page load. The webhook
 * receiver (class-webhook.php) flushes this the moment inventory changes,
 * so the cache TTL below is just a safety net, not the primary freshness
 * mechanism.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Collections_Cache {

	const TRANSIENT_KEY = 'booqable_core_collections';
	const TTL           = 15 * MINUTE_IN_SECONDS;

	/**
	 * @return array|\WP_Error
	 */
	public static function get() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$client      = new Client();
		$collections = $client->get_collections();

		if ( is_wp_error( $collections ) ) {
			return $collections;
		}

		set_transient( self::TRANSIENT_KEY, $collections, self::TTL );
		return $collections;
	}

	/**
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
