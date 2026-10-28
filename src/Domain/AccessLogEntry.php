<?php
/**
 * Entità "voce del registro accessi".
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Domain;

/**
 * Una riga del registro accessi: valore immutabile.
 */
final class AccessLogEntry {

	/**
	 * Costruttore.
	 *
	 * @param int           $post_id    ID del contenuto richiesto.
	 * @param AccessOutcome $outcome    Esito della richiesta.
	 * @param int|null      $user_id    ID utente, null se non autenticato.
	 * @param string|null   $ip_address IP in formato testuale (la codifica binaria è nel repository).
	 * @param string|null   $user_agent User agent (troncato a 500 caratteri dal repository).
	 * @param string|null   $referer    Referer (troncato a 500 caratteri dal repository).
	 * @param string|null   $created_at Data/ora UTC `Y-m-d H:i:s`; se null la imposta il repository.
	 * @param int|null      $id         ID della riga, null se non ancora salvata.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly AccessOutcome $outcome,
		public readonly ?int $user_id = null,
		public readonly ?string $ip_address = null,
		public readonly ?string $user_agent = null,
		public readonly ?string $referer = null,
		public readonly ?string $created_at = null,
		public readonly ?int $id = null
	) {}
}
