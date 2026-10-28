<?php
/**
 * Scrittura nel registro accessi.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Logging;

use WPSimpleAccessControl\Domain\AccessLogEntry;
use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Infrastructure\Repository\AccessLogRepository;
use WPSimpleAccessControl\Security\IpResolver;

/**
 * Registra gli esiti di accesso raccogliendo i dati della richiesta corrente.
 */
class AccessLogger {

	/**
	 * Repository del registro.
	 *
	 * @var AccessLogRepository
	 */
	private AccessLogRepository $logs;

	/**
	 * Risoluzione dell'IP.
	 *
	 * @var IpResolver
	 */
	private IpResolver $ip;

	/**
	 * Costruttore.
	 *
	 * @param AccessLogRepository $logs Repository del registro.
	 * @param IpResolver          $ip   Risoluzione dell'IP.
	 */
	public function __construct( AccessLogRepository $logs, IpResolver $ip ) {
		$this->logs = $logs;
		$this->ip   = $ip;
	}

	/**
	 * Scrive una voce nel registro.
	 *
	 * @param int           $post_id ID del contenuto coinvolto.
	 * @param AccessOutcome $outcome Esito della richiesta.
	 * @param int|null      $user_id ID utente; null = utente corrente (se non loggato resta null).
	 * @return void
	 */
	public function log( int $post_id, AccessOutcome $outcome, ?int $user_id = null ): void {
		$user_id ??= get_current_user_id();

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null;
		$referer    = wp_get_raw_referer();

		$entry = new AccessLogEntry(
			$post_id,
			$outcome,
			$user_id > 0 ? $user_id : null,
			$this->ip->ip_for_log(),
			$user_agent,
			false === $referer ? null : esc_url_raw( $referer )
		);

		$id = $this->logs->add( $entry );

		if ( false !== $id ) {
			/**
			 * Scatta dopo la scrittura di una voce nel registro accessi.
			 *
			 * @param AccessLogEntry $entry Voce salvata.
			 * @param int            $id    ID della riga inserita.
			 */
			do_action( 'wpsac_after_log', $entry, $id );
		}
	}
}
