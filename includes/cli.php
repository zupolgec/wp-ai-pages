<?php
/**
 * Comando WP-CLI: `wp ai-page upsert` (deploy idempotente da file/STDIN).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIP_CLI {

	/**
	 * Crea o aggiorna una AI page da un file HTML (idempotente per AI page key).
	 *
	 * ## OPTIONS
	 *
	 * --key=<key>
	 * : Chiave univoca della AI page. Una pagina con questa chiave viene aggiornata.
	 *
	 * [--file=<path>]
	 * : Path al file HTML self-contained. Se omesso legge da STDIN.
	 *
	 * [--title=<title>]
	 * : Titolo della page.
	 *
		 * [--slug=<slug>]
		 * : Slug URL (default: la key).
		 *
		 * [--url-path=<path>]
		 * : Percorso URL personalizzato, es. /promo/nome-pagina.
		 *
		 * [--assets=<path>]
		 * : Path a un file JSON con la lista assets per i placeholder asset://.
	 *
	 * [--chrome=<chrome>]
	 * : none|site|full. Default: il tipo di pagina predefinito (Impostazioni).
	 *
	 * [--status=<status>]
	 * : publish|draft. Default: publish.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-page upsert --key=black-friday --file=./bf.html --title="Black Friday" --chrome=full
	 *     cat bf.html | wp ai-page upsert --key=black-friday
	 *
	 * @when after_wp_load
	 */
	public function upsert( $args, $assoc ) {
		if ( ! empty( $assoc['file'] ) ) {
			if ( ! file_exists( $assoc['file'] ) ) {
				WP_CLI::error( "File non trovato: {$assoc['file']}" );
			}
			$html = file_get_contents( $assoc['file'] );
		} else {
			$html = stream_get_contents( STDIN );
		}

		$assets = [];
		if ( ! empty( $assoc['assets'] ) ) {
			if ( ! file_exists( $assoc['assets'] ) ) {
				WP_CLI::error( "File assets non trovato: {$assoc['assets']}" );
			}
			$decoded = json_decode( file_get_contents( $assoc['assets'] ), true );
			if ( ! is_array( $decoded ) ) {
				WP_CLI::error( 'Il file assets deve contenere JSON valido.' );
			}
			$assets = $decoded['assets'] ?? $decoded;
		}

		$res = aip_upsert_landing( [
			'key'    => $assoc['key'] ?? '',
			'html'   => $html,
			'title'  => $assoc['title'] ?? null,
			'slug'   => $assoc['slug'] ?? null,
			'path'   => $assoc['url-path'] ?? null,
			'chrome' => $assoc['chrome'] ?? null,
			'status' => $assoc['status'] ?? 'publish',
			'assets' => $assets,
		] );

		if ( is_wp_error( $res ) ) {
			WP_CLI::error( $res->get_error_message() );
		}

		$verb = 'created' === $res['action'] ? 'Creata' : 'Aggiornata';
		WP_CLI::success( sprintf( '%s ai_page #%d → %s', $verb, $res['id'], $res['url'] ) );
	}
}

WP_CLI::add_command( 'ai-page', 'AIP_CLI' );
