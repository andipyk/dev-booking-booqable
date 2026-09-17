<?php
/**
 * Settings → Booqable Rental Core admin page.
 *
 * The access token is entered here, in wp-admin, and never leaves the
 * server — it's stored as a WP option and used only server-side by
 * Client::get(). The field never re-displays a saved value in the HTML;
 * it shows a masked placeholder instead, so re-opening this page can't
 * leak the token back out.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Settings {

	const OPTION_GROUP        = 'booqable_core_settings';
	const TOKEN_OPTION        = 'booqable_core_access_token';
	const SLUG_OPTION         = 'booqable_core_company_slug';
	const INSTRUCTIONS_OPTION = 'booqable_core_payment_instructions';
	const HOLD_HOURS_OPTION   = 'booqable_core_order_hold_hours';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_booqable_core_save_token', array( $this, 'handle_save' ) );
		add_action( 'admin_post_booqable_core_regenerate_webhook', array( $this, 'handle_regenerate_webhook' ) );
		add_action( 'wp_ajax_booqable_core_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	public function add_page() {
		add_options_page(
			__( 'Booqable Rental Core', 'booqable-rental-core' ),
			__( 'Booqable Rental Core', 'booqable-rental-core' ),
			'manage_options',
			'booqable-rental-core',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::TOKEN_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::SLUG_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::INSTRUCTIONS_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::HOLD_HOURS_OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => Order_Expiry::DEFAULT_HOLD_HOURS,
			)
		);
	}

	/**
	 * Manual admin-post handler (rather than a raw settings form submit)
	 * so we can tell "field left blank on purpose" apart from "field left
	 * blank because we never echo the real value back" — leave it empty
	 * and the existing token is kept as-is.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'booqable-rental-core' ) );
		}
		check_admin_referer( 'booqable_core_save_token' );

		if ( isset( $_POST['booqable_core_company_slug'] ) ) {
			update_option( self::SLUG_OPTION, sanitize_title( wp_unslash( $_POST['booqable_core_company_slug'] ) ) );
		}

		$new_token = isset( $_POST['booqable_core_access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['booqable_core_access_token'] ) ) : '';

		if ( '' !== $new_token ) {
			update_option( self::TOKEN_OPTION, $new_token );
		}

		if ( ! empty( $_POST['booqable_core_clear_token'] ) ) {
			delete_option( self::TOKEN_OPTION );
		}

		if ( isset( $_POST[ self::INSTRUCTIONS_OPTION ] ) ) {
			update_option( self::INSTRUCTIONS_OPTION, wp_kses_post( wp_unslash( $_POST[ self::INSTRUCTIONS_OPTION ] ) ) );
		}

		if ( isset( $_POST[ self::HOLD_HOURS_OPTION ] ) ) {
			update_option( self::HOLD_HOURS_OPTION, max( 1, absint( $_POST[ self::HOLD_HOURS_OPTION ] ) ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'booqable-rental-core', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function handle_regenerate_webhook() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'booqable-rental-core' ) );
		}
		check_admin_referer( 'booqable_core_regenerate_webhook' );

		delete_option( Webhook::SECRET_OPTION );
		Webhook::get_secret(); // Regenerates immediately.

		wp_safe_redirect( add_query_arg( array( 'page' => 'booqable-rental-core', 'webhook_regenerated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'booqable_core_test_connection' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'booqable-rental-core' ) ), 403 );
		}

		$client = new Client();
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connected — Booqable API responded successfully.', 'booqable-rental-core' ) ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$has_token     = '' !== trim( (string) get_option( self::TOKEN_OPTION, '' ) );
		$company_slug  = trim( (string) get_option( self::SLUG_OPTION, '' ) );
		$webhook_url   = Webhook::get_url();
		$nonce_connect = wp_create_nonce( 'booqable_core_test_connection' );
		$instructions  = (string) get_option( self::INSTRUCTIONS_OPTION, '' );
		$hold_hours    = (int) get_option( self::HOLD_HOURS_OPTION, Order_Expiry::DEFAULT_HOLD_HOURS );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Booqable Rental Core', 'booqable-rental-core' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'booqable-rental-core' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['webhook_regenerated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Webhook URL regenerated — update it in your Booqable dashboard.', 'booqable-rental-core' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Connection', 'booqable-rental-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'booqable_core_save_token' ); ?>
				<input type="hidden" name="action" value="booqable_core_save_token" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="booqable_core_company_slug"><?php esc_html_e( 'Company Slug', 'booqable-rental-core' ); ?></label></th>
						<td>
							<input type="text" id="booqable_core_company_slug" name="booqable_core_company_slug" class="regular-text" value="<?php echo esc_attr( $company_slug ); ?>" placeholder="yourcompany" />
							<p class="description">
								<?php esc_html_e( 'The subdomain in your Booqable URL — e.g. if you log in at "yourcompany.booqable.com", enter "yourcompany".', 'booqable-rental-core' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="booqable_core_access_token"><?php esc_html_e( 'Access Token', 'booqable-rental-core' ); ?></label></th>
						<td>
							<input type="password" id="booqable_core_access_token" name="booqable_core_access_token" class="regular-text" autocomplete="off" placeholder="<?php echo $has_token ? esc_attr__( '•••••••• (saved — leave blank to keep it)', 'booqable-rental-core' ) : esc_attr__( 'Paste your Booqable access token', 'booqable-rental-core' ); ?>" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to generate a Booqable access token */
									esc_html__( 'Generate one at %s (Booqable dashboard → your name → Access Tokens). This field never shows the saved value — it only accepts a new one.', 'booqable-rental-core' ),
									'<code>{company}.booqable.com/employees/current</code>'
								);
								?>
							</p>
							<?php if ( $has_token ) : ?>
								<label><input type="checkbox" name="booqable_core_clear_token" value="1" /> <?php esc_html_e( 'Remove the saved token', 'booqable-rental-core' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Checkout & Payment', 'booqable-rental-core' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Stripe is not available for this account, so checkout collects the booking and shows these manual payment instructions instead of a card form. A staff member confirms the transfer and records the payment directly in the Booqable dashboard.', 'booqable-rental-core' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::INSTRUCTIONS_OPTION ); ?>"><?php esc_html_e( 'Payment Instructions', 'booqable-rental-core' ); ?></label></th>
						<td>
							<?php
							wp_editor(
								$instructions,
								self::INSTRUCTIONS_OPTION,
								array(
									'textarea_name' => self::INSTRUCTIONS_OPTION,
									'textarea_rows' => 8,
									'media_buttons' => true,
									'teeny'         => true,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'Shown to visitors right after they check out — bank account details, a QRIS image (use "Add Media" above), or any other manual payment instructions.', 'booqable-rental-core' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::HOLD_HOURS_OPTION ); ?>"><?php esc_html_e( 'Order Hold Duration (hours)', 'booqable-rental-core' ); ?></label></th>
						<td>
							<input type="number" min="1" step="1" id="<?php echo esc_attr( self::HOLD_HOURS_OPTION ); ?>" name="<?php echo esc_attr( self::HOLD_HOURS_OPTION ); ?>" class="small-text" value="<?php echo esc_attr( $hold_hours ); ?>" />
							<p class="description">
								<?php esc_html_e( 'How long an order can wait for payment confirmation before it\'s automatically canceled and the stock released. Applies to abandoned carts too. A background check runs every 30 minutes.', 'booqable-rental-core' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save', 'booqable-rental-core' ) ); ?>
			</form>

			<?php if ( $has_token ) : ?>
				<p>
					<button type="button" class="button" id="booqable-core-test-connection"><?php esc_html_e( 'Test Connection', 'booqable-rental-core' ); ?></button>
					<span id="booqable-core-test-connection-result" style="margin-left:8px;"></span>
				</p>
				<script>
				document.getElementById('booqable-core-test-connection').addEventListener('click', function () {
					var btn = this, result = document.getElementById('booqable-core-test-connection-result');
					btn.disabled = true;
					result.textContent = '<?php echo esc_js( __( 'Testing…', 'booqable-rental-core' ) ); ?>';
					var body = new URLSearchParams();
					body.set('action', 'booqable_core_test_connection');
					body.set('_ajax_nonce', '<?php echo esc_js( $nonce_connect ); ?>');
					fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (data) {
							result.textContent = (data.data && data.data.message) ? data.data.message : (data.success ? 'OK' : 'Failed');
							result.style.color = data.success ? '#046a04' : '#a00';
						})
						.catch(function () { result.textContent = '<?php echo esc_js( __( 'Request failed.', 'booqable-rental-core' ) ); ?>'; result.style.color = '#a00'; })
						.finally(function () { btn.disabled = false; });
				});
				</script>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Webhook (real-time sync)', 'booqable-rental-core' ); ?></h2>
			<p><?php esc_html_e( 'Paste this URL into your Booqable dashboard\'s webhook settings so category/inventory changes refresh here immediately instead of waiting up to 15 minutes.', 'booqable-rental-core' ); ?></p>
			<p><input type="text" readonly class="large-text code" value="<?php echo esc_attr( $webhook_url ); ?>" onclick="this.select();" /></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'booqable_core_regenerate_webhook' ); ?>
				<input type="hidden" name="action" value="booqable_core_regenerate_webhook" />
				<?php submit_button( __( 'Regenerate Webhook URL', 'booqable-rental-core' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
