<?php
/**
 * Server-side render for booqable-core/product-catalog.
 *
 * Pulls product groups directly from the Booqable API — no client-side
 * widget script, no shortcode. Each card resolves the real, bookable
 * Product id behind its Product Group (via Products_Cache) so the "Book"
 * button can call the cart REST proxy directly.
 *
 * @package Booqable_Rental_Core
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$collection   = ! empty( $attributes['collectionId'] ) ? $attributes['collectionId'] : null;
$limit        = max( 1, (int) ( $attributes['limit'] ?? 12 ) );
$products     = \Booqable_Rental_Core\Product_Groups_Cache::get( $collection, $limit );
$wrapper_attr = get_block_wrapper_attributes( array( 'class' => 'sc-catalog' ) );

if ( is_wp_error( $products ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		printf(
			'<div %1$s><p class="sc-catalog-notice">%2$s %3$s</p></div>',
			$wrapper_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Booqable Product Catalog: not connected yet.', 'booqable-rental-core' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=booqable-rental-core' ) ) . '">' . esc_html__( 'Fix in Settings →', 'booqable-rental-core' ) . '</a>'
		);
	}
	return;
}

if ( empty( $products ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		printf(
			'<div %1$s><p class="sc-catalog-notice">%2$s</p></div>',
			$wrapper_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Booqable Product Catalog: connected, but no products are marked "show in store" yet in your Booqable account.', 'booqable-rental-core' )
		);
	}
	return;
}

// Group the flat products list by product_group_id so each card can find
// its own real, bookable Product id without one API call per card.
$all_products     = \Booqable_Rental_Core\Products_Cache::get();
$products_by_group = array();
if ( ! is_wp_error( $all_products ) ) {
	foreach ( $all_products as $p ) {
		$products_by_group[ $p['product_group_id'] ][] = $p;
	}
}

echo '<div ' . $wrapper_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

foreach ( $products as $product ) {
	$price    = \Booqable_Rental_Core\Money::format( $product['price_in_cents'] );
	$variants = $products_by_group[ $product['id'] ] ?? array();

	$button = '';
	if ( 1 === count( $variants ) ) {
		// Exactly one Product behind this group — safe to book directly.
		// Price/title/period ride along on the button itself so cart.js can
		// build the (local, no-API) cart line without a round trip back to
		// Booqable just to ask what it already rendered — see
		// assets/js/cart.js and known-gaps.md.
		$button = sprintf(
			'<button type="button" class="sc-product-book" data-bq-add-to-cart data-product-id="%s" data-title="%s" data-price-in-cents="%d" data-price-period="%s">%s</button>',
			esc_attr( $variants[0]['id'] ),
			esc_attr( $product['name'] ),
			(int) $product['price_in_cents'],
			esc_attr( (string) $product['price_period'] ),
			esc_html__( 'Book', 'booqable-rental-core' )
		);
	} elseif ( count( $variants ) > 1 ) {
		// Multiple variations — no picker UI yet, don't wire a button to a
		// guessed variant. See known-gaps.md.
		$button = sprintf(
			'<button type="button" class="sc-product-book" disabled title="%s">%s</button>',
			esc_attr__( 'This product has multiple options — choose on the product page (coming soon).', 'booqable-rental-core' ),
			esc_html__( 'Choose options', 'booqable-rental-core' )
		);
	}

	printf(
		'<article class="sc-product-card">
			<div class="sc-product-media"%1$s></div>
			<div class="sc-product-body">
				<h3 class="sc-product-name">%2$s</h3>
				<p class="sc-product-price">%3$s%4$s</p>
				%5$s
			</div>
		</article>',
		$product['photo_url'] ? ' style="background-image:url(\'' . esc_url( $product['photo_url'] ) . '\')"' : '',
		esc_html( $product['name'] ),
		esc_html( $price ),
		$product['price_period'] ? ' <span>/ ' . esc_html( $product['price_period'] ) . '</span>' : '',
		$button // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above.
	);
}

echo '</div>';
