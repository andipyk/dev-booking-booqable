<?php
/**
 * Checkout — the one moment the visitor's cart actually becomes a Booqable
 * Order. Every earlier phase (add to cart, qty +/-, remove, change dates)
 * now lives entirely in the browser's own `localStorage`
 * (see assets/js/cart.js) — Booqable is never touched while a visitor is
 * just browsing, only a cheap, side-effect-free stock check
 * (Availability_Rest) runs in the background. That's a deliberate reversal
 * of the original Cart-phase design (an Order created and booked on the
 * first "Book" click) once real usage showed every one of those live calls
 * cost 1-5s and made adding to cart feel broken — see known-gaps.md and
 * cart-and-checkout.md for the full before/after.
 *
 * Trade-off, made explicitly with the project owner: stock isn't held in
 * Booqable until this step, so two visitors can both have the same item in
 * their browser-side cart; Booqable itself doesn't hard-reject an
 * over-booked `book_product` call, it just flags the resulting order
 * `location_shortage` (already surfaced to the confirmation screen below) —
 * consistent with how shortage was already handled before this change.
 *
 * There is no card payment here (see references/payments.md: Stripe isn't
 * available for this account); this only creates the order, books every
 * line, attaches a Customer, and transitions the order `new` → `reserved`.
 * Payment itself is manual/offline — the visitor sees bank transfer/QRIS
 * instructions, and a staff member marks the order paid directly in the
 * Booqable dashboard once the transfer is confirmed.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Checkout {

	/** @var Client */
	protected $client;

	public function __construct() {
		$this->client = new Client();
	}

	/**
	 * @param string $name
	 * @param string $email
	 * @param string $phone
	 * @param string $starts_at ISO 8601.
	 * @param string $stops_at  ISO 8601.
	 * @param array  $lines     array<{product_id: string, quantity: int}> — the
	 *                          visitor's browser-side cart, sent fresh with
	 *                          this request (nothing to read server-side,
	 *                          there's no order yet).
	 * @return array|\WP_Error Mapped order (status `reserved`, real `number`).
	 */
	public function submit( $name, $email, $phone, $starts_at, $stops_at, $lines ) {
		if ( empty( $lines ) ) {
			return new \WP_Error(
				'booqable_core_empty_cart',
				__( 'Your cart is empty — add something before checking out.', 'booqable-rental-core' ),
				array( 'status' => 400 )
			);
		}

		// Phase 1 — order creation and the customer-by-email lookup touch
		// different resources and don't need each other's result, so they
		// go out in the same round trip instead of one after the other.
		// See cart-and-checkout.md#performance-note.
		$phase1 = $this->client->request_multiple(
			array(
				'order'    => $this->client->order_create_spec( $starts_at, $stops_at ),
				'customer' => $this->client->customer_lookup_spec( $email ),
			)
		);

		if ( is_wp_error( $phase1['order'] ) ) {
			return $phase1['order'];
		}
		$order    = $this->client->map_order( $phase1['order']['data'] ?? array() );
		$order_id = $order['id'];

		// A lookup failure (network hiccup, etc.) is treated exactly like
		// "no existing customer" — the same fall-through-and-create contract
		// Client::find_or_create_customer() always had.
		$customer_id = ( ! is_wp_error( $phase1['customer'] ) && ! empty( $phase1['customer']['data'][0]['id'] ) )
			? $phase1['customer']['data'][0]['id']
			: null;

		// Phase 2 — every line's booking only depends on $order_id, never on
		// the other lines, so all of them go out together instead of one
		// HTTP round trip per line. If the customer doesn't exist yet,
		// creating them goes in the same batch too (a brand-new customer
		// row has no dependency on the order's lines either).
		//
		// Deliberately NOT batched here: attaching the customer to the
		// order (set_order_customer, below) and reserving it — both write
		// to the *same* order resource these book_product calls also write
		// to, and nothing here has verified live that Booqable's API
		// handles concurrent writes to one order safely. Parallelizing
		// several book_product calls against each other is the same
		// assumption, just harder to avoid without giving up most of the
		// win for a multi-line cart — verify live with a 2+ line booking
		// before trusting this at real volume, the same discipline every
		// other "confirmed live" claim in this codebase already follows.
		$phase2_calls = array();
		foreach ( $lines as $index => $line ) {
			$phase2_calls[ 'line_' . $index ] = $this->client->book_product_spec( $order_id, $line['product_id'], $line['quantity'] );
		}
		if ( null === $customer_id ) {
			$phase2_calls['new_customer'] = $this->client->customer_create_spec( $name, $email );
		}
		$phase2 = $this->client->request_multiple( $phase2_calls );

		foreach ( $lines as $index => $line ) {
			$booked = $this->client->extract_line( $phase2[ 'line_' . $index ] );
			if ( is_wp_error( $booked ) ) {
				// Don't leave a partial, customer-less order sitting in
				// Booqable — cancel what was created so far and let the
				// visitor see the error and retry. `Order_Expiry` would
				// eventually clean this up too, but there's no reason to
				// wait for the next sweep.
				$this->client->cancel_order( $order_id, 'new' );
				return $booked;
			}
		}

		if ( null === $customer_id ) {
			$created = $phase2['new_customer'];
			if ( is_wp_error( $created ) ) {
				$this->client->cancel_order( $order_id, 'new' );
				return $created;
			}
			$customer_id = $created['data']['id'] ?? '';
			if ( '' === $customer_id ) {
				$this->client->cancel_order( $order_id, 'new' );
				return new \WP_Error( 'booqable_core_no_customer', __( 'Booqable did not return a customer id.', 'booqable-rental-core' ) );
			}
			if ( '' !== trim( (string) $phone ) ) {
				$this->client->attach_customer_phone( $customer_id, $phone );
			}
		}

		$order = $this->client->set_order_customer( $order_id, $customer_id );
		if ( is_wp_error( $order ) ) {
			$this->client->cancel_order( $order_id, 'new' );
			return $order;
		}

		$reserved = $this->client->reserve_order( $order_id );
		if ( is_wp_error( $reserved ) ) {
			// Previously this error was returned as-is, leaving a fully
			// booked, customer-attached order stuck in `new` until the next
			// Order_Expiry sweep — a shortage/conflict at reservation time
			// is exactly the case that sweep exists for, but there's no
			// reason to make the visitor wait 30 minutes for the inventory
			// to actually free up if they never retry.
			$this->client->cancel_order( $order_id, 'new' );
			return $reserved;
		}

		return $reserved;
	}
}
