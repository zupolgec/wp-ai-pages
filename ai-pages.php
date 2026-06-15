<?php
/**
 * Plugin Name: AI Pages
 * Description: AI page self-contained generate con l'AI come CPT, renderizzate raw con editor HTML, anteprima e pubblicazione via WP-CLI o REST a token.
 * Version: 0.5.0
 * Author: 16bit
 * Requires at least: 6.4
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: ai-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIP_FILE', __FILE__ );
define( 'AIP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIP_URL', plugin_dir_url( __FILE__ ) );
define( 'AIP_VER', '0.5.0' );

require_once AIP_DIR . 'includes/access.php';
require_once AIP_DIR . 'includes/cpt.php';
require_once AIP_DIR . 'includes/meta.php';
require_once AIP_DIR . 'includes/render.php';
require_once AIP_DIR . 'includes/assets.php';
require_once AIP_DIR . 'includes/upsert.php';
require_once AIP_DIR . 'includes/users.php';
require_once AIP_DIR . 'includes/rest.php';

if ( is_admin() ) {
	require_once AIP_DIR . 'includes/admin.php';
	require_once AIP_DIR . 'includes/settings.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once AIP_DIR . 'includes/cli.php';
}

register_activation_hook( __FILE__, function () {
	aip_register_cpt();
	update_option( 'aip_version', AIP_VER );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
