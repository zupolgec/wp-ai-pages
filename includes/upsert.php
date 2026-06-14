<?php
/**
 * Logica di upsert condivisa: usata sia da WP-CLI che dall'endpoint REST.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Genera un token API casuale (48 caratteri esadecimali).
 */
function aip_generate_token() {
	return bin2hex( random_bytes( 24 ) );
}

/**
 * Scrive una ai_page mantenendo l'HTML raw (doctype/html/head/body/script).
 * KSES viene disattivato solo attorno alla nostra scrittura, poi ripristinato,
 * così il risultato è identico in ogni contesto (REST, WP-CLI, admin),
 * a prescindere dalla capability unfiltered_html dell'utente.
 *
 * @param array $postarr Dati post (con 'ID' per update).
 * @return int|WP_Error
 */
function aip_write_post( array $postarr ) {
	$kses_active = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	if ( $kses_active ) {
		kses_remove_filters();
	}

	$res = isset( $postarr['ID'] )
		? wp_update_post( $postarr, true )
		: wp_insert_post( $postarr, true );

	if ( $kses_active ) {
		kses_init_filters();
	}
	return $res;
}

/**
 * Crea o aggiorna una landing in modo idempotente per landing_key.
 *
 * @param array $args key, html, title?, slug?, chrome?, status?
 * @return array|WP_Error  [id, url, action] oppure WP_Error.
 */
function aip_upsert_landing( array $args ) {
	$key = sanitize_text_field( $args['key'] ?? '' );
	if ( '' === $key ) {
		return new WP_Error( 'aip_missing_key', 'Parametro "key" obbligatorio.' );
	}

	$html = (string) ( $args['html'] ?? '' );
	if ( '' === trim( $html ) ) {
		return new WP_Error( 'aip_empty_html', 'Nessun contenuto HTML fornito.' );
	}

	$default_chrome = get_option( 'aip_default_chrome', 'full' );
	$chrome = in_array( $args['chrome'] ?? '', [ 'none', 'site', 'full' ], true ) ? $args['chrome'] : $default_chrome;
	$status = in_array( $args['status'] ?? 'publish', [ 'publish', 'draft' ], true ) ? $args['status'] : 'publish';
	$slug   = sanitize_title( $args['slug'] ?? $key );
	$title  = sanitize_text_field( $args['title'] ?? $key );

	$existing = get_posts( [
		'post_type'   => 'ai_page',
		'post_status' => 'any',
		'meta_key'    => '_aip_landing_key',
		'meta_value'  => $key,
		'numberposts' => 1,
		'fields'      => 'ids',
	] );

	$postarr = [
		'post_type'    => 'ai_page',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => wp_slash( $html ),
		'post_status'  => $status,
	];

	if ( ! empty( $args['author'] ) ) {
		$postarr['post_author'] = (int) $args['author'];
	}

	if ( $existing ) {
		$postarr['ID'] = $existing[0];
		$action        = 'updated';
	} else {
		$action = 'created';
	}
	$id = aip_write_post( $postarr );

	if ( is_wp_error( $id ) ) {
		return $id;
	}

	update_post_meta( $id, '_aip_landing_key', $key );
	update_post_meta( $id, '_aip_chrome', $chrome );

	return [
		'id'     => $id,
		'url'    => get_permalink( $id ),
		'action' => $action,
	];
}
