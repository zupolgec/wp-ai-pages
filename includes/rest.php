<?php
/**
 * Endpoint REST a token (per-utente): deploy di una AI page da un agent cloud,
 * senza WP-CLI né SSH. Il token identifica l'utente e i deploy sono attribuiti
 * a lui.
 *
 *   POST /wp-json/ai-pages/v1/deploy
 *   Header: Authorization: Bearer <token>   (oppure X-AIP-Token: <token>)
 *   Body JSON: { key, html, title?, slug?, chrome?, status? }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ai-pages/v1', '/deploy', [
		'methods'             => 'POST',
		'callback'            => 'aip_rest_deploy',
		'permission_callback' => 'aip_rest_check_token',
	] );
} );

function aip_extract_token( WP_REST_Request $req ) {
	$provided = (string) $req->get_header( 'x_aip_token' );
	if ( '' === $provided ) {
		$auth = (string) $req->get_header( 'authorization' );
		if ( 0 === stripos( $auth, 'bearer ' ) ) {
			$provided = trim( substr( $auth, 7 ) );
		}
	}
	return $provided;
}

function aip_rest_check_token( WP_REST_Request $req ) {
	if ( 'disabled' === aip_get_access_mode() ) {
		return new WP_Error( 'aip_deploy_disabled', 'Pubblicazione automatica disattivata.', [ 'status' => 403 ] );
	}

	$provided = aip_extract_token( $req );
	if ( '' === $provided ) {
		return new WP_Error( 'aip_no_token', 'Token mancante.', [ 'status' => 401 ] );
	}

	$user_id = aip_user_by_token( $provided );
	if ( ! $user_id ) {
		return new WP_Error( 'aip_forbidden', 'Token non valido.', [ 'status' => 401 ] );
	}

	wp_set_current_user( $user_id );
	if ( ! aip_current_user_can_deploy() ) {
		return new WP_Error( 'aip_forbidden', 'Utente senza permessi di pubblicazione.', [ 'status' => 403 ] );
	}
	return true;
}

function aip_rest_deploy( WP_REST_Request $req ) {
	$p   = (array) $req->get_json_params();
	$res = aip_upsert_landing( [
		'key'    => $p['key'] ?? '',
		'html'   => $p['html'] ?? '',
		'title'  => $p['title'] ?? null,
		'slug'   => $p['slug'] ?? null,
		'chrome' => $p['chrome'] ?? null,
		'status' => $p['status'] ?? 'publish',
		'author' => get_current_user_id(),
	] );

	if ( is_wp_error( $res ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => $res->get_error_message() ], 400 );
	}

	return new WP_REST_Response( array_merge( [ 'ok' => true ], $res ), 200 );
}
