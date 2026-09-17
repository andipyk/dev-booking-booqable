<?php
/**
 * Booqable Rental Theme functions and definitions.
 *
 * Standalone block theme — no parent theme required.
 *
 * @package Booqable_Rental_Theme
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'BOOQABLE_RENTAL_THEME_VERSION', '0.1.0' );

/**
 * Theme setup: editor style + pattern category.
 * Block-theme defaults (title-tag, post-thumbnails, editor-styles, html5,
 * wp-block-styles, align-wide, responsive-embeds) are enabled automatically
 * by WordPress core because this theme ships templates/index.html.
 */
function booqable_rental_theme_setup() {
	add_editor_style( 'assets/css/editor-style.css' );

	register_block_pattern_category(
		'booqable-rental',
		array(
			'label'       => __( 'Rental Business', 'booqable-rental-theme' ),
			'description' => __( 'Hero, services, gallery, and CTA patterns for rental and staging businesses.', 'booqable-rental-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'booqable_rental_theme_setup' );

/**
 * Theme stylesheet (style.css) — required by WordPress so the theme is
 * recognized correctly; carries no visual rules of its own (theme.json +
 * custom.css own the design system).
 */
function booqable_rental_theme_enqueue_styles() {
	wp_enqueue_style(
		'booqable-rental-theme-style',
		get_stylesheet_uri(),
		array(),
		BOOQABLE_RENTAL_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'booqable_rental_theme_enqueue_styles' );

/**
 * Google Fonts: Bricolage Grotesque (headings) + Plus Jakarta Sans (body).
 */
function booqable_rental_theme_enqueue_fonts() {
	wp_enqueue_style(
		'booqable-rental-theme-fonts',
		'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap',
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'booqable_rental_theme_enqueue_fonts' );

/**
 * GSAP + ScrollTrigger (cdnjs) and our own motion/CSS assets.
 * Loaded after the Booqable plugin's own script so we never race it; our
 * JS only ever queries our own `.sc-*` classes, never Booqable's markup.
 */
function booqable_rental_theme_enqueue_motion_assets() {
	wp_enqueue_script(
		'gsap',
		'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
		array(),
		'3.12.5',
		true
	);

	wp_enqueue_script(
		'gsap-scrolltrigger',
		'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
		array( 'gsap' ),
		'3.12.5',
		true
	);

	wp_enqueue_style(
		'booqable-rental-theme-custom-style',
		get_stylesheet_directory_uri() . '/assets/css/custom.css',
		array( 'booqable-rental-theme-style' ),
		BOOQABLE_RENTAL_THEME_VERSION
	);

	wp_enqueue_script(
		'booqable-rental-theme-motion',
		get_stylesheet_directory_uri() . '/assets/js/motion.js',
		array( 'gsap', 'gsap-scrolltrigger' ),
		BOOQABLE_RENTAL_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'booqable_rental_theme_enqueue_motion_assets', 20 );

/**
 * Nav menu location kept for classic-menu compatibility (e.g. widgets, some
 * plugins still call wp_nav_menu()). Block navigation itself uses a
 * `core/navigation` entity (wp_navigation post), set from the Site Editor —
 * this registration doesn't replace that.
 */
function booqable_rental_theme_menus() {
	register_nav_menus(
		array(
			'primary' => __( 'Primary Navigation', 'booqable-rental-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'booqable_rental_theme_menus' );

/**
 * Admin notice: nudge activation of the companion plugin once it exists,
 * without hard-requiring it (theme still works standalone — Booqable
 * blocks/live category sync are additive, not load-bearing).
 */
function booqable_rental_theme_companion_notice() {
	if ( defined( 'BOOQABLE_RENTAL_CORE_VERSION' ) ) {
		return;
	}
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'themes' !== $screen->id ) {
		return;
	}
	echo '<div class="notice notice-info is-dismissible"><p>' .
		esc_html__( 'Install the free "Booqable Rental Theme Core" plugin to unlock live category sync and native Gutenberg blocks for your Booqable inventory.', 'booqable-rental-theme' ) .
		'</p></div>';
}
add_action( 'admin_notices', 'booqable_rental_theme_companion_notice' );
