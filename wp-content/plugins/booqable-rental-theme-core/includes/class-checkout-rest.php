<?php
/**
 * Public REST proxy for Checkout — nonce-gated, same trust model the cart
 * used to run under. The visitor's whole cart (dates + lines) travels in
 * this one request, since nothing about it lives server-side anymore until
 * now — see class-checkout.php. Only ever returns what the confirmation
 * screen needs (order number, total, shortage flag, payment instructions) —
 * never a passthrough of the raw Booqable order.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Checkout_Rest {

	const INSTRUCTIONS_OPTION = 'booqable_core_payment_instructions';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'booqable-core/v1',
			'/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
				'args'                => array(
					'name'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'phone'     => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'starts_at' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'stops_at'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'lines'     => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'product_id' => array( 'type' => 'string' ),
								'quantity'   => array( 'type' => 'integer' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function verify_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( $request ) {
		$name  = $request->get_param( 'name' );
		$email = $request->get_param( 'email' );
		$phone = $request->get_param( 'phone' );

		if ( '' === trim( (string) $name ) ) {
			return new \WP_Error( 'booqable_core_missing_name', __( 'Please enter your name.', 'booqable-rental-core' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'booqable_core_invalid_email', __( 'Please enter a valid email address.', 'booqable-rental-core' ), array( 'status' => 400 ) );
		}

		// Never trust the raw client payload as-is — re-shape it down to
		// exactly {product_id, quantity} per line, same discipline as every
		// other write route in this plugin.
		$lines = array();
		foreach ( (array) $request->get_param( 'lines' ) as $raw_line ) {
			$product_id = sanitize_text_field( (string) ( $raw_line['product_id'] ?? '' ) );
			if ( '' === $product_id ) {
				continue;
			}
			$lines[] = array(
				'product_id' => $product_id,
				'quantity'   => max( 1, absint( $raw_line['quantity'] ?? 1 ) ),
			);
		}

		$order = ( new Checkout() )->submit(
			$name,
			$email,
			$phone,
			sanitize_text_field( (string) $request->get_param( 'starts_at' ) ),
			sanitize_text_field( (string) $request->get_param( 'stops_at' ) ),
			$lines
		);
		if ( is_wp_error( $order ) ) {
			return $this->error_response( $order );
		}

		return new \WP_REST_Response(
			array(
				'order_number'         => $order['number'],
				'total'                => Money::format( $order['grand_total_in_cents'] ),
				'total_in_cents'       => $order['grand_total_in_cents'],
				'shortage'             => $order['location_shortage'] || $order['shortage'],
				'payment_instructions' => wp_kses_post( (string) get_option( self::INSTRUCTIONS_OPTION, '' ) ),
			),
			200
		);
	}

	/**
	 * @param \WP_Error $error
	 * @return \WP_Error
	 */
	protected function error_response( $error ) {
		$status = $error->get_error_data()['status'] ?? 400;
		$error->add_data( array( 'status' => $status ) );
		return $error;
	}
}
