<?php
/**
 * Enqueues the cart script/style and prints the (empty) drawer markup that
 * cart.js fills in — the shell lives here rather than in the theme because
 * its content is entirely client-rendered, not static presentation. The
 * floating toggle button lives here too (not in the theme's header part) so
 * the cart keeps working even if the theme is ever switched — it doesn't
 * depend on any particular header markup existing.
 *
 * The cart itself is no longer a Booqable Order (see class-checkout.php for
 * why) — it's plain state in the visitor's own browser (`localStorage`), so
 * this class has nothing to localize from a live API call, and no cookie of
 * its own to manage. Booqable only hears about the cart once, at Checkout.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Cart_Widget {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_drawer' ) );
	}

	public function enqueue() {
		wp_enqueue_style(
			'booqable-core-cart',
			BOOQABLE_RENTAL_CORE_URL . 'assets/css/cart.css',
			array(),
			BOOQABLE_RENTAL_CORE_VERSION
		);

		wp_enqueue_script(
			'booqable-core-cart',
			BOOQABLE_RENTAL_CORE_URL . 'assets/js/cart.js',
			array(),
			BOOQABLE_RENTAL_CORE_VERSION,
			true
		);

		wp_localize_script(
			'booqable-core-cart',
			'BooqableCartConfig',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'booqable-core/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'currency' => Money::get_js_config(), // Real currency from the Booqable account — never hardcoded.
			)
		);
	}

	public function render_drawer() {
		?>
		<button id="bq-cart-toggle" type="button" aria-label="<?php esc_attr_e( 'Open cart', 'booqable-rental-core' ); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 4h2l1.6 10.6a2 2 0 0 0 2 1.7h8.2a2 2 0 0 0 2-1.6L20 8H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9.5" cy="20" r="1.4" fill="currentColor"/><circle cx="17.5" cy="20" r="1.4" fill="currentColor"/></svg>
			<span id="bq-cart-count">0</span>
		</button>

		<div id="bq-cart-drawer" aria-hidden="true">
			<div class="bq-cart-head">
				<strong><?php esc_html_e( 'Your Booking', 'booqable-rental-core' ); ?></strong>
				<button id="bq-cart-close" type="button"><span aria-hidden="true">&times;</span><span class="screen-reader-text"><?php esc_html_e( 'Close cart', 'booqable-rental-core' ); ?></span></button>
			</div>
			<div id="bq-cart-view">
				<div class="bq-cart-dates">
					<label>
						<?php esc_html_e( 'Start', 'booqable-rental-core' ); ?>
						<input type="date" id="bq-cart-starts" />
					</label>
					<label>
						<?php esc_html_e( 'End', 'booqable-rental-core' ); ?>
						<input type="date" id="bq-cart-stops" />
					</label>
				</div>
				<p id="bq-cart-shortage" hidden><?php esc_html_e( 'One or more items have reached the stock we last checked for your dates — the final amount may still shift slightly at checkout.', 'booqable-rental-core' ); ?></p>
				<div id="bq-cart-body">
					<p class="bq-cart-empty"><?php esc_html_e( 'Your cart is empty.', 'booqable-rental-core' ); ?></p>
				</div>
				<div id="bq-cart-foot">
					<div class="bq-cart-total-row">
						<span><?php esc_html_e( 'Estimated total', 'booqable-rental-core' ); ?></span>
						<span id="bq-cart-total"><?php echo esc_html( Money::format( 0 ) ); ?></span>
					</div>
					<p class="bq-cart-estimate-note"><?php esc_html_e( 'Estimate only — the final amount (duration, any adjustments) is confirmed by Booqable right after you check out.', 'booqable-rental-core' ); ?></p>
					<button type="button" id="bq-cart-checkout"><?php esc_html_e( 'Checkout', 'booqable-rental-core' ); ?></button>
				</div>
			</div>

			<div id="bq-checkout-panel" hidden>
				<p class="bq-checkout-intro"><?php esc_html_e( 'Enter your details to reserve this booking. Payment is arranged separately — see the instructions on the next screen.', 'booqable-rental-core' ); ?></p>
				<label class="bq-checkout-field">
					<?php esc_html_e( 'Name', 'booqable-rental-core' ); ?>
					<input type="text" id="bq-checkout-name" autocomplete="name" required />
				</label>
				<label class="bq-checkout-field">
					<?php esc_html_e( 'Email', 'booqable-rental-core' ); ?>
					<input type="email" id="bq-checkout-email" autocomplete="email" required />
				</label>
				<label class="bq-checkout-field">
					<?php esc_html_e( 'Phone (optional)', 'booqable-rental-core' ); ?>
					<input type="tel" id="bq-checkout-phone" autocomplete="tel" />
				</label>
				<button type="button" id="bq-checkout-submit" class="bq-checkout-primary"><?php esc_html_e( 'Confirm Booking', 'booqable-rental-core' ); ?></button>
				<button type="button" id="bq-checkout-back" class="bq-checkout-secondary"><?php esc_html_e( '← Back to cart', 'booqable-rental-core' ); ?></button>
			</div>

			<div id="bq-checkout-confirmation" hidden>
				<p class="bq-checkout-success"><?php esc_html_e( 'Booking confirmed!', 'booqable-rental-core' ); ?> <?php esc_html_e( 'Order #', 'booqable-rental-core' ); ?><span id="bq-checkout-order-number"></span></p>
				<p class="bq-checkout-total"><?php esc_html_e( 'Amount due:', 'booqable-rental-core' ); ?> <strong id="bq-checkout-order-total"></strong></p>
				<p id="bq-checkout-shortage" hidden><?php esc_html_e( 'Heads up: one or more items in this booking may be tighter on stock than expected — our team will confirm availability with you.', 'booqable-rental-core' ); ?></p>
				<div id="bq-checkout-instructions"></div>
				<button type="button" id="bq-checkout-done" class="bq-checkout-primary"><?php esc_html_e( 'Done', 'booqable-rental-core' ); ?></button>
			</div>
		</div>
		<div id="bq-cart-error" hidden></div>
		<?php
	}
}
