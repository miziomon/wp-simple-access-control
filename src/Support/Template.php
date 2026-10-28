<?php
/**
 * Caricamento dei template con override da tema.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Support;

/**
 * Trova e renderizza i template del plugin.
 *
 * Ordine di ricerca di `<nome>.php`:
 * 1. `{tema-figlio}/wp-simple-access-control/`
 * 2. `{tema-padre}/wp-simple-access-control/`
 * 3. `templates/` del plugin
 */
final class Template {

	/**
	 * Nome della sottocartella usata per l'override nel tema.
	 */
	private const THEME_DIR = 'wp-simple-access-control';

	/**
	 * Renderizza un template e ne restituisce l'HTML.
	 *
	 * Le variabili sono disponibili nel template come array `$args`.
	 *
	 * @param string              $name Nome del template senza estensione (es. `login-form`).
	 * @param array<string,mixed> $args Dati da passare al template.
	 * @return string HTML già escapato dal template; stringa vuota se il template non esiste.
	 */
	public static function render( string $name, array $args = array() ): string {
		$file = self::locate( $name );

		if ( '' === $file ) {
			return '';
		}

		/**
		 * Filtra i dati passati a un template prima del rendering.
		 *
		 * @param array<string,mixed> $args Dati del template.
		 * @param string              $name Nome del template.
		 */
		$args = apply_filters( 'wpsac_template_args', $args, $name );

		// Include dentro una funzione anonima: il template vede solo $args, non lo scope del chiamante.
		ob_start();
		( static function ( string $wpsac_file, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args è usato dal template incluso.
			include $wpsac_file;
		} )( $file, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Percorso del file di un template (con override da tema).
	 *
	 * @param string $name Nome del template senza estensione.
	 * @return string Percorso assoluto, stringa vuota se non trovato.
	 */
	public static function locate( string $name ): string {
		// Solo nomi semplici: niente separatori di percorso (evita path traversal).
		$name = sanitize_file_name( $name );

		if ( '' === $name ) {
			return '';
		}

		// locate_template() cerca prima nel tema figlio e poi nel tema padre.
		$theme_file = locate_template( self::THEME_DIR . '/' . $name . '.php' );

		if ( '' !== $theme_file ) {
			return $theme_file;
		}

		$plugin_file = WPSAC_PLUGIN_DIR . 'templates/' . $name . '.php';

		return is_readable( $plugin_file ) ? $plugin_file : '';
	}
}
