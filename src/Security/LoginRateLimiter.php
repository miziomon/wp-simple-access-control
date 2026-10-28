<?php
/**
 * Limitazione dei tentativi di login.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Security;

use WPSimpleAccessControl\Support\Settings;

/**
 * Rate limiting dei login falliti, per indirizzo IP, basato su transient.
 *
 * Dopo `rate_limit_attempts` tentativi falliti entro `rate_limit_window` secondi
 * l'IP viene bloccato per `rate_limit_lockout` secondi: il tentativo successivo
 * (il N+1°) viene rifiutato senza nemmeno controllare le credenziali.
 * Con `rate_limit_attempts` = 0 il limite è disattivato.
 */
class LoginRateLimiter {

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Risoluzione dell'IP.
	 *
	 * @var IpResolver
	 */
	private IpResolver $ip;

	/**
	 * Costruttore.
	 *
	 * @param Settings   $settings Impostazioni globali.
	 * @param IpResolver $ip       Risoluzione dell'IP.
	 */
	public function __construct( Settings $settings, IpResolver $ip ) {
		$this->settings = $settings;
		$this->ip       = $ip;
	}

	/**
	 * Indica se l'IP corrente è attualmente bloccato.
	 *
	 * @return bool
	 */
	public function is_limited(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		return false !== get_transient( $this->key( 'lock' ) );
	}

	/**
	 * Registra un tentativo fallito e, se si supera la soglia, blocca l'IP.
	 *
	 * @return void
	 */
	public function record_failure(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$window = max( 1, (int) $this->settings->get( 'rate_limit_window' ) );
		$key    = $this->key( 'count' );
		$now    = time();

		// Finestra fissa: il contatore conserva l'istante del primo errore per non allungare la scadenza.
		$data = get_transient( $key );
		$data = is_array( $data ) && isset( $data['count'], $data['start'] ) ? $data : array(
			'count' => 0,
			'start' => $now,
		);

		++$data['count'];

		if ( $data['count'] >= (int) $this->settings->get( 'rate_limit_attempts' ) ) {
			set_transient( $this->key( 'lock' ), 1, max( 1, (int) $this->settings->get( 'rate_limit_lockout' ) ) );
			delete_transient( $key );
			return;
		}

		set_transient( $key, $data, max( 1, $window - ( $now - (int) $data['start'] ) ) );
	}

	/**
	 * Azzera il contatore dopo un login riuscito.
	 *
	 * @return void
	 */
	public function clear(): void {
		delete_transient( $this->key( 'count' ) );
	}

	/**
	 * Il limite è attivo solo con una soglia positiva e un IP noto.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return (int) $this->settings->get( 'rate_limit_attempts' ) > 0 && null !== $this->ip->client_ip();
	}

	/**
	 * Chiave del transient: contiene solo l'hash dell'IP, mai l'IP in chiaro.
	 *
	 * @param string $type `count` (contatore) oppure `lock` (blocco attivo).
	 * @return string
	 */
	private function key( string $type ): string {
		return 'wpsac_rl_' . $type . '_' . md5( (string) $this->ip->client_ip() );
	}
}
