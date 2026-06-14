<?php
/**
 * Schermata di modifica della landing: editor HTML (CodeMirror) + anteprima
 * live, metabox parametri (chrome, SEO, landing key) e salvataggio.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Asset (solo sulle schermate di edit di ai_page)
 * ---------------------------------------------------------------------- */
add_action( 'admin_enqueue_scripts', 'aip_admin_assets' );
function aip_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'ai_page' !== $screen->post_type || ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}

	wp_enqueue_style( 'aip-admin', AIP_URL . 'assets/admin.css', [], AIP_VER );

	$cm = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
	if ( false === $cm ) {
		return; // l'utente ha disattivato CodeMirror nel profilo.
	}
	wp_enqueue_script( 'aip-admin', AIP_URL . 'assets/admin.js', [ 'jquery', 'code-editor' ], AIP_VER, true );
	wp_localize_script( 'aip-admin', 'AIP_CM', $cm );
}

/* -------------------------------------------------------------------------
 * Editor HTML + anteprima, a tutta larghezza sotto il titolo
 * ---------------------------------------------------------------------- */
add_action( 'edit_form_after_title', 'aip_edit_form_editor' );
function aip_edit_form_editor( $post ) {
	if ( 'ai_page' !== $post->post_type ) {
		return;
	}
	wp_nonce_field( 'aip_save', 'aip_nonce' );
	?>
	<div class="aip-edit" id="aip-edit">
		<div class="aip-pane aip-html-pane">
			<div class="aip-pane-head">HTML</div>
			<textarea id="aip-html" name="aip_html" class="aip-html"><?php echo esc_textarea( $post->post_content ); ?></textarea>
		</div>
		<div class="aip-pane aip-preview-pane">
			<div class="aip-pane-head">
				<strong>Anteprima</strong>
				<span class="aip-bp" role="group" aria-label="Breakpoint">
					<button type="button" class="button button-small" data-w="375" title="Mobile 375px">Mobile</button>
					<button type="button" class="button button-small" data-w="768" title="Tablet 768px">Tablet</button>
					<button type="button" class="button button-small" data-w="1280" title="Desktop 1280px">Desktop</button>
					<button type="button" class="button button-small active" data-w="full" title="Tutta la larghezza">Full</button>
				</span>
				<span class="aip-pane-head-right">
					<button type="button" class="button button-small" id="aip-pick" title="Clicca un elemento nell'anteprima per evidenziarlo nel codice">&#9737; Seleziona</button>
					<button type="button" class="button button-small" id="aip-fs" title="Anteprima a schermo intero" aria-label="Schermo intero">&#9974;</button>
					<button type="button" class="button button-small" id="aip-refresh" title="Aggiorna anteprima">&#8635;</button>
				</span>
			</div>
			<div class="aip-preview-wrap"><iframe id="aip-preview" title="Anteprima landing"></iframe></div>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Metabox parametri (colonna laterale)
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes_ai_page', function () {
	add_meta_box( 'aip-params', 'Parametri', 'aip_params_metabox', 'ai_page', 'side', 'high' );
} );

function aip_params_metabox( $post ) {
	$chrome = get_post_meta( $post->ID, '_aip_chrome', true ) ?: get_option( 'aip_default_chrome', 'full' );
	$sc     = get_post_meta( $post->ID, '_aip_shortcodes', true );
	$key    = get_post_meta( $post->ID, '_aip_landing_key', true );

	$modes = [
		'none' => 'Pagina pulita (solo il tuo HTML)',
		'site' => 'Con header e footer del sito',
		'full' => 'Documento HTML completo',
	];
	?>
	<p>
		<label for="aip-chrome"><strong>Tipo di pagina</strong></label><br>
		<select id="aip-chrome" name="aip_chrome" style="width:100%">
			<?php foreach ( $modes as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $chrome, $val ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label><input type="checkbox" name="aip_shortcodes" value="1" <?php checked( $sc, '1' ); ?>> <strong>Esegui shortcode</strong></label><br>
		<span class="description">Esegue gli shortcode di WordPress presenti nel contenuto (es. moduli, gallerie). Lascialo spento se non li usi.</span>
	</p>
	<p>
		<label for="aip-key"><strong>Chiave landing</strong></label><br>
		<input type="text" id="aip-key" name="aip_landing_key" value="<?php echo esc_attr( $key ); ?>" style="width:100%" placeholder="(default: slug)">
		<span class="description">Identifica la pagina: ripubblicandola con la stessa chiave viene aggiornata invece di duplicata.</span>
	</p>
	<?php
}

/* -------------------------------------------------------------------------
 * Salvataggio
 * ---------------------------------------------------------------------- */
add_action( 'save_post_ai_page', 'aip_save_post', 10, 2 );
function aip_save_post( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['aip_nonce'] ) || ! wp_verify_nonce( $_POST['aip_nonce'], 'aip_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Meta.
	if ( isset( $_POST['aip_chrome'] ) ) {
		$chrome = in_array( $_POST['aip_chrome'], [ 'none', 'site', 'full' ], true ) ? $_POST['aip_chrome'] : 'none';
		update_post_meta( $post_id, '_aip_chrome', $chrome );
	}
	update_post_meta( $post_id, '_aip_shortcodes', empty( $_POST['aip_shortcodes'] ) ? '' : '1' );
	$key = sanitize_text_field( wp_unslash( $_POST['aip_landing_key'] ?? '' ) );
	if ( '' === $key ) {
		$key = $post->post_name ?: ( 'lp-' . $post_id );
	}
	update_post_meta( $post_id, '_aip_landing_key', $key );

	// Contenuto HTML: scritto in post_content evitando la ricorsione.
	if ( isset( $_POST['aip_html'] ) ) {
		remove_action( 'save_post_ai_page', 'aip_save_post', 10 );
		aip_write_post( [
			'ID'           => $post_id,
			'post_content' => $_POST['aip_html'], // già slashato da $_POST.
		] );
		add_action( 'save_post_ai_page', 'aip_save_post', 10, 2 );
	}
}
