<?php
/**
 * Risoluzione dell'indirizzo IP del client.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Security;

use WPSimpleAccessControl\Support\Settings;

/**
 * Ricava l'IP del visitatore e, se richiesto, lo anonimizza prima di salvarlo.
 */
class IpResolver {

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
	 * IP del client, così com'è (serve al rate limiter, che lo usa solo come chiave hash).
	 *
	 * Legge `REMOTE_ADDR`. L'header `X-Forwarded-For` viene usato solo se l'opzione
	 * `trust_proxy_headers` è attiva (default: no), perché un client può falsificarlo
	 * quando il sito non è dietro a un proxy che lo riscrive.
	 *
	 * @return string|null IP valido, null se non determinabile.
	 */
	public function client_ip(): ?string {
		$candidates = array();

		if ( $this->settings->get( 'trust_proxy_headers' ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// L'elenco è "client, proxy1, proxy2": il primo è il client originale.
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			foreach ( explode( ',', $forwarded ) as $ip ) {
				$candidates[] = trim( $ip );
			}
		}

		$candidates[] = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		foreach ( $candidates as $ip ) {
			if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return null;
	}

	/**
	 * IP da salvare nel log: anonimizzato se l'opzione `anonymize_ip` è attiva.
	 *
	 * @return string|null
	 */
	public function ip_for_log(): ?string {
		$ip = $this->client_ip();

		if ( null === $ip || ! $this->settings->get( 'anonymize_ip' ) ) {
			return $ip;
		}

		return $this->anonymize( $ip );
	}

	/**
	 * Azzera la parte finale dell'indirizzo: ultimo ottetto per IPv4 (/24), ultimi 64 bit per IPv6 (/64).
	 *
	 * @param string $ip Indirizzo IP valido.
	 * @return string|null Indirizzo anonimizzato, null se l'input non è valido.
	 */
	public function anonymize( string $ip ): ?string {
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			return null;
		}

		$packed = 4 === strlen( $packed )
			? substr( $packed, 0, 3 ) . "\0"
			: substr( $packed, 0, 8 ) . str_repeat( "\0", 8 );

		$result = inet_ntop( $packed );

		return false === $result ? null : $result;
	}
}
