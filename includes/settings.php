<?php
/**
 * Pagina Impostazioni: prompt pronto per l'AI, token per utente,
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

	$raw_prefix = isset( $_POST['aip_prefix'] ) ? trim( wp_unslash( $_POST['aip_prefix'] ) ) : '';
	$new_prefix = sanitize_title( $raw_prefix );
	if ( '' === $new_prefix ) {
		wp_safe_redirect( add_query_arg(
			[ 'post_type' => 'ai_page', 'page' => 'aip-settings', 'aip_prefix_error' => 'empty' ],
			admin_url( 'edit.php' )
		) );
		exit;
	}

	$access_mode = isset( $_POST['aip_access_mode'] ) ? sanitize_key( wp_unslash( $_POST['aip_access_mode'] ) ) : 'disabled';
	if ( ! in_array( $access_mode, [ 'disabled', 'admin', 'editor' ], true ) ) {
		$access_mode = 'disabled';
	}
	update_option( 'aip_access_mode', $access_mode );

	$old_prefix = (string) get_option( 'aip_prefix', 'pages' );
	update_option( 'aip_prefix', $new_prefix );
	if ( $new_prefix !== $old_prefix ) {
		update_option( 'aip_flush_rewrite', 1 );
	}

	$chrome = isset( $_POST['aip_default_chrome'] ) ? sanitize_key( wp_unslash( $_POST['aip_default_chrome'] ) ) : 'none';
	update_option( 'aip_default_chrome', in_array( $chrome, [ 'none', 'site', 'full' ], true ) ? $chrome : 'none' );

	update_option( 'aip_site_head', empty( $_POST['aip_site_head'] ) ? '' : '1' );
	update_option( 'aip_head_snippet', wp_unslash( $_POST['aip_head_snippet'] ?? '' ) );
	update_option( 'aip_body_snippet', wp_unslash( $_POST['aip_body_snippet'] ?? '' ) );

	$token_action = isset( $_POST['aip_token_action'] ) ? sanitize_key( wp_unslash( $_POST['aip_token_action'] ) ) : '';
	if ( 'generate' === $token_action && aip_current_user_can_deploy() ) {
		aip_set_user_token( get_current_user_id() );
	} elseif ( 'revoke' === $token_action ) {
		aip_revoke_user_token( get_current_user_id() );
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

	$user_id     = get_current_user_id();
	$access_mode = aip_get_access_mode();
	$new_token   = aip_take_new_user_token( $user_id );
	$has_token   = aip_user_has_token( $user_id );
	$can_deploy  = aip_current_user_can_deploy();
	$created_at  = (string) get_user_meta( $user_id, 'aip_api_token_created_at', true );
	$prefix      = get_option( 'aip_prefix', 'pages' );
	$chrome      = get_option( 'aip_default_chrome', 'full' );
	$site_head   = get_option( 'aip_site_head' );
	$head_snip   = (string) get_option( 'aip_head_snippet', '' );
	$body_snip   = (string) get_option( 'aip_body_snippet', '' );
	$endpoint    = home_url( '/wp-json/ai-pages/v1/deploy' );
	$action      = admin_url( 'admin-post.php' );
	$site        = home_url( '/' );
	$prompt_token = '' !== $new_token ? $new_token : '<token-generato-da-questa-pagina>';

	$prompt = aip_agent_prompt( $endpoint, $prompt_token, $site );
	$dark   = 'background:#1e1e2e;color:#e7ecf5;padding:14px;border-radius:6px;overflow:auto;white-space:pre-wrap';
	?>
	<div class="wrap">
		<h1>AI Pages: Impostazioni</h1>

		<?php if ( ! empty( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['aip_prefix_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>Inserisci un prefisso valido per l'indirizzo delle AI page.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $action ); ?>">
			<input type="hidden" name="action" value="aip_save_settings">
			<?php wp_nonce_field( 'aip_settings' ); ?>

			<h2>Configurazione</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="aip_access_mode">Pubblicazione automatica</label></th>
					<td>
						<select id="aip_access_mode" name="aip_access_mode">
							<option value="disabled" <?php selected( $access_mode, 'disabled' ); ?>>Disattivata</option>
							<option value="admin" <?php selected( $access_mode, 'admin' ); ?>>Solo amministratori</option>
							<option value="editor" <?php selected( $access_mode, 'editor' ); ?>>Amministratori ed editor</option>
						</select>
						<p class="description">Controlla chi può creare e aggiornare AI page con token. Disattivata è l'opzione più sicura.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aip_api_token">Il tuo token</label></th>
					<td>
						<?php if ( ! $can_deploy ) : ?>
							<p>Attiva la pubblicazione automatica per generare un token.</p>
						<?php elseif ( '' !== $new_token ) : ?>
							<input type="text" id="aip_api_token" value="<?php echo esc_attr( $new_token ); ?>" readonly class="regular-text code" style="width:30em" onclick="this.select()">
							<p class="description">Copialo ora: non sarà più mostrato.</p>
						<?php elseif ( $has_token ) : ?>
							<p><strong>Token attivo.</strong><?php echo $created_at ? ' Creato il ' . esc_html( mysql2date( get_option( 'date_format' ), $created_at ) ) . '.' : ''; ?></p>
							<p class="description">Per sicurezza il valore non viene mostrato. Generane uno nuovo se l'hai perso.</p>
						<?php else : ?>
							<p>Nessun token attivo.</p>
						<?php endif; ?>

						<?php if ( $can_deploy ) : ?>
							<p>
								<button type="submit" class="button" name="aip_token_action" value="generate">Genera nuovo token</button>
								<?php if ( $has_token ) : ?>
									<button type="submit" class="button" name="aip_token_action" value="revoke">Revoca token</button>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aip_prefix">Indirizzo delle AI page</label></th>
					<td>
						<?php $home = untrailingslashit( home_url() ); ?>
						<code><?php echo esc_html( $home ); ?>/</code><input type="text" id="aip_prefix" name="aip_prefix" value="<?php echo esc_attr( $prefix ); ?>" style="width:10em" placeholder="pages" required><code>/nome-pagina</code>
						<p class="description">Cartella sotto cui vivono le AI page. Non può essere vuota, così eviti conflitti con pagine e articoli.</p>
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
						<p class="description">Valore proposto per le nuove AI page.</p>
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
		<p>Incollalo nel tuo agent cloud o locale. Se il token non è appena stato generato, nel prompt trovi un segnaposto da sostituire.</p>
		<textarea id="aip-prompt" readonly rows="16" style="width:100%;font-family:Menlo,Consolas,monospace;font-size:12px;<?php echo esc_attr( $dark ); ?>"><?php echo esc_textarea( $prompt ); ?></textarea>
		<p>
			<button type="button" class="button" onclick="var t=document.getElementById('aip-prompt');t.select();document.execCommand('copy');this.textContent='Copiato!';">Copia prompt</button>
			<span class="description">Il token identifica l'utente che pubblica le AI page.</span>
		</p>

		<hr>

		<h2>Le tre modalità chrome</h2>
		<p>Il "chrome" decide quanta cornice del sito avvolge la AI page. Si sceglie per ogni pagina (e c'è un default qui sopra).</p>
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
					<td>La AI page è avvolta da <strong>header e footer del tema</strong> (menu, logo, footer) e i plugin SEO funzionano come nel resto del sito.</td>
					<td>Quando la AI page deve sembrare parte del sito e mantenere navigazione e brand.</td>
				</tr>
				<tr>
					<td><code>full</code></td>
					<td>Il tuo HTML viene servito <strong>verbatim</strong>, dal <code>&lt;!doctype&gt;</code> al <code>&lt;/html&gt;</code>. Il plugin non aggiunge nulla: head, SEO, script li controlli tu.</td>
					<td>Quando l'AI genera un documento HTML completo (es. con Tailwind da CDN). Fedeltà totale all'output, indipendente dal tema.</td>
				</tr>
			</tbody>
		</table>

		<h2>Workflow disponibili</h2>
		<p>Una AI page è un file HTML self-contained. La <strong>AI page key</strong> la identifica: ripubblicare con la stessa key aggiorna invece di duplicare.</p>

		<h3>1. Deploy da agent cloud (REST a token)</h3>
		<pre style="<?php echo esc_attr( $dark ); ?>">curl -X POST <?php echo esc_html( $endpoint ); ?> \
  -H "Authorization: Bearer <?php echo esc_html( $prompt_token ); ?>" \
  -H "Content-Type: application/json" \
  -d '{ "key": "black-friday", "title": "Black Friday", "chrome": "full",
        "status": "publish", "html": "&lt;!doctype html&gt;...&lt;/html&gt;" }'</pre>
		<p class="description">Campi: <code>key</code> e <code>html</code> obbligatori; <code>title</code>, <code>slug</code>, <code>chrome</code>, <code>status</code> opzionali. La <code>key</code> viene salvata come AI page key univoca. Risposta: <code>{ ok, id, url, action }</code>.</p>

		<h3>2. Deploy da riga di comando (WP-CLI)</h3>
		<pre style="<?php echo esc_attr( $dark ); ?>">wp ai-page upsert --key=black-friday --file=./bf.html --chrome=full
cat bf.html | wp ai-page upsert --key=black-friday</pre>
		<p class="description">Tieni i file <code>.html</code> in git per diff, review e rollback.</p>

		<h3>3. Editing manuale</h3>
		<p>Da <strong>AI Pages &rarr; Aggiungi</strong>: editor con syntax highlighting, anteprima live (con breakpoint e schermo intero) e versioning tramite revisioni di WordPress.</p>
	</div>
	<?php
}

/**
 * Prompt pronto da incollare in un agent AI.
 */
function aip_agent_prompt( $endpoint, $token, $site ) {
	return <<<PROMPT
Sei un assistente che crea e pubblica AI page sul sito {$site}.

Per pubblicare una AI page fai UNA sola richiesta HTTP:

POST {$endpoint}
Header: Authorization: Bearer {$token}
Header: Content-Type: application/json
Body JSON: {
  "key": "<ai-page-key-univoca>",
  "title": "<titolo>",
  "chrome": "full",
  "status": "publish",
  "html": "<documento html completo>"
}

Regole per l'HTML:
- Deve essere self-contained: CSS e JS inline, nessun file locale. Font e librerie solo da CDN.
- chrome "full": generi un documento HTML completo (<!doctype html> ... </html>). chrome "none": generi solo il contenuto del <body> (il sito aggiunge una struttura minima). chrome "site": la AI page viene avvolta da header e footer del tema.
- "key" identifica la AI page: riusare la stessa key AGGIORNA la pagina invece di crearne una nuova.
- In italiano non scrivere tutto a iniziali maiuscole (titolo della pagina, non Titolo Della Pagina).

La risposta è JSON: { ok, id, url, action }. Comunica all'utente l'url pubblicato.
PROMPT;
}
