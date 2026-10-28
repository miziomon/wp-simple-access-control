<?php
/**
 * Messaggio mostrato al posto di un blocco riservato.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Blocks;

use WPSimpleAccessControl\Domain\AccessPolicy;
use WPSimpleAccessControl\Support\Settings;

/**
 * Costruisce l'HTML del "teaser" che sostituisce un blocco nascosto.
 */
class TeaserRenderer {

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Costruttore.
	 *
	 * @param Settings $settings Impostazioni globali.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * HTML del teaser per un blocco nascosto.
	 *
	 * Priorità del messaggio:
	 * 1. messaggio del blocco (se il teaser è attivo e il testo non è vuoto);
	 * 2. messaggio globale di default, solo per i blocchi `logged_in`
	 *    (a un utente già loggato non si chiede di fare login);
	 * 3. nessun teaser: il blocco sparisce senza lasciare tracce.
	 *
	 * Segnaposto nel messaggio: `[login]testo[/login]` diventa un link alla pagina di login
	 * e `{login_url}` diventa l'URL di login.
	 *
	 * @param array<string,mixed> $block Blocco parsato (con `attrs`).
	 * @param string              $mode  Modalità di visibilità del blocco.
	 * @return string HTML già filtrato; stringa vuota se non c'è teaser.
	 */
	public function render( array $block, string $mode ): string {
		$attrs   = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$message = '';

		if ( ! empty( $attrs['wpsacShowTeaser'] ) && is_string( $attrs['wpsacTeaserMessage'] ?? null ) ) {
			$message = trim( $attrs['wpsacTeaserMessage'] );
		}

		if ( '' === $message && AccessPolicy::MODE_LOGGED_IN === $mode ) {
			$message = trim( (string) $this->settings->get( 'default_block_teaser' ) );
		}

		if ( '' === $message ) {
			return '';
		}

		$login_url = esc_url( wp_login_url( $this->current_url() ) );

		// Il messaggio è testo semplice: si escapa e solo dopo si sostituiscono i segnaposto.
		// Niente HTML nell'attributo del blocco: kses ripulirebbe il commento del blocco per
		// gli autori senza `unfiltered_html`, facendo sparire la restrizione (fail open).
		$html = esc_html( $message );
		$html = (string) preg_replace_callback(
			'/\[login\](.*?)\[\/login\]/s',
			static fn( array $found ): string => '<a href="' . $login_url . '">' . $found[1] . '</a>',
			$html
		);
		$html = str_replace( '{login_url}', $login_url, $html );

		$html = '<div class="wpsac-teaser">' . wp_kses_post( wpautop( $html ) ) . '</div>';

		/**
		 * Filtra l'HTML del teaser mostrato al posto di un blocco riservato.
		 *
		 * @param string              $html  HTML del teaser.
		 * @param array<string,mixed> $block Blocco parsato.
		 * @param string              $mode  Modalità di visibilità del blocco.
		 */
		return (string) apply_filters( 'wpsac_block_teaser_html', $html, $block, $mode );
	}

	/**
	 * URL della richiesta corrente (senza il parametro di errore del login).
	 *
	 * Si costruisce dalla richiesta e non da get_permalink(), che fuori dal loop può restituire false.
	 *
	 * @return string
	 */
	private function current_url(): string {
		global $wp;

		// Solo la lettura dei parametri per ricostruire l'URL: nessun dato viene modificato.
		$query = array_diff_key( wp_unslash( $_GET ), array( 'wpsac_error' => true ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- l'URL viene poi passato a esc_url()/wp_login_url().

		return home_url( add_query_arg( $query, $wp->request ?? '' ) );
	}
}
