<?php
/**
 * Receives Booqable webhook events and invalidates our local cache so
 * category/inventory changes show up without waiting for the transient
 * to expire.
 *
 * SECURITY NOTE: the exact webhook signing scheme Booqable uses (header
 * name + algorithm) needs confirming against a live webhook_endpoints
 * response once real API credentials are available (tracked as a TODO —
 * see Settings::render_webhook_field()). Until then this endpoint is
 * protected by an unguessable per-site secret in the URL itself, which is
 * the same baseline WordPress core uses for e.g. the REST API's
 * `_wpnonce`-less webhook-style callbacks. Register the printed URL as-is
 * in Booqable's dashboard; regenerate it any time from the settings page.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Webhook {

	const SECRET_OPTION = 'booqable_core_webhook_secret';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * @return string
	 */
	public static function get_secret() {
		$secret = get_option( self::SECRET_OPTION );
		if ( ! $secret ) {
			$secret = wp_generate_password( 32, false );
			update_option( self::SECRET_OPTION, $secret );
		}
		return $secret;
	}

	/**
	 * @return string
	 */
	public static function get_url() {
		return rest_url( 'booqable-core/v1/webhook/' . self::get_secret() );
	}

	public function register_route() {
		register_rest_route(
			'booqable-core/v1',
			'/webhook/(?P<secret>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authorize' ),
				'args'                => array(
					'secret' => array( 'required' => true ),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function authorize( $request ) {
		return hash_equals( self::get_secret(), (string) $request['secret'] );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle( $request ) {
		// Any collection/product-group event invalidates the cache — we
		// don't need to parse the payload to know it's now stale.
		Collections_Cache::flush();
		Products_Cache::flush();
		Product_Groups_Cache::flush();
		Company_Cache::flush();

		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}
}
