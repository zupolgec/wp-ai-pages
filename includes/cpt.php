<?php
/**
 * Custom post type ai_page (le landing). Niente editor a blocchi: l'HTML si
 * modifica dal nostro editor con anteprima.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'aip_register_cpt' );
function aip_register_cpt() {
	$prefix = (string) get_option( 'aip_prefix', 'lp' );
	$prefix = trim( $prefix, '/' );

	register_post_type( 'ai_page', [
		'label'        => 'AI Pages',
		'labels'       => [
			'name'          => 'AI Pages',
			'singular_name' => 'AI Page',
			'add_new_item'  => 'Aggiungi AI page',
			'edit_item'     => 'Modifica AI page',
			'menu_name'     => 'AI Pages',
		],
		'public'       => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-welcome-widgets-menus',
		'supports'     => [ 'title', 'revisions' ],
		'rewrite'      => [ 'slug' => ( '' !== $prefix ? $prefix : '/' ), 'with_front' => false ],
		'has_archive'  => false,
	] );
}

/**
 * Rigenera le rewrite rules dopo un cambio di prefisso (flag impostato dalle
 * Impostazioni), quando il CPT è già registrato col nuovo prefisso.
 */
add_action( 'init', function () {
	if ( get_option( 'aip_flush_rewrite' ) ) {
		delete_option( 'aip_flush_rewrite' );
		flush_rewrite_rules();
	}
}, 99 );

/**
 * Gutenberg non è compatibile con l'HTML raw: disattiva il block editor per
 * le ai_page (l'editing avviene nel nostro editor CodeMirror + anteprima).
 */
add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
	return 'ai_page' === $post_type ? false : $use;
}, 10, 2 );

/**
 * Conserva l'HTML raw (script/style inline) quando si salva una ai_page,
 * bypassando KSES solo per questo CPT.
 */
add_filter( 'wp_insert_post_data', 'aip_keep_raw_html', 1, 2 );
function aip_keep_raw_html( $data, $postarr ) {
	if ( ( $data['post_type'] ?? '' ) === 'ai_page' && isset( $postarr['post_content'] ) ) {
		$data['post_content'] = $postarr['post_content'];
	}
	return $data;
}
