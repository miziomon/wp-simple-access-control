<?php
/**
 * Esiti possibili di una richiesta di accesso.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Domain;

/**
 * Enum degli esiti registrati nel log accessi.
 *
 * I valori stringa sono quelli salvati nella colonna `outcome` della tabella dei log.
 */
enum AccessOutcome: string {

	case GRANTED            = 'granted';
	case DENIED_NOT_LOGGED  = 'denied_not_logged';
	case DENIED_ROLE        = 'denied_role';
	case DENIED_DISABLED    = 'denied_disabled';
	case LOGIN_SUCCESS      = 'login_success';
	case LOGIN_FAILED       = 'login_failed';
	case LOGIN_RATE_LIMITED = 'login_rate_limited';

	/**
	 * Etichetta leggibile dell'esito (usata nella tabella dei log).
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::GRANTED            => __( 'Granted', 'wp-simple-access-control' ),
			self::DENIED_NOT_LOGGED  => __( 'Denied (not logged in)', 'wp-simple-access-control' ),
			self::DENIED_ROLE        => __( 'Denied (wrong role)', 'wp-simple-access-control' ),
			self::DENIED_DISABLED    => __( 'Denied (disabled)', 'wp-simple-access-control' ),
			self::LOGIN_SUCCESS      => __( 'Login successful', 'wp-simple-access-control' ),
			self::LOGIN_FAILED       => __( 'Login failed', 'wp-simple-access-control' ),
			self::LOGIN_RATE_LIMITED => __( 'Login rate limited', 'wp-simple-access-control' ),
		};
	}
}
