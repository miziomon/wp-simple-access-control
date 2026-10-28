<?php
/**
 * Template del form di login mostrato al posto di un contenuto protetto.
 *
 * Per personalizzarlo copia questo file in `{tuo-tema}/wp-simple-access-control/login-form.php`.
 * Il file non deve contenere logica: riceve tutto già pronto in `$args`.
 *
 * Variabili disponibili in `$args`:
 * - reason            AccessOutcome  Motivo del rifiuto (DENIED_NOT_LOGGED o DENIED_ROLE).
 * - message           string         Messaggio sopra il form (testo semplice).
 * - error             string         Errore dell'ultimo tentativo di login, vuoto se assente.
 * - is_logged_in      bool           True se l'utente è già loggato (ma non autorizzato).
 * - post_id           int            ID del contenuto protetto.
 * - redirect_to       string         URL a cui tornare dopo il login.
 * - action_url        string         URL di admin-post.php.
 * - action            string         Nome dell'azione di admin-post.php.
 * - nonce_action      string         Azione del nonce.
 * - show_remember     bool           Se mostrare la casella "Ricordami".
 * - lost_password_url string         Link "Password dimenticata", vuoto se disattivato.
 * - register_url      string         Link "Registrati", vuoto se disattivato.
 * - logout_url        string         URL di logout (utile se l'utente è già loggato).
 *
 * @package WPSimpleAccessControl
 *
 * @var array<string,mixed> $args
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prefisso per gli id dei campi: evita duplicati se il form compare più volte nella pagina.
$wpsac_uid = 'wpsac-login-' . (int) $args['post_id'];
?>
<div class="wpsac-login">
	<?php if ( '' !== $args['message'] ) : ?>
		<p class="wpsac-login__message"><?php echo wp_kses_post( nl2br( esc_html( $args['message'] ) ) ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $args['error'] ) : ?>
		<p class="wpsac-login__error" role="alert"><?php echo esc_html( $args['error'] ); ?></p>
	<?php endif; ?>

	<form class="wpsac-login__form" method="post" action="<?php echo esc_url( $args['action_url'] ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $args['action'] ); ?>" />
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $args['redirect_to'] ); ?>" />
		<input type="hidden" name="post_id" value="<?php echo (int) $args['post_id']; ?>" />
		<?php wp_nonce_field( $args['nonce_action'], 'wpsac_nonce' ); ?>

		<p class="wpsac-login__field">
			<label class="wpsac-login__label" for="<?php echo esc_attr( $wpsac_uid ); ?>-log">
				<?php esc_html_e( 'Username or email address', 'wp-simple-access-control' ); ?>
			</label>
			<input class="wpsac-login__input" type="text" name="log" id="<?php echo esc_attr( $wpsac_uid ); ?>-log" autocomplete="username" required />
		</p>

		<p class="wpsac-login__field">
			<label class="wpsac-login__label" for="<?php echo esc_attr( $wpsac_uid ); ?>-pwd">
				<?php esc_html_e( 'Password', 'wp-simple-access-control' ); ?>
			</label>
			<input class="wpsac-login__input" type="password" name="pwd" id="<?php echo esc_attr( $wpsac_uid ); ?>-pwd" autocomplete="current-password" required />
		</p>

		<?php if ( $args['show_remember'] ) : ?>
			<p class="wpsac-login__field wpsac-login__field--remember">
				<label class="wpsac-login__label wpsac-login__label--inline">
					<input type="checkbox" name="rememberme" value="forever" />
					<?php esc_html_e( 'Remember me', 'wp-simple-access-control' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<p class="wpsac-login__field wpsac-login__field--submit">
			<button class="wpsac-login__button" type="submit"><?php esc_html_e( 'Log in', 'wp-simple-access-control' ); ?></button>
		</p>
	</form>

	<?php if ( '' !== $args['lost_password_url'] || '' !== $args['register_url'] ) : ?>
		<p class="wpsac-login__links">
			<?php if ( '' !== $args['lost_password_url'] ) : ?>
				<a class="wpsac-login__link" href="<?php echo esc_url( $args['lost_password_url'] ); ?>"><?php esc_html_e( 'Lost your password?', 'wp-simple-access-control' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $args['register_url'] ) : ?>
				<a class="wpsac-login__link" href="<?php echo esc_url( $args['register_url'] ); ?>"><?php esc_html_e( 'Register', 'wp-simple-access-control' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( $args['is_logged_in'] ) : ?>
		<p class="wpsac-login__links">
			<a class="wpsac-login__link" href="<?php echo esc_url( $args['logout_url'] ); ?>"><?php esc_html_e( 'Log out and use another account', 'wp-simple-access-control' ); ?></a>
		</p>
	<?php endif; ?>
</div>
