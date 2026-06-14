<?php
/**
 * Plugin Name: AI Pages
 * Description: Landing self-contained generate via AI come CPT, renderizzate raw da un template blank-canvas, con editor HTML + anteprima, deploy via WP-CLI e via REST a token.
 * Version: 0.4.0
 * Author: 16bit
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIP_FILE', __FILE__ );
define( 'AIP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIP_URL', plugin_dir_url( __FILE__ ) );
define( 'AIP_VER', '0.4.0' );

require_once AIP_DIR . 'includes/cpt.php';
require_once AIP_DIR . 'includes/meta.php';
require_once AIP_DIR . 'includes/render.php';
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
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
