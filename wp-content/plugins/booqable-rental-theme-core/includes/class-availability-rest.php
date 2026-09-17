<?php
/**
 * Public, read-only stock-check route. Split out from what used to be
 * `Cart_Rest` when the cart itself stopped being a live Booqable order (see
 * class-checkout.php) — this is the one Booqable call that still has to
 * happen while the visitor is just browsing/adding to cart, since only
 * Booqable knows real stock. Never creates or touches an Order — a GET here
 * has no side effect in Booqable at all.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Availability_Rest {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'booqable-core/v1',
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'from'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'till'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Uses the account's first Location — this project has no
	 * multi-location UI yet (see known-gaps.md).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_availability( $request ) {
		$client    = new Client();
		$locations = $client->get_locations();
		if ( is_wp_error( $locations ) || empty( $locations ) ) {
			return new \WP_REST_Response( array( 'available' => null ), 200 );
		}

		$result = $client->get_inventory_availability(
			$request->get_param( 'product_id' ),
			$locations[0]['id'],
			$request->get_param( 'from' ),
			$request->get_param( 'till' )
		);
		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data()['status'] ?? 400;
			$result->add_data( array( 'status' => $status ) );
			return $result;
		}

		// Empty result means Booqable has no tracking data for this
		// item/range — treat as available rather than showing a false "0 left".
		return new \WP_REST_Response(
			array( 'available' => $result['available'] ?? null ),
			200
		);
	}
}
