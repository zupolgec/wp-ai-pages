<?php
/**
 * Access rules shared by admin UI, REST deploy and the ai_page post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aip_get_access_mode() {
	$mode = (string) get_option( 'aip_access_mode', 'disabled' );
	return in_array( $mode, [ 'disabled', 'admin', 'editor' ], true ) ? $mode : 'disabled';
}

function aip_capability_for_access_mode( $mode = null ) {
	$mode = $mode ?? aip_get_access_mode();
	return 'editor' === $mode ? 'edit_others_posts' : 'manage_options';
}

function aip_editor_capability() {
	return aip_capability_for_access_mode( 'editor' === aip_get_access_mode() ? 'editor' : 'admin' );
}

function aip_current_user_can_edit_ai_pages() {
	return current_user_can( aip_editor_capability() );
}

function aip_current_user_can_deploy() {
	$mode = aip_get_access_mode();
	if ( 'disabled' === $mode ) {
		return false;
	}

	return current_user_can( aip_capability_for_access_mode( $mode ) );
}

function aip_user_can_deploy( $user ) {
	$mode = aip_get_access_mode();
	if ( 'disabled' === $mode ) {
		return false;
	}

	return user_can( $user, aip_capability_for_access_mode( $mode ) );
}

function aip_ai_page_primitive_caps() {
	return [
		'edit_ai_pages',
		'edit_others_ai_pages',
		'publish_ai_pages',
		'read_private_ai_pages',
		'delete_ai_pages',
		'delete_private_ai_pages',
		'delete_published_ai_pages',
		'delete_others_ai_pages',
		'edit_private_ai_pages',
		'edit_published_ai_pages',
	];
}

add_filter( 'user_has_cap', function ( $allcaps ) {
	$mode = aip_get_access_mode();
	$can_edit = ! empty( $allcaps['manage_options'] );
	if ( 'editor' === $mode && ! empty( $allcaps['edit_others_posts'] ) ) {
		$can_edit = true;
	}

	if ( ! $can_edit ) {
		return $allcaps;
	}

	foreach ( aip_ai_page_primitive_caps() as $cap ) {
		$allcaps[ $cap ] = true;
	}

	return $allcaps;
} );
