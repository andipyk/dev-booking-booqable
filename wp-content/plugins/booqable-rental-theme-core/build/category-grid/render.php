<?php
/**
 * Server-side render for booqable-core/category-grid.
 *
 * Renders identically in the editor (WP 6.4+ block.json "render" field) and
 * on the front end — one code path, no duplicate JS/PHP rendering logic to
 * keep in sync. Reuses the exact same `.sc-bento` grid CSS the theme's
 * hero/services pattern already ships, so it looks native regardless of
 * which theme is active (falls back to plain, unstyled cards otherwise).
 *
 * @package Booqable_Rental_Core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content (unused, no InnerBlocks).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$limit     = max( 1, (int) ( $attributes['limit'] ?? 6 ) );
$link_base = $attributes['linkBase'] ?? '/inventory/';

$collections = \Booqable_Rental_Core\Collections_Cache::get();

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'sc-bento booqable-core-category-grid' ) );

if ( is_wp_error( $collections ) ) {
	// Fails soft: editors/admins see why, visitors just see nothing (no
	// broken layout, no PHP warnings) until the connection is fixed.
	if ( current_user_can( 'manage_options' ) ) {
		printf(
			'<div %1$s><p style="grid-column:1/-1;padding:1.5rem;border:1px dashed currentColor;border-radius:1rem;">%2$s %3$s</p></div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- from get_block_wrapper_attributes(), already escaped.
			esc_html__( 'Booqable Category Grid: not connected yet.', 'booqable-rental-core' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=booqable-rental-core' ) ) . '">' . esc_html__( 'Fix in Settings →', 'booqable-rental-core' ) . '</a>'
		);
	}
	return;
}

if ( empty( $collections ) ) {
	return;
}

$collections = array_slice( $collections, 0, $limit );
$count       = count( $collections );

echo '<div ' . $wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

foreach ( $collections as $index => $collection ) {
	$is_feature = ( 0 === $index && $count > 2 );
	$classes    = 'sc-bento-card' . ( $is_feature ? ' sc-bento-feature' : '' );
	$url        = trailingslashit( home_url( $link_base ) ) . '?collection=' . rawurlencode( $collection['slug'] ?: $collection['id'] );
	$image      = $collection['image_url'];

	printf(
		'<a class="%1$s" href="%2$s"%3$s><span class="sc-bento-tag">%4$s</span></a>',
		esc_attr( $classes ),
		esc_url( $url ),
		$image ? ' style="background-image:url(\'' . esc_url( $image ) . '\')"' : '',
		esc_html( $collection['name'] )
	);
}

echo '</div>';
