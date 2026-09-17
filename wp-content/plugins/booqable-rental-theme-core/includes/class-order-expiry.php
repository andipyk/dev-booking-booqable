<?php
/**
 * Auto-releases stale, unpaid orders (`new` carts nobody ever checked out,
 * and `reserved` orders nobody paid for in time) so they don't lock real
 * inventory forever — see the "Inventory hold" discussion in
 * references/payments.md. Configurable hold window: Settings →
 * Booqable Rental Core → "Order hold duration".
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Order_Expiry {

	const HOOK               = 'booqable_core_expire_orders';
	const SCHEDULE           = 'booqable_core_thirty_minutes';
	const HOLD_HOURS_OPTION  = 'booqable_core_order_hold_hours';
	const DEFAULT_HOLD_HOURS = 24;

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * @param array $schedules
	 * @return array
	 */
	public function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes (Booqable order expiry)', 'booqable-rental-core' ),
		);
		return $schedules;
	}

	/**
	 * Lazily schedules the sweep (rather than relying on an activation hook,
	 * so it also self-heals if the event is ever lost — e.g. after a cron
	 * plugin reset) instead of requiring reactivation.
	 */
	public function maybe_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), self::SCHEDULE, self::HOOK );
		}
	}

	/**
	 * @return int Hours an unpaid order is allowed to hold inventory before
	 *             being auto-canceled. Never less than 1.
	 */
	public static function get_hold_hours() {
		return max( 1, (int) get_option( self::HOLD_HOURS_OPTION, self::DEFAULT_HOLD_HOURS ) );
	}

	/**
	 * Cron handler. Cancels `new`/`reserved` orders last touched before the
	 * hold window — except any that already show a payment (paid/
	 * partially_paid/overpaid), which are left for staff to handle manually
	 * rather than risk auto-canceling something a customer already paid for.
	 */
	public function run() {
		$client = new Client();
		if ( ! $client->is_configured() ) {
			return;
		}

		$before = gmdate( 'c', time() - self::get_hold_hours() * HOUR_IN_SECONDS );
		$orders = $client->get_stale_orders( array( 'new', 'reserved' ), $before );
		if ( is_wp_error( $orders ) ) {
			return;
		}

		foreach ( $orders as $order ) {
			if ( in_array( $order['payment_status'], array( 'paid', 'partially_paid', 'overpaid' ), true ) ) {
				continue;
			}
			$client->cancel_order( $order['id'], $order['status'] );
		}
	}
}
