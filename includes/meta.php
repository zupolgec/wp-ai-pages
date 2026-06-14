<?php
/**
 * Meta delle landing: chiave univoca, toggle chrome e campi SEO/OG.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'aip_register_meta' );
function aip_register_meta() {
	$keys = [
		'_aip_landing_key', // chiave univoca per upsert idempotente
		'_aip_chrome',      // none|site|full
		'_aip_shortcodes',  // '1' = esegui do_shortcode sul contenuto
	];
	foreach ( $keys as $key ) {
		register_post_meta( 'ai_page', $key, [
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );
	}
}
