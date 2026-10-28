<?php
/**
 * Lettura delle impostazioni globali del plugin.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Support;

/**
 * Accesso in sola lettura all'opzione `wpsac_settings` (per-sito), con i valori di default.
 *
 * La scrittura e la sanitizzazione sono nella pagina delle impostazioni (step 9).
 */
class Settings {

	/**
	 * Nome dell'opzione che contiene tutte le impostazioni.
	 */
	public const OPTION = 'wpsac_settings';

	/**
	 * Valori di default di tutte le impostazioni.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'login_form_mode'       => 'inline', // Valori ammessi: inline, full_template.
			'enable_remember_me'    => true,
			'enable_lost_password'  => true,
			'enable_registration'   => false, // Rispetta comunque get_option( 'users_can_register' ).
			'default_login_message' => '',
			'default_block_teaser'  => '',
			'rate_limit_attempts'   => 5,
			'rate_limit_window'     => 300, // Secondi.
			'rate_limit_lockout'    => 900, // Secondi.
			'anonymize_ip'          => false,
			'trust_proxy_headers'   => false,
			'feed_show_excerpt'     => true,
			'log_retention_days'    => 0, // 0 = nessuna retention.
			'twofa_integration'     => 'auto', // Valori ammessi: auto, off.
		);
	}

	/**
	 * Tutte le impostazioni, con i default per le chiavi mancanti.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Valore di una singola impostazione.
	 *
	 * @param string $key Chiave dell'impostazione.
	 * @return mixed Valore salvato, altrimenti il default; null se la chiave non esiste.
	 */
	public function get( string $key ): mixed {
		return $this->all()[ $key ] ?? null;
	}
}
