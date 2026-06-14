<?php
/**
 * Pagina Impostazioni: prompt pronto per l'AI, token deploy (per-utente),
 * SEO mode, GTM, chrome di default e documentazione dei workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=ai_page',
		'AI Pages: Impostazioni',
		'Impostazioni',
		'manage_options',
		'aip-settings',
		'aip_render_settings_page'
	);
} );

add_action( 'admin_post_aip_save_settings', 'aip_handle_save_settings' );
function aip_handle_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permessi insufficienti.' );
	}
	check_admin_referer( 'aip_settings' );

	$old_prefix = (string) get_option( 'aip_prefix', 'lp' );
	$new_prefix = sanitize_title( wp_unslash( $_POST['aip_prefix'] ?? '' ) );
	update_option( 'aip_prefix', $new_prefix );
	if ( $new_prefix !== $old_prefix ) {
		update_option( 'aip_flush_rewrite', 1 );
	}

	$chrome = $_POST['aip_default_chrome'] ?? 'none';
	update_option( 'aip_default_chrome', in_array( $chrome, [ 'none', 'site', 'full' ], true ) ? $chrome : 'none' );

	update_option( 'aip_site_head', empty( $_POST['aip_site_head'] ) ? '' : '1' );
	update_option( 'aip_head_snippet', wp_unslash( $_POST['aip_head_snippet'] ?? '' ) );
	update_option( 'aip_body_snippet', wp_unslash( $_POST['aip_body_snippet'] ?? '' ) );

	if ( ! empty( $_POST['aip_regen_token'] ) ) {
		update_user_meta( get_current_user_id(), 'aip_api_token', aip_generate_token() );
	}

	wp_safe_redirect( add_query_arg(
		[ 'post_type' => 'ai_page', 'page' => 'aip-settings', 'updated' => '1' ],
		admin_url( 'edit.php' )
	) );
	exit;
}

function aip_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$token     = aip_get_user_token( get_current_user_id() );
	$prefix    = get_option( 'aip_prefix', 'lp' );
	$chrome    = get_option( 'aip_default_chrome', 'full' );
	$site_head = get_option( 'aip_site_head' );
	$head_snip = (string) get_option( 'aip_head_snippet', '' );
	$body_snip = (string) get_option( 'aip_body_snippet', '' );
	$endpoint  = home_url( '/wp-json/ai-pages/v1/deploy' );
	$action    = admin_url( 'admin-post.php' );
	$site      = home_url( '/' );

	$prompt = aip_agent_prompt( $endpoint, $token, $site );
	$dark   = 'background:#1e1e2e;color:#e7ecf5;padding:14px;border-radius:6px;overflow:auto;white-space:pre-wrap';
	?>
	<div class="wrap">
		<h1>AI Pages: Impostazioni</h1>

		<?php if ( ! empty( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="action" value="aip_save_settings">
			<?php wp_nonce_field( 'aip_settings' ); ?>

			<h2>Configurazione</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="aip_api_token">Il tuo token deploy</label></th>
					<td>
						<input type="text" id="aip_api_token" value="<?php echo esc_attr( $token ); ?>" readonly class="regular-text code" style="width:30em" onclick="this.select()">
						<p><label><input type="checkbox" name="aip_regen_token" value="1"> Rigenera il mio token al salvataggio</label></p>
						<p class="description">Token personale: i deploy via REST sono attribuiti a te. Ogni utente ha il suo (anche nel profilo).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aip_prefix">Indirizzo delle landing</label></th>
					<td>
						<?php $home = untrailingslashit( home_url() ); ?>
						<code><?php echo esc_html( $home ); ?>/</code><input type="text" id="aip_prefix" name="aip_prefix" value="<?php echo esc_attr( $prefix ); ?>" style="width:10em" placeholder="lp"><code>/nome-pagina</code>
						<p class="description">Cartella sotto cui vivono le landing. Vuoto = alla radice del sito: sconsigliato, può confliggere con pagine e articoli con lo stesso indirizzo.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aip_default_chrome">Tipo di pagina predefinito</label></th>
					<td>
						<select id="aip_default_chrome" name="aip_default_chrome">
							<option value="none" <?php selected( $chrome, 'none' ); ?>>Pagina pulita (solo il tuo HTML)</option>
							<option value="site" <?php selected( $chrome, 'site' ); ?>>Con header e footer del sito</option>
							<option value="full" <?php selected( $chrome, 'full' ); ?>>Documento HTML completo</option>
						</select>
						<p class="description">Valore proposto per le nuove landing.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Head e footer del sito</th>
					<td>
						<label><input type="checkbox" name="aip_site_head" value="1" <?php checked( $site_head, '1' ); ?>> Esegui nelle pagine pulite gli script che il sito aggiunge nell'head e a fine pagina</label>
						<p class="description">Attivalo se vuoi che il plugin SEO (Slim SEO, Yoast) e gli altri plugin che inseriscono codice nell'head funzionino anche nelle pagine pulite. Spento = pagine più leggere.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aip_head_snippet">Codice nell'head</label></th>
					<td>
						<textarea id="aip_head_snippet" name="aip_head_snippet" rows="4" class="large-text code" placeholder="<!-- es. tag manager, pixel, font, meta di verifica -->"><?php echo esc_textarea( $head_snip ); ?></textarea>
						<p class="description">Inserito nell'head di ogni pagina pulita. Mettici quello che ti serve: tag manager, pixel, font, codici di verifica.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aip_body_snippet">Codice a fine pagina</label></th>
					<td>
						<textarea id="aip_body_snippet" name="aip_body_snippet" rows="4" class="large-text code" placeholder="<!-- es. widget chat, script di analytics -->"><?php echo esc_textarea( $body_snip ); ?></textarea>
						<p class="description">Inserito appena prima della chiusura di ogni pagina pulita.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Salva impostazioni' ); ?>
		</form>

		<hr>

		<h2>Prompt per l'agent AI</h2>
		<p>Incollalo nel tuo agent (cloud o locale): contiene già endpoint e token per pubblicare landing in autonomia.</p>
		<textarea id="aip-prompt" readonly rows="16" style="width:100%;font-family:Menlo,Consolas,monospace;font-size:12px;<?php echo esc_attr( $dark ); ?>"><?php echo esc_textarea( $prompt ); ?></textarea>
		<p>
			<button type="button" class="button" onclick="var t=document.getElementById('aip-prompt');t.select();document.execCommand('copy');this.textContent='Copiato!';">Copia prompt</button>
			<span class="description">Il prompt include il tuo token personale: i deploy risulteranno fatti da te.</span>
		</p>

		<hr>

		<h2>Le tre modalità chrome</h2>
		<p>Il "chrome" decide quanta cornice del sito avvolge la landing. Si sceglie per ogni pagina (e c'è un default qui sopra).</p>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Modalità</th><th>Cosa produce</th><th>Quando usarla</th></tr></thead>
			<tbody>
				<tr>
					<td><code>none</code></td>
					<td>Pagina autonoma: il tuo HTML più gli eventuali snippet head/footer impostati qui sopra. Nessun header o footer del tema.</td>
					<td>Default. Quando generi solo il contenuto della pagina. Massima pulizia, zero CSS del tema.</td>
				</tr>
				<tr>
					<td><code>site</code></td>
					<td>La landing è avvolta da <strong>header e footer del tema</strong> (menu, logo, footer) e i plugin SEO funzionano come nel resto del sito.</td>
					<td>Quando la landing deve sembrare parte del sito e mantenere navigazione e brand.</td>
				</tr>
				<tr>
					<td><code>full</code></td>
					<td>Il tuo HTML viene servito <strong>verbatim</strong>, dal <code>&lt;!doctype&gt;</code> al <code>&lt;/html&gt;</code>. Il plugin non aggiunge nulla: head, SEO, script li controlli tu.</td>
					<td>Quando l'AI genera un documento HTML completo (es. con Tailwind da CDN). Fedelta' totale all'output, indipendente dal tema.</td>
				</tr>
			</tbody>
		</table>

		<h2>Workflow disponibili</h2>
		<p>Una landing è un file HTML self-contained. La <strong>landing key</strong> la identifica: ripubblicare con la stessa key aggiorna invece di duplicare.</p>

		<h3>1. Deploy da agent cloud (REST a token)</h3>
		<pre style="<?php echo esc_attr( $dark ); ?>">curl -X POST <?php echo esc_html( $endpoint ); ?> \
  -H "Authorization: Bearer <?php echo esc_html( $token ); ?>" \
  -H "Content-Type: application/json" \
  -d '{ "key": "black-friday", "title": "Black Friday", "chrome": "full",
        "status": "publish", "html": "&lt;!doctype html&gt;...&lt;/html&gt;" }'</pre>
		<p class="description">Campi: <code>key</code> e <code>html</code> obbligatori; <code>title</code>, <code>slug</code>, <code>chrome</code>, <code>status</code> opzionali. Risposta: <code>{ ok, id, url, action }</code>.</p>

		<h3>2. Deploy da riga di comando (WP-CLI)</h3>
		<pre style="<?php echo esc_attr( $dark ); ?>">wp ai-page upsert --key=black-friday --file=./bf.html --chrome=full
cat bf.html | wp ai-page upsert --key=black-friday</pre>
		<p class="description">Tieni i file <code>.html</code> in git per diff, review e rollback.</p>

		<h3>3. Editing manuale</h3>
		<p>Da <strong>AI Pages &rarr; Aggiungi</strong>: editor con syntax highlighting, anteprima live (con breakpoint e schermo intero) e versioning via revisioni di WordPress.</p>
	</div>
	<?php
}

/**
 * Prompt pronto da incollare in un agent AI.
 */
function aip_agent_prompt( $endpoint, $token, $site ) {
	return <<<PROMPT
Sei un assistente che crea e pubblica landing page sul sito {$site}.

Per pubblicare una landing fai UNA sola richiesta HTTP:

POST {$endpoint}
Header: Authorization: Bearer {$token}
Header: Content-Type: application/json
Body JSON: {
  "key": "<slug-univoco-della-landing>",
  "title": "<titolo>",
  "chrome": "full",
  "status": "publish",
  "html": "<documento html completo>"
}

Regole per l'HTML:
- Deve essere self-contained: CSS e JS inline, nessun file locale. Font e librerie solo da CDN.
- chrome "full": generi un documento HTML completo (<!doctype html> ... </html>). chrome "none": generi solo il contenuto del <body> (il sito aggiunge una struttura minima). chrome "site": la landing viene avvolta da header e footer del tema.
- "key" identifica la landing: riusare la stessa key AGGIORNA la pagina invece di crearne una nuova.
- In italiano non scrivere tutto a iniziali maiuscole (titolo della pagina, non Titolo Della Pagina).

La risposta è JSON: { ok, id, url, action }. Comunica all'utente l'url pubblicato.
PROMPT;
}
