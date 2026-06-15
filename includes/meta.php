<?php
/**
 * Meta delle AI page: chiave univoca, toggle chrome e shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'aip_register_meta' );
function aip_register_meta() {
	$keys = [
		'_aip_page_key',   // chiave univoca per upsert idempotente
		'_aip_chrome',     // none|site|full
		'_aip_shortcodes', // '1' = esegui do_shortcode sul contenuto
	];
	foreach ( $keys as $key ) {
		register_post_meta( 'ai_page', $key, [
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return aip_current_user_can_edit_ai_pages();
			},
			'sanitize_callback' => 'sanitize_text_field',
		] );
	}
}
