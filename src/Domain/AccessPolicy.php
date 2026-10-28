<?php
/**
 * Regole di decisione dell'accesso.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Domain;

/**
 * Decide se un utente può vedere un contenuto, dati i ruoli richiesti.
 *
 * Servizio senza stato né accesso al database: è l'unico posto in cui vivono
 * le regole di accesso, condiviso dal gate dei contenuti, dalla REST API, dai
 * feed e dai blocchi.
 */
class AccessPolicy {

	/**
	 * Modalità di visibilità di un blocco: visibile a tutti.
	 */
	public const MODE_ALL = 'all';

	/**
	 * Modalità di visibilità di un blocco: solo utenti loggati (con ruoli opzionali).
	 */
	public const MODE_LOGGED_IN = 'logged_in';

	/**
	 * Modalità di visibilità di un blocco: solo visitatori non loggati.
	 */
	public const MODE_LOGGED_OUT = 'logged_out';

	/**
	 * Verifica l'accesso di un utente a un contenuto protetto per ruolo.
	 *
	 * - non loggato                       => DENIED_NOT_LOGGED
	 * - amministratore (manage_options)   => GRANTED, sempre
	 * - nessun ruolo richiesto            => GRANTED (basta essere loggati)
	 * - almeno un ruolo in comune         => GRANTED
	 * - altrimenti                        => DENIED_ROLE
	 *
	 * @param \WP_User          $user  Utente corrente (ID 0 = non autenticato).
	 * @param array<int,string> $roles Slug dei ruoli ammessi; vuoto = qualsiasi utente loggato.
	 * @return AccessOutcome GRANTED, DENIED_NOT_LOGGED o DENIED_ROLE.
	 */
	public function check( \WP_User $user, array $roles ): AccessOutcome {
		if ( 0 === $user->ID ) {
			return AccessOutcome::DENIED_NOT_LOGGED;
		}

		// L'amministratore passa sempre, anche se il suo ruolo non è nell'elenco.
		if ( user_can( $user, 'manage_options' ) ) {
			return AccessOutcome::GRANTED;
		}

		if ( array() === $roles ) {
			return AccessOutcome::GRANTED;
		}

		if ( array() !== array_intersect( $user->roles, $roles ) ) {
			return AccessOutcome::GRANTED;
		}

		return AccessOutcome::DENIED_ROLE;
	}

	/**
	 * Verifica se un utente può vedere un singolo blocco.
	 *
	 * - `all`        => sempre
	 * - `logged_in`  => stesse regole dei contenuti (admin sempre, ruoli opzionali)
	 * - `logged_out` => solo chi non è loggato; l'admin loggato NON lo vede
	 * - altro        => mai (fail closed: un valore sconosciuto non deve esporre contenuto)
	 *
	 * @param string            $mode  Una delle costanti MODE_*.
	 * @param array<int,string> $roles Slug dei ruoli ammessi (usati solo con `logged_in`).
	 * @param \WP_User          $user  Utente corrente.
	 * @return bool
	 */
	public function can_view_block( string $mode, array $roles, \WP_User $user ): bool {
		return match ( $mode ) {
			self::MODE_ALL        => true,
			self::MODE_LOGGED_OUT => 0 === $user->ID,
			self::MODE_LOGGED_IN  => AccessOutcome::GRANTED === $this->check( $user, $roles ),
			default               => false,
		};
	}
}
