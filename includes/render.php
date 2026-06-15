<?php
/**
 * Render: dirotta le ai_page sul template blank-canvas e fornisce
 * gli helper per l'head condiviso (SEO/OG/GTM).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Le AI page sono host-agnostiche: niente redirect canonico verso siteurl,
// così funzionano dietro un tunnel (expose) o un dominio diverso.
add_action( 'template_redirect', function () {
	if ( is_singular( 'ai_page' ) ) {
		remove_action( 'template_redirect', 'redirect_canonical' );
	}
}, 0 );

add_filter( 'redirect_canonical', function ( $redirect_url, $requested_url ) {
	if ( ! $redirect_url || is_preview() ) {
		return $redirect_url;
	}

	$redirect_path  = aip_sanitize_path( wp_parse_url( $redirect_url, PHP_URL_PATH ) );
	$requested_path = aip_sanitize_path( wp_parse_url( $requested_url, PHP_URL_PATH ) );
	$post_id        = aip_post_id_by_path( $redirect_path, 'any' );
	if ( ! $post_id ) {
		return $redirect_url;
	}

	$expected_path = aip_get_post_path( $post_id );
	if ( '' !== $requested_path && '' !== $expected_path && $requested_path !== $expected_path ) {
		return false;
	}

	return $redirect_url;
}, 10, 2 );

add_action( 'template_redirect', function () {
	if ( ! is_singular( 'ai_page' ) || is_preview() ) {
		return;
	}

	$current_path  = aip_current_request_path();
	$expected_path = aip_get_post_path( get_queried_object_id() );
	if ( '' === $current_path || '' === $expected_path || $current_path === $expected_path ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 1 );

add_filter( 'template_include', 'aip_template_include' );
function aip_template_include( $template ) {
	if ( is_singular( 'ai_page' ) ) {
		return AIP_DIR . 'templates/canvas.php';
	}
	return $template;
}

/**
 * Contenuto della AI page. Echo raw per default; se la pagina ha gli shortcode
 * attivi applica solo do_shortcode (niente wpautop/texturize), così l'HTML
 * resta fedele ma gli shortcode registrati vengono eseguiti.
 */
function aip_get_content( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );
	if ( get_post_meta( $post_id, '_aip_shortcodes', true ) ) {
		$content = do_shortcode( $content );
	}
	return $content;
}

function aip_meta( $key, $default = '' ) {
	$v = get_post_meta( get_the_ID(), $key, true );
	return ( $v !== '' && $v !== false ) ? $v : $default;
}

/**
 * Head per il chrome "none". Se l'opzione "head del sito" è attiva esegue
 * wp_head() (così i plugin SEO/analytics iniettano i loro tag), altrimenti
 * mette solo il titolo della pagina. In coda lo snippet head personalizzato.
 */
function aip_head() {
	if ( get_option( 'aip_site_head' ) ) {
		wp_head();
	} else {
		echo '<title>' . esc_html( get_the_title() ) . "</title>\n";
	}
	$snippet = (string) get_option( 'aip_head_snippet', '' );
	if ( '' !== trim( $snippet ) ) {
		echo $snippet . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- snippet fidato dell'admin
	}
}

/**
 * Footer per il chrome "none": snippet body personalizzato e, se attivo,
 * wp_footer() per gli script che si agganciano alla chiusura della pagina.
 */
function aip_footer() {
	$snippet = (string) get_option( 'aip_body_snippet', '' );
	if ( '' !== trim( $snippet ) ) {
		echo $snippet . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- snippet fidato dell'admin
	}
	if ( get_option( 'aip_site_head' ) ) {
		wp_footer();
	}
}
