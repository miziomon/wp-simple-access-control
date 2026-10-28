<?php
/**
 * Rendering del form di login al posto del contenuto protetto.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Domain\AccessRule;
use WPSimpleAccessControl\Support\Settings;
use WPSimpleAccessControl\Support\Template;

/**
 * Mostra il form di login quando l'accesso a un contenuto è negato.
 *
 * Modalità inline: il form compare dentro il tema del sito.
 * - Tema classico: `get_header()`, form, `get_footer()` ed `exit`, così il
 *   template del tema non tocca mai il contenuto protetto.
 * - Block theme: `get_header()` non è utilizzabile (non c'è header.php), quindi
 *   si sostituisce il contenuto del post tramite `the_content`, che nei block
 *   theme passa sempre da `core/post-content`.
 */
class LoginRenderer {

	/**
	 * Nome del parametro di query che porta l'esito di un login fallito.
	 */
	public const ERROR_QUERY_ARG = 'wpsac_error';

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Costruttore.
	 *
	 * @param Settings $settings Impostazioni globali.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Mostra il form di login e, nei temi classici, termina la richiesta.
	 *
	 * @param AccessOutcome $reason Motivo del rifiuto (DENIED_NOT_LOGGED o DENIED_ROLE).
	 * @param AccessRule    $rule   Regola del contenuto.
	 * @param \WP_Post      $post   Contenuto protetto.
	 * @return void
	 */
	public function render( AccessOutcome $reason, AccessRule $rule, \WP_Post $post ): void {
		// Il contenuto è negato: 403, così i motori di ricerca non lo indicizzano come pagina valida.
		status_header( 403 );

		Assets::enqueue();

		$form = Template::render( 'login-form', $this->build_context( $reason, $rule, $post ) );

		// Modalità "pagina autonoma": documento HTML minimale, senza il tema.
		if ( 'full_template' === $this->settings->get( 'login_form_mode' ) ) {
			$page = Template::render(
				'login-page',
				array(
					'form'      => $form,
					/* translators: %s: site name. */
					'title'     => sprintf( __( 'Log in - %s', 'wp-simple-access-control' ), get_bloginfo( 'name' ) ),
					'site_name' => get_bloginfo( 'name' ),
					'home_url'  => home_url( '/' ),
				)
			);

			echo $page; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato dai template.
			exit;
		}

		if ( wp_is_block_theme() ) {
			$this->replace_content( $form, $post );
			return;
		}

		get_header();
		echo '<main class="wpsac-login__wrapper">' . $form . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato dal template.
		get_footer();
		exit;
	}

	/**
	 * Sostituisce il contenuto del post con il form (block theme) e nasconde i commenti.
	 *
	 * @param string   $form HTML del form.
	 * @param \WP_Post $post Contenuto protetto.
	 * @return void
	 */
	private function replace_content( string $form, \WP_Post $post ): void {
		// Senza tipo sul parametro: alcuni plugin chiamano `the_content` con null e un TypeError qui darebbe un fatal.
		add_filter(
			'the_content',
			static fn( $content ) => ( is_main_query() && get_the_ID() === $post->ID ) ? $form : $content,
			PHP_INT_MAX
		);

		// I commenti di un contenuto riservato non vanno mostrati.
		add_filter( 'comments_open', '__return_false', PHP_INT_MAX );
		add_filter( 'comments_array', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'get_comments_number', '__return_zero', PHP_INT_MAX );
	}

	/**
	 * Prepara i dati per il template del form.
	 *
	 * @param AccessOutcome $reason Motivo del rifiuto.
	 * @param AccessRule    $rule   Regola del contenuto.
	 * @param \WP_Post      $post   Contenuto protetto.
	 * @return array<string,mixed>
	 */
	private function build_context( AccessOutcome $reason, AccessRule $rule, \WP_Post $post ): array {
		$redirect_to = (string) get_permalink( $post );

		return array(
			'reason'            => $reason,
			'message'           => $this->resolve_message( $reason, $rule ),
			'error'             => $this->resolve_error(),
			'is_logged_in'      => is_user_logged_in(),
			'post_id'           => $post->ID,
			'redirect_to'       => $redirect_to,
			'action_url'        => admin_url( 'admin-post.php' ),
			'action'            => LoginHandler::ACTION,
			'nonce_action'      => LoginHandler::NONCE_ACTION,
			'show_remember'     => (bool) $this->settings->get( 'enable_remember_me' ),
			'lost_password_url' => $this->settings->get( 'enable_lost_password' ) ? wp_lostpassword_url( $redirect_to ) : '',
			'register_url'      => ( $this->settings->get( 'enable_registration' ) && get_option( 'users_can_register' ) ) ? wp_registration_url() : '',
			'logout_url'        => wp_logout_url( $redirect_to ),
		);
	}

	/**
	 * Messaggio sopra il form.
	 *
	 * Priorità: messaggio del contenuto, poi messaggio globale, poi testo di default.
	 *
	 * @param AccessOutcome $reason Motivo del rifiuto.
	 * @param AccessRule    $rule   Regola del contenuto.
	 * @return string Testo semplice (viene escapato dal template).
	 */
	private function resolve_message( AccessOutcome $reason, AccessRule $rule ): string {
		if ( null !== $rule->login_message && '' !== $rule->login_message ) {
			return $rule->login_message;
		}

		$global = (string) $this->settings->get( 'default_login_message' );

		if ( '' !== $global ) {
			return $global;
		}

		if ( AccessOutcome::DENIED_ROLE === $reason ) {
			return __( 'Your account is not allowed to view this content.', 'wp-simple-access-control' );
		}

		return __( 'This content is reserved. Please log in to view it.', 'wp-simple-access-control' );
	}

	/**
	 * Messaggio di errore dopo un login fallito (da un elenco chiuso di codici).
	 *
	 * @return string Messaggio, stringa vuota se non c'è errore.
	 */
	private function resolve_error(): string {
		// Lettura di un flag di sola visualizzazione: non modifica dati, quindi niente nonce.
		$code = isset( $_GET[ self::ERROR_QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::ERROR_QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Si usa solo l'elenco chiuso: il valore ricevuto non viene mai stampato.
		$messages = array(
			'failed'       => __( 'Invalid username or password.', 'wp-simple-access-control' ),
			'empty'        => __( 'Please enter your username and password.', 'wp-simple-access-control' ),
			'nonce'        => __( 'The form has expired. Please try again.', 'wp-simple-access-control' ),
			'rate_limited' => __( 'Too many failed attempts. Please try again later.', 'wp-simple-access-control' ),
		);

		return $messages[ $code ] ?? '';
	}
}
