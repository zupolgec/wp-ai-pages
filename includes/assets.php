<?php
/**
 * Media Library asset import for AI page deploys.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aip_allowed_asset_mimes() {
	return [
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'gif'  => 'image/gif',
	];
}

function aip_asset_size_limit() {
	return 10 * 1024 * 1024;
}

function aip_decode_asset_data( $data ) {
	$data = trim( (string) $data );
	if ( '' === $data ) {
		return new WP_Error( 'aip_asset_empty_data', 'Un asset non contiene dati.' );
	}

	if ( preg_match( '#^data:([^;,]+)?;base64,(.+)$#s', $data, $matches ) ) {
		$data = $matches[2];
	}

	$data  = preg_replace( '/\s+/', '', $data );
	$bytes = base64_decode( $data, true );
	if ( false === $bytes ) {
		return new WP_Error( 'aip_asset_invalid_base64', 'Un asset contiene dati base64 non validi.' );
	}

	if ( strlen( $bytes ) > aip_asset_size_limit() ) {
		return new WP_Error( 'aip_asset_too_large', 'Un asset supera il limite di 10 MB.' );
	}

	return $bytes;
}

function aip_validate_asset_payload( $asset ) {
	if ( ! is_array( $asset ) ) {
		return new WP_Error( 'aip_asset_invalid', 'Ogni asset deve essere un oggetto.' );
	}

	$name = sanitize_file_name( (string) ( $asset['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'aip_asset_missing_name', 'Ogni asset deve avere un nome file.' );
	}

	$filetype = wp_check_filetype( $name, aip_allowed_asset_mimes() );
	if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
		return new WP_Error( 'aip_asset_invalid_type', 'Tipo asset non supportato: ' . $name );
	}

	$bytes = aip_decode_asset_data( $asset['data'] ?? '' );
	if ( is_wp_error( $bytes ) ) {
		return $bytes;
	}

	return [
		'name'  => $name,
		'type'  => $filetype['type'],
		'bytes' => $bytes,
		'hash'  => hash( 'sha256', $bytes ),
		'alt'   => sanitize_text_field( $asset['alt'] ?? '' ),
		'title' => sanitize_text_field( $asset['title'] ?? pathinfo( $name, PATHINFO_FILENAME ) ),
	];
}

/**
 * Cerca un attachment già importato con lo stesso contenuto (hash), così lo
 * stesso media caricato da più deploy non viene duplicato nella Libreria.
 */
function aip_find_asset_by_hash( $hash ) {
	$hash = (string) $hash;
	if ( '' === $hash ) {
		return 0;
	}

	$existing = get_posts( [
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_key'    => '_aip_asset_hash',
		'meta_value'  => $hash,
		'numberposts' => 1,
		'fields'      => 'ids',
	] );

	return $existing ? (int) $existing[0] : 0;
}

function aip_import_assets_for_post( $post_id, $html, $assets ) {
	if ( empty( $assets ) ) {
		return [
			'html'      => $html,
			'asset_ids' => [],
		];
	}

	if ( ! is_array( $assets ) ) {
		return new WP_Error( 'aip_assets_invalid', 'Il campo assets deve essere una lista.' );
	}

	$prepared = [];
	foreach ( $assets as $asset ) {
		$validated = aip_validate_asset_payload( $asset );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$prepared[] = $validated;
	}

	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	$asset_ids = [];
	foreach ( $prepared as $asset ) {
		$attachment_id = aip_get_or_sideload_asset( $asset, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( $url ) {
			$html = aip_replace_asset_placeholders( $html, $asset['name'], $url );
		}

		$asset_ids[] = (int) $attachment_id;
	}

	return [
		'html'      => $html,
		'asset_ids' => array_values( array_unique( $asset_ids ) ),
	];
}

/**
 * Riusa un attachment con lo stesso contenuto se esiste, altrimenti carica il
 * file nella Libreria media e ne registra l'hash per i deploy successivi.
 *
 * @return int|WP_Error
 */
function aip_get_or_sideload_asset( $asset, $post_id ) {
	$existing_id = aip_find_asset_by_hash( $asset['hash'] );
	if ( $existing_id ) {
		return $existing_id;
	}

	$tmp = wp_tempnam( $asset['name'] );
	if ( ! $tmp ) {
		return new WP_Error( 'aip_asset_temp_failed', 'Impossibile preparare un file temporaneo.' );
	}

	$result = file_put_contents( $tmp, $asset['bytes'] );
	if ( false === $result ) {
		@unlink( $tmp );
		return new WP_Error( 'aip_asset_write_failed', 'Impossibile salvare un asset temporaneo.' );
	}

	$checked = wp_check_filetype_and_ext( $tmp, $asset['name'], aip_allowed_asset_mimes() );
	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
		@unlink( $tmp );
		return new WP_Error( 'aip_asset_invalid_file', 'Un asset non supera la validazione del file.' );
	}

	$file = [
		'name'     => $asset['name'],
		'tmp_name' => $tmp,
		'type'     => $checked['type'],
		'size'     => strlen( $asset['bytes'] ),
	];

	$attachment_id = media_handle_sideload( $file, $post_id, $asset['title'] );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return $attachment_id;
	}

	update_post_meta( $attachment_id, '_aip_asset_hash', $asset['hash'] );
	if ( '' !== $asset['alt'] ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $asset['alt'] );
	}

	return (int) $attachment_id;
}

function aip_replace_asset_placeholders( $html, $name, $url ) {
	$placeholders = array_unique( [
		'asset://' . $name,
		'asset://' . rawurlencode( $name ),
	] );

	return str_replace( $placeholders, $url, $html );
}
