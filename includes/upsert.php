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
 * Crea o aggiorna una AI page in modo idempotente per AI page key.
 *
 * @param array $args key, html, title?, slug?, path?, chrome?, status?, assets?
 * @return array|WP_Error  [id, url, action] oppure WP_Error.
 */
function aip_upsert_landing( array $args ) {
	$key = aip_sanitize_page_key( $args['key'] ?? '' );
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
	$path_provided = array_key_exists( 'path', $args ) || array_key_exists( 'url', $args );
	$path = null;
	if ( $path_provided ) {
		$path = aip_sanitize_path( $args['path'] ?? $args['url'] ?? '' );
	}

	$existing = aip_get_page_ids_by_key( $key );
	if ( count( $existing ) > 1 ) {
		return new WP_Error( 'aip_duplicate_key', 'Questa AI page key è già usata da più pagine.' );
	}
	$existing_id = $existing ? (int) $existing[0] : 0;
	if ( null !== $path && '' !== $path && aip_path_exists( $path, $existing_id ) ) {
		return new WP_Error( 'aip_duplicate_path', 'Questo percorso è già usato da un’altra AI page.' );
	}
	if ( ( null === $path || '' === $path ) && aip_path_exists( aip_default_path_for_slug( $slug ), $existing_id ) ) {
		return new WP_Error( 'aip_duplicate_path', 'Il percorso predefinito di questa AI page è già usato.' );
	}

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
		$postarr['ID'] = $existing_id;
		$action        = 'updated';
	} else {
		$action = 'created';
	}
	$id = aip_write_post( $postarr );

	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$asset_ids = [];

	if ( null !== $path ) {
		if ( '' === $path ) {
			delete_post_meta( $id, '_aip_custom_path' );
		} else {
			update_post_meta( $id, '_aip_custom_path', $path );
		}
	}

	if ( ! empty( $args['assets'] ) ) {
		$asset_result = aip_import_assets_for_post( $id, $html, $args['assets'] );
		if ( is_wp_error( $asset_result ) ) {
			return $asset_result;
		}

		$html = $asset_result['html'];
		$asset_ids = array_map( 'intval', $asset_result['asset_ids'] );
		update_post_meta( $id, '_aip_asset_ids', $asset_ids );
		if ( $html !== (string) ( $args['html'] ?? '' ) ) {
			$written = aip_write_post( [
				'ID'           => $id,
				'post_content' => wp_slash( $html ),
			] );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
		}
	}

	update_post_meta( $id, '_aip_page_key', $key );
	update_post_meta( $id, '_aip_chrome', $chrome );

	return [
		'id'        => $id,
		'url'       => get_permalink( $id ),
		'action'    => $action,
		'asset_ids' => $asset_ids,
	];
}

function aip_sanitize_page_key( $key ) {
	return sanitize_title( (string) $key );
}

function aip_get_page_ids_by_key( $key, $exclude_id = 0 ) {
	$key = aip_sanitize_page_key( $key );
	if ( '' === $key ) {
		return [];
	}

	$ids = get_posts( [
		'post_type'   => 'ai_page',
		'post_status' => 'any',
		'meta_key'    => '_aip_page_key',
		'meta_value'  => $key,
		'numberposts' => 2,
		'fields'      => 'ids',
	] );

	if ( ! $exclude_id ) {
		return array_map( 'intval', $ids );
	}

	$exclude_id = (int) $exclude_id;
	return array_values( array_filter( array_map( 'intval', $ids ), function ( $id ) use ( $exclude_id ) {
		return $id !== $exclude_id;
	} ) );
}

function aip_page_key_exists( $key, $exclude_id = 0 ) {
	return ! empty( aip_get_page_ids_by_key( $key, $exclude_id ) );
}
