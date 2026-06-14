<?php
/**
 * Blank-canvas template. Renderizza il post_content RAW (niente the_content,
 * niente wpautop, niente shortcode). Con chrome=site avvolge header/footer
 * del tema; con chrome=none (default) e' un documento standalone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aip_chrome  = get_post_meta( get_the_ID(), '_aip_chrome', true ) ?: 'none';
$aip_content = aip_get_content( get_the_ID() ); // raw dal DB (+ do_shortcode se attivo)

if ( $aip_chrome === 'full' ) {
	// Documento HTML completo: emesso verbatim, niente shell ne' tema.
	echo $aip_content; // phpcs:ignore WordPress.Security.EscapeOutput -- contenuto fidato
	return;
}

if ( $aip_chrome === 'site' ) {
	get_header();
	echo $aip_content; // phpcs:ignore WordPress.Security.EscapeOutput -- contenuto fidato (solo redazione interna)
	get_footer();
	return;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php aip_head(); ?>
</head>
<body>
<?php echo $aip_content; // phpcs:ignore WordPress.Security.EscapeOutput -- contenuto fidato (solo redazione interna) ?>
<?php aip_footer(); ?>
</body>
</html>
