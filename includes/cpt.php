<?php
/**
 * Custom post type ai_page. Niente editor a blocchi: l'HTML si
 * modifica dal nostro editor con anteprima.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'aip_register_cpt' );
function aip_register_cpt() {
	$prefix = aip_sanitize_prefix( get_option( 'aip_prefix', 'pages' ) );
	$prefix = '' !== $prefix ? $prefix : 'pages';
	$rewrite = '/' === $prefix ? false : [ 'slug' => $prefix, 'with_front' => false ];

	register_post_type( 'ai_page', [
		'label'           => 'AI Pages',
		'labels'          => [
			'name'          => 'AI Pages',
			'singular_name' => 'AI Page',
			'add_new_item'  => 'Aggiungi AI page',
			'edit_item'     => 'Modifica AI page',
			'menu_name'     => 'AI Pages',
		],
		'public'          => true,
		'show_in_rest'    => true,
		'menu_icon'       => 'dashicons-welcome-widgets-menus',
		'supports'        => [ 'title', 'revisions' ],
		'rewrite'         => $rewrite,
		'has_archive'     => false,
		'capability_type' => [ 'ai_page', 'ai_pages' ],
		'map_meta_cap'    => true,
	] );

	add_rewrite_rule( '^(.+?)/?$', 'index.php?aip_path=$matches[1]', 'bottom' );
}

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'aip_path';
	return $vars;
} );

add_filter( 'request', function ( $query_vars ) {
	$path = '';
	if ( ! empty( $query_vars['aip_path'] ) ) {
		$path = $query_vars['aip_path'];
	} elseif ( ! empty( $query_vars['pagename'] ) && ! get_page_by_path( $query_vars['pagename'], OBJECT, 'page' ) ) {
		$path = $query_vars['pagename'];
	} elseif ( ! empty( $query_vars['name'] ) && ! get_page_by_path( $query_vars['name'], OBJECT, 'post' ) ) {
		$path = $query_vars['name'];
	} elseif ( ! empty( $query_vars['attachment'] ) && ! get_page_by_path( $query_vars['attachment'], OBJECT, 'attachment' ) ) {
		$path = aip_current_request_path();
	}

	if ( '' === $path ) {
		return $query_vars;
	}

	$post_id = aip_post_id_by_path( $path );
	if ( ! $post_id ) {
		if ( empty( $query_vars['aip_path'] ) ) {
			return $query_vars;
		}
		return [ 'error' => '404' ];
	}

	return [
		'p'         => $post_id,
		'post_type' => 'ai_page',
	];
} );

function aip_current_request_path() {
	$request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$home_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	$request_path = is_string( $request_path ) ? $request_path : '';
	$home_path    = is_string( $home_path ) ? $home_path : '/';

	if ( '/' !== $home_path && 0 === strpos( $request_path, $home_path ) ) {
		$request_path = substr( $request_path, strlen( $home_path ) );
	}

	return aip_sanitize_path( $request_path );
}

add_filter( 'post_type_link', function ( $permalink, $post ) {
	if ( ! $post || 'ai_page' !== $post->post_type ) {
		return $permalink;
	}

	$path = aip_get_post_path( $post );
	if ( '' === $path ) {
		return $permalink;
	}

	return home_url( user_trailingslashit( $path ) );
}, 10, 2 );

function aip_sanitize_prefix( $prefix ) {
	$prefix = trim( (string) $prefix );
	return sanitize_title( trim( $prefix, '/' ) );
}

function aip_sanitize_path( $path ) {
	$path = trim( (string) $path );
	if ( '' === $path ) {
		return '';
	}

	$parsed_path = wp_parse_url( $path, PHP_URL_PATH );
	if ( is_string( $parsed_path ) && '' !== $parsed_path ) {
		$path = $parsed_path;
	}

	$path     = trim( str_replace( '\\', '/', $path ), '/' );
	$segments = array_filter( explode( '/', $path ), 'strlen' );
	$segments = array_map( 'sanitize_title', $segments );
	$segments = array_filter( $segments, 'strlen' );

	return implode( '/', $segments );
}

function aip_get_post_path( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'ai_page' !== $post->post_type ) {
		return '';
	}

	$custom_path = aip_sanitize_path( get_post_meta( $post->ID, '_aip_custom_path', true ) );
	if ( '' !== $custom_path ) {
		return $custom_path;
	}

	$slug   = sanitize_title( $post->post_name ?: $post->post_title );

	return aip_default_path_for_slug( $slug );
}

function aip_default_path_for_slug( $slug ) {
	$prefix = aip_sanitize_prefix( get_option( 'aip_prefix', 'pages' ) );
	$prefix = '' !== $prefix ? $prefix : 'pages';
	$slug   = sanitize_title( $slug );

	return trim( $prefix . '/' . $slug, '/' );
}

function aip_post_id_by_path( $path, $post_status = 'publish' ) {
	$path = aip_sanitize_path( $path );
	if ( '' === $path ) {
		return 0;
	}

	$custom = get_posts( [
		'post_type'   => 'ai_page',
		'post_status' => $post_status,
		'meta_key'    => '_aip_custom_path',
		'meta_value'  => $path,
		'numberposts' => 1,
		'fields'      => 'ids',
	] );
	if ( $custom ) {
		return (int) $custom[0];
	}

	$prefix = aip_sanitize_prefix( get_option( 'aip_prefix', 'pages' ) );
	$prefix = '' !== $prefix ? $prefix : 'pages';
	$slug   = '';

	if ( 0 === strpos( $path . '/', $prefix . '/' ) ) {
		$slug = substr( $path, strlen( $prefix ) + 1 );
		if ( false !== strpos( $slug, '/' ) ) {
			return 0;
		}
	}

	if ( '' === $slug ) {
		return 0;
	}

	$post = get_page_by_path( $slug, OBJECT, 'ai_page' );
	if ( ! $post ) {
		return 0;
	}

	if ( 'any' !== $post_status ) {
		$allowed = (array) $post_status;
		if ( ! in_array( $post->post_status, $allowed, true ) ) {
			return 0;
		}
	}

	return (int) $post->ID;
}

function aip_path_exists( $path, $exclude_id = 0 ) {
	$post_id = aip_post_id_by_path( $path, 'any' );
	if ( $post_id && (int) $post_id !== (int) $exclude_id ) {
		return true;
	}

	$existing = get_page_by_path( aip_sanitize_path( $path ), OBJECT, [ 'page', 'post' ] );
	return (bool) $existing;
}

/**
 * Rigenera le rewrite rules dopo un cambio di prefisso (flag impostato dalle
 * Impostazioni), quando il CPT è già registrato col nuovo prefisso.
 */
add_action( 'init', function () {
	if ( get_option( 'aip_version' ) !== AIP_VER ) {
		update_option( 'aip_version', AIP_VER );
		update_option( 'aip_flush_rewrite', 1 );
	}

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
