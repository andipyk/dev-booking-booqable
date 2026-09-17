<?php
/**
 * Caches Client::get_product_groups() — the Product Catalog block's own
 * data source. Confirmed live: product_groups exposes no `collection_id`
 * (flat attribute or relationship) in its response, so unlike
 * Collections_Cache/Products_Cache this can't cache one full list and filter
 * client-side — it caches one transient per (collection, limit)
 * combination instead, which is exactly the same block config a real site
 * would repeat across visitors anyway.
 *
 * Before this class existed, `product-catalog/render.php` called
 * `Client::get_product_groups()` directly — a live, uncached Booqable API
 * call on *every single page view* of any page containing the block
 * (measured: ~800ms added to server render time). See debugging.md.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Product_Groups_Cache {

	const PREFIX = 'booqable_core_product_groups_';
	const TTL    = 15 * MINUTE_IN_SECONDS;

	/**
	 * @param string|null $collection_id
	 * @param int         $limit
	 * @return array|\WP_Error
	 */
	public static function get( $collection_id = null, $limit = 24 ) {
		$key    = self::key( $collection_id, $limit );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$client = new Client();
		$groups = $client->get_product_groups( $collection_id, $limit );

		if ( is_wp_error( $groups ) ) {
			return $groups;
		}

		set_transient( $key, $groups, self::TTL );
		return $groups;
	}

	/**
	 * Deletes every cached (collection, limit) variant at once — the exact
	 * combination in use isn't tracked anywhere, so a direct query against
	 * the options table (matching the standard `_transient_{key}` /
	 * `_transient_timeout_{key}` row naming) is the only way to flush them
	 * all on a webhook event.
	 *
	 * @return void
	 */
	public static function flush() {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param string|null $collection_id
	 * @param int         $limit
	 * @return string
	 */
	protected static function key( $collection_id, $limit ) {
		return self::PREFIX . md5( ( $collection_id ? $collection_id : 'all' ) . ':' . (int) $limit );
	}
}
