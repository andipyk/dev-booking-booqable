<?php
/**
 * Registers every block shipped in build/ — one register_block_type() call
 * per block.json, nothing hardcoded per-block here so adding a new block
 * is just "add a folder under src/, rebuild".
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Blocks {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register() {
		$build_dir = BOOQABLE_RENTAL_CORE_DIR . 'build';

		if ( ! is_dir( $build_dir ) ) {
			return;
		}

		foreach ( glob( $build_dir . '/*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}

	/**
	 * Read-only, editor-facing helper so block Inspector Controls can offer
	 * a "pick a collection" dropdown instead of asking editors to paste an
	 * ID. Only ever returns id/name/slug — never the token or anything
	 * account-sensitive — so it's safe to leave publicly readable the same
	 * way the front-end blocks themselves are.
	 */
	public function register_routes() {
		register_rest_route(
			'booqable-core/v1',
			'/collections',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_collections' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_collections() {
		$collections = Collections_Cache::get();
		if ( is_wp_error( $collections ) ) {
			return new \WP_REST_Response( array(), 200 );
		}
		return new \WP_REST_Response(
			array_map(
				function ( $c ) {
					return array( 'id' => $c['id'], 'name' => $c['name'] );
				},
				$collections
			),
			200
		);
	}
}
