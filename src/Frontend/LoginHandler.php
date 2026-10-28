<?php
/**
 * Gestione del POST del form di login.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Logging\AccessLogger;
use WPSimpleAccessControl\Security\LoginRateLimiter;
use WPSimpleAccessControl\Support\Settings;

/**
 * Elabora l'azione `wpsac_do_login` (via admin-post.php).
 *
 * In caso di errore ritorna alla pagina protetta con un codice nella query
 * (pattern Post/Redirect/Get), così il form si ricarica e il refresh non
 * rinvia le credenziali.
 */
class LoginHandler {

	/**
	 * Nome dell'azione di admin-post.php.
	 */
	public const ACTION = 'wpsac_do_login';

	/**
	 * Azione del nonce del form.
	 */
	public const NONCE_ACTION = 'wpsac_login';

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registro accessi.
	 *
	 * @var AccessLogger
	 */
	private AccessLogger $logger;

	/**
	 * Limitazione dei tentativi di login.
	 *
	 * @var LoginRateLimiter
	 */
	private LoginRateLimiter $limiter;

	/**
	 * Costruttore.
	 *
	 * @param Settings         $settings Impostazioni globali.
	 * @param AccessLogger     $logger   Registro accessi.
	 * @param LoginRateLimiter $limiter  Limitazione dei tentativi di login.
	 */
	public function __construct( Settings $settings, AccessLogger $logger, LoginRateLimiter $limiter ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->limiter  = $limiter;
	}

	/**
	 * Aggancia gli hook (utenti anonimi e già loggati che cambiano account).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Elabora il login e reindirizza.
	 *
	 * @return void
	 */
	public function handle(): void {
		$redirect_to = $this->requested_redirect();

		// Contenuto da cui arriva il login: serve solo al registro accessi.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce viene verificato subito sotto, prima di agire sui dati.

		// 1. Nonce.
		$nonce = isset( $_POST['wpsac_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpsac_nonce'] ) ) : '';

		if ( false === wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->back_with_error( $redirect_to, 'nonce' );
		}

		// 2. Credenziali. La password non va sanitizzata: qualsiasi alterazione la renderebbe errata.
		$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $login || '' === $password ) {
			$this->back_with_error( $redirect_to, 'empty' );
		}

		// 3. Rate limit: dopo troppi errori l'IP è bloccato e le credenziali non vengono nemmeno provate.
		if ( $this->limiter->is_limited() ) {
			$this->logger->log( $post_id, AccessOutcome::LOGIN_RATE_LIMITED, 0 );
			$this->back_with_error( $redirect_to, 'rate_limited' );
		}

		// 4. Autenticazione: wp_signon imposta i cookie e fa scattare `wp_login` (e quindi il 2FA, se attivo).
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['rememberme'] ) && (bool) $this->settings->get( 'enable_remember_me' ),
			)
		);

		if ( is_wp_error( $user ) ) {
			$this->limiter->record_failure();
			$this->logger->log( $post_id, AccessOutcome::LOGIN_FAILED, 0 );
			$this->back_with_error( $redirect_to, 'failed' );
		}

		$this->limiter->clear();
		$this->logger->log( $post_id, AccessOutcome::LOGIN_SUCCESS, $user->ID );

		// 5. Torna al contenuto originale.
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * URL di ritorno ricevuto dal form, validato (solo URL del sito).
	 *
	 * @return string
	 */
	private function requested_redirect(): string {
		$raw = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce viene verificato in handle(), prima di agire sui dati.

		return wp_validate_redirect( $raw, home_url( '/' ) );
	}

	/**
	 * Torna alla pagina protetta con un codice d'errore.
	 *
	 * @param string $redirect_to URL di ritorno.
	 * @param string $code        Codice dell'errore (vedi LoginRenderer::resolve_error()).
	 * @return never
	 */
	private function back_with_error( string $redirect_to, string $code ): never {
		wp_safe_redirect( add_query_arg( LoginRenderer::ERROR_QUERY_ARG, $code, $redirect_to ) );
		exit;
	}
}
