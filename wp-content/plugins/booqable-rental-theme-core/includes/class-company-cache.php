<?php
/**
 * Caches Client::get_company() — used for the store's real currency so
 * prices never need a manual "set your currency" settings field. Company
 * info changes far less often than catalog data, hence the longer TTL, but
 * it still rides the same webhook-flush safety net as the other caches.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Company_Cache {

	const TRANSIENT_KEY = 'booqable_core_company';
	const TTL           = HOUR_IN_SECONDS;

	/**
	 * @return array|\WP_Error
	 */
	public static function get() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$client  = new Client();
		$company = $client->get_company();

		if ( is_wp_error( $company ) ) {
			return $company;
		}

		set_transient( self::TRANSIENT_KEY, $company, self::TTL );
		return $company;
	}

	/**
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
