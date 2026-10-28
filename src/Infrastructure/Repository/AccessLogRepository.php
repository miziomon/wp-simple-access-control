<?php
/**
 * Repository del registro accessi.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Infrastructure\Repository;

use WPSimpleAccessControl\Domain\AccessLogEntry;
use WPSimpleAccessControl\Infrastructure\Database\Schema;

/**
 * Scrive nella tabella `{prefix}wpsac_access_logs`.
 *
 * Le query di lettura (filtri, paginazione, export) arrivano con la pagina dei log.
 */
class AccessLogRepository {

	/**
	 * Lunghezza massima di user agent e referer salvati.
	 */
	private const MAX_TEXT_LENGTH = 500;

	/**
	 * Inserisce una voce nel registro.
	 *
	 * @param AccessLogEntry $entry Voce da salvare.
	 * @return int|false ID della riga inserita, false in caso di errore.
	 */
	public function add( AccessLogEntry $entry ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			Schema::logs_table(),
			array(
				'post_id'    => $entry->post_id,
				'user_id'    => $entry->user_id,
				'ip_address' => $this->pack_ip( $entry->ip_address ),
				'user_agent' => $this->truncate( $entry->user_agent ),
				'outcome'    => $entry->outcome->value,
				'referer'    => $this->truncate( $entry->referer ),
				'created_at' => $entry->created_at ?? current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Converte un IP testuale in binario (inet_pton), per IPv4 e IPv6.
	 *
	 * @param string|null $ip Indirizzo IP testuale.
	 * @return string|null Binario di 4 o 16 byte, null se assente o non valido.
	 */
	private function pack_ip( ?string $ip ): ?string {
		if ( null === $ip || '' === $ip ) {
			return null;
		}

		$packed = inet_pton( $ip );

		return false === $packed ? null : $packed;
	}

	/**
	 * Tronca una stringa alla lunghezza massima della colonna.
	 *
	 * @param string|null $value Valore da troncare.
	 * @return string|null
	 */
	private function truncate( ?string $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return mb_substr( $value, 0, self::MAX_TEXT_LENGTH );
	}
}
