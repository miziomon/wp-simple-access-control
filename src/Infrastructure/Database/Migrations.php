<?php
/**
 * Gestione degli aggiornamenti dello schema.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Infrastructure\Database;

/**
 * Confronta la versione dello schema installata con quella attesa e,
 * se serve, riesegue Schema::install() (dbDelta è idempotente).
 *
 * Serve ad aggiornare le tabelle dopo un update del plugin, perché
 * l'hook di attivazione non scatta negli aggiornamenti.
 */
final class Migrations {

	/**
	 * Aggiorna lo schema del sito corrente se la versione non coincide.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( Schema::OPTION_DB_VERSION, '' );

		if ( version_compare( $installed, Schema::DB_VERSION, '<' ) ) {
			Schema::install();
		}
	}
}
