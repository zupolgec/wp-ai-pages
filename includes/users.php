<?php
/**
 * Token API per-utente: ogni utente ha il suo token, i deploy via REST sono
 * attribuiti a quell'utente (post_author). UI nel profilo utente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token dell'utente, creandolo al volo se mancante.
 */
function aip_get_user_token( $user_id ) {
	$token = get_user_meta( $user_id, 'aip_api_token', true );
	if ( ! $token ) {
		$token = aip_generate_token();
		update_user_meta( $user_id, 'aip_api_token', $token );
	}
	return $token;
}

/**
 * Risolve un token a un user ID (0 se non trovato).
 */
function aip_user_by_token( $token ) {
	if ( ! $token ) {
		return 0;
	}
	$users = get_users( [
		'meta_key'   => 'aip_api_token',
		'meta_value' => $token,
		'number'     => 1,
		'fields'     => 'ID',
	] );
	return $users ? (int) $users[0] : 0;
}

/* -------------------------------------------------------------------------
 * UI nel profilo utente
 * ---------------------------------------------------------------------- */
add_action( 'show_user_profile', 'aip_profile_token_field' );
add_action( 'edit_user_profile', 'aip_profile_token_field' );
function aip_profile_token_field( $user ) {
	if ( ! user_can( $user, 'edit_posts' ) ) {
		return; // solo chi può creare contenuti.
	}
	$token = aip_get_user_token( $user->ID );
	?>
	<h2>AI Pages</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="aip_user_token">Token deploy</label></th>
			<td>
				<input type="text" id="aip_user_token" value="<?php echo esc_attr( $token ); ?>" readonly class="regular-text code" style="width:30em" onclick="this.select()">
				<p><label><input type="checkbox" name="aip_regen_token" value="1"> Rigenera al salvataggio</label></p>
				<p class="description">Autentica i deploy via REST (<code>Authorization: Bearer ...</code>) come questo utente. Le landing pubblicate con questo token risultano create da te.</p>
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
	if ( ! empty( $_POST['aip_regen_token'] ) ) {
		update_user_meta( $user_id, 'aip_api_token', aip_generate_token() );
	}
}
