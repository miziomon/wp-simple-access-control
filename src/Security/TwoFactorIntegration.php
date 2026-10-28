<?php
/**
 * Integrazione con i plugin di autenticazione a due fattori.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Security;

use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Frontend\ContentAccess;
use WPSimpleAccessControl\Logging\AccessLogger;
use WPSimpleAccessControl\Support\Settings;

/**
 * Il form di login usa `wp_signon()`, quindi i plugin 2FA funzionano come su wp-login.php:
 * - utente senza 2FA: il login si completa subito (lo registra LoginHandler);
 * - utente con 2FA: il plugin interrompe il flusso dopo la password e mostra la seconda verifica,
 *   poi riporta l'utente al contenuto grazie al campo `redirect_to` del nostro form.
 *
 * Il secondo caso non passa da LoginHandler, perciò qui si registra `login_success`
 * solo quando il secondo fattore è stato davvero superato.
 *
 * Plugin rilevati: Two Factor (hook `two_factor_user_authenticated`). Altri plugin possono
 * dichiararsi con il filtro `wpsac_twofa_active` e chiamare log_success() dal loro hook.
 */
class TwoFactorIntegration {

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
	 * Verifica di accesso ai contenuti.
	 *
	 * @var ContentAccess
	 */
	private ContentAccess $access;

	/**
	 * Costruttore.
	 *
	 * @param Settings      $settings Impostazioni globali.
	 * @param AccessLogger  $logger   Registro accessi.
	 * @param ContentAccess $access   Verifica di accesso ai contenuti.
	 */
	public function __construct( Settings $settings, AccessLogger $logger, ContentAccess $access ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->access   = $access;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'two_factor_user_authenticated', array( $this, 'log_success' ) );
	}

	/**
	 * Indica se l'integrazione è attiva (opzione `auto` e un plugin 2FA presente).
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		if ( 'auto' !== $this->settings->get( 'twofa_integration' ) ) {
			return false;
		}

		$detected = class_exists( 'Two_Factor_Core' );

		/**
		 * Permette a un plugin 2FA non rilevato di dichiararsi presente.
		 *
		 * @param bool $detected True se un plugin 2FA compatibile è attivo.
		 */
		return (bool) apply_filters( 'wpsac_twofa_active', $detected );
	}

	/**
	 * Registra il login riuscito dopo il secondo fattore.
	 *
	 * Si registra solo se l'utente stava andando a un contenuto protetto: lo stesso hook scatta
	 * anche per i login di wp-login.php, che non riguardano questo plugin.
	 *
	 * @param \WP_User $user Utente appena autenticato.
	 * @return void
	 */
	public function log_success( \WP_User $user ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		// Il plugin 2FA ha già verificato il proprio nonce prima di emettere l'hook.
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id  = '' === $redirect ? 0 : url_to_postid( wp_validate_redirect( $redirect, home_url( '/' ) ) );

		if ( $post_id > 0 && $this->access->is_protected( $post_id ) ) {
			$this->logger->log( $post_id, AccessOutcome::LOGIN_SUCCESS, $user->ID );
		}
	}
}
