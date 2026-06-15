<?php
/**
 * Token API per-utente: ogni utente ha il suo token, i deploy via REST sono
 * attribuiti a quell'utente (post_author). UI nel profilo utente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hash queryable del token. Il token è casuale e non viene salvato in chiaro.
 */
function aip_hash_token( $token ) {
	return hash_hmac( 'sha256', trim( (string) $token ), wp_salt( 'auth' ) );
}

function aip_user_has_token( $user_id ) {
	return '' !== (string) get_user_meta( $user_id, 'aip_api_token_hash', true );
}

function aip_set_user_token( $user_id ) {
	$token = aip_generate_token();
	update_user_meta( $user_id, 'aip_api_token_hash', aip_hash_token( $token ) );
	update_user_meta( $user_id, 'aip_api_token_created_at', current_time( 'mysql' ) );
	delete_user_meta( $user_id, 'aip_api_token' );
	aip_store_new_user_token( $user_id, $token );

	return $token;
}

function aip_revoke_user_token( $user_id ) {
	delete_user_meta( $user_id, 'aip_api_token_hash' );
	delete_user_meta( $user_id, 'aip_api_token_created_at' );
	delete_user_meta( $user_id, 'aip_api_token' );
	delete_transient( aip_new_user_token_transient_key( $user_id ) );
}

function aip_new_user_token_transient_key( $user_id ) {
	return 'aip_new_api_token_' . (int) $user_id;
}

function aip_store_new_user_token( $user_id, $token ) {
	set_transient( aip_new_user_token_transient_key( $user_id ), $token, 10 * MINUTE_IN_SECONDS );
}

function aip_take_new_user_token( $user_id ) {
	$key   = aip_new_user_token_transient_key( $user_id );
	$token = (string) get_transient( $key );
	if ( '' !== $token ) {
		delete_transient( $key );
	}

	return $token;
}

/**
 * Risolve un token a un user ID (0 se non trovato).
 */
function aip_user_by_token( $token ) {
	$token = trim( (string) $token );
	if ( '' === $token ) {
		return 0;
	}
	$users = get_users( [
		'meta_key'   => 'aip_api_token_hash',
		'meta_value' => aip_hash_token( $token ),
		'number'     => 1,
		'fields'     => 'ID',
	] );
	return $users ? (int) $users[0] : 0;
}

add_action( 'admin_init', function () {
	if ( get_option( 'aip_token_storage_version' ) ) {
		return;
	}

	delete_metadata( 'user', 0, 'aip_api_token', '', true );
	update_option( 'aip_token_storage_version', '2' );
} );

/* -------------------------------------------------------------------------
 * UI nel profilo utente
 * ---------------------------------------------------------------------- */
add_action( 'show_user_profile', 'aip_profile_token_field' );
add_action( 'edit_user_profile', 'aip_profile_token_field' );
function aip_profile_token_field( $user ) {
	if ( ! aip_user_can_deploy( $user ) ) {
		return;
	}
	$new_token  = aip_take_new_user_token( $user->ID );
	$has_token  = aip_user_has_token( $user->ID );
	$created_at = (string) get_user_meta( $user->ID, 'aip_api_token_created_at', true );
	?>
	<h2>AI Pages</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="aip_user_token">Token pubblicazione</label></th>
			<td>
				<?php if ( '' !== $new_token ) : ?>
					<input type="text" id="aip_user_token" value="<?php echo esc_attr( $new_token ); ?>" readonly class="regular-text code" style="width:30em" onclick="this.select()">
					<p class="description">Copialo ora: non sarà più mostrato.</p>
				<?php elseif ( $has_token ) : ?>
					<p><strong>Token attivo.</strong><?php echo $created_at ? ' Creato il ' . esc_html( mysql2date( get_option( 'date_format' ), $created_at ) ) . '.' : ''; ?></p>
					<p class="description">Per sicurezza il valore non viene mostrato. Generane uno nuovo se l'hai perso.</p>
				<?php else : ?>
					<p>Nessun token attivo.</p>
				<?php endif; ?>
				<p>
					<button type="submit" class="button" name="aip_token_action" value="generate">Genera nuovo token</button>
					<?php if ( $has_token ) : ?>
						<button type="submit" class="button" name="aip_token_action" value="revoke">Revoca token</button>
					<?php endif; ?>
				</p>
				<p class="description">Il token permette la pubblicazione automatica come questo utente.</p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'aip_profile_token_save' );
add_action( 'edit_user_profile_update', 'aip_profile_token_save' );
function aip_profile_token_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! aip_user_can_deploy( $user ) ) {
		return;
	}

	$action = isset( $_POST['aip_token_action'] ) ? sanitize_key( wp_unslash( $_POST['aip_token_action'] ) ) : '';
	if ( 'generate' === $action ) {
		aip_set_user_token( $user_id );
	} elseif ( 'revoke' === $action ) {
		aip_revoke_user_token( $user_id );
	}
}
