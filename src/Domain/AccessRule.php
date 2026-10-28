<?php
/**
 * Entità "regola di accesso" di un singolo contenuto.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Domain;

/**
 * Regola di accesso di un contenuto: valore immutabile, senza logica di persistenza.
 */
final class AccessRule {

	/**
	 * Costruttore.
	 *
	 * @param int               $post_id       ID del contenuto protetto.
	 * @param bool              $enabled       True se la protezione è attiva.
	 * @param array<int,string> $roles         Slug dei ruoli ammessi; vuoto = qualsiasi utente loggato.
	 * @param string|null       $login_message Messaggio personalizzato sopra il form di login.
	 * @param int|null          $id            ID della riga, null se non ancora salvata.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly bool $enabled,
		public readonly array $roles = array(),
		public readonly ?string $login_message = null,
		public readonly ?int $id = null
	) {}
}
