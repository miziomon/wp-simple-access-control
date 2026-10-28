<?php
/**
 * Eliminazione periodica delle voci più vecchie del registro.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Logging;

use WPSimpleAccessControl\Infrastructure\Repository\AccessLogRepository;
use WPSimpleAccessControl\Support\Settings;

/**
 * Applica l'opzione `log_retention_days` con un evento giornaliero di WP-Cron.
 * Con 0 giorni (default) il registro non viene mai svuotato.
 */
class LogRetention {

	/**
	 * Nome dell'evento di WP-Cron.
	 */
	public const HOOK = 'wpsac_purge_logs';

	/**
	 * Repository del registro.
	 *
	 * @var AccessLogRepository
	 */
	private AccessLogRepository $logs;

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Costruttore.
	 *
	 * @param AccessLogRepository $logs     Repository del registro.
	 * @param Settings            $settings Impostazioni globali.
	 */
	public function __construct( AccessLogRepository $logs, Settings $settings ) {
		$this->logs     = $logs;
		$this->settings = $settings;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'purge' ) );
	}

	/**
	 * Pianifica l'evento giornaliero se non esiste già.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Elimina le voci più vecchie del periodo di conservazione.
	 *
	 * @return void
	 */
	public function purge(): void {
		$this->logs->delete_older_than( (int) $this->settings->get( 'log_retention_days' ) );
	}

	/**
	 * Rimuove l'evento pianificato (alla disattivazione del plugin).
	 *
	 * Con la disattivazione di rete l'evento va tolto da ogni sito: il cron è per sito.
	 *
	 * @param bool $network_wide True se il plugin è disattivato per tutta la rete.
	 * @return void
	 */
	public static function unschedule( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				wp_clear_scheduled_hook( self::HOOK );
				restore_current_blog();
			}
			return;
		}

		wp_clear_scheduled_hook( self::HOOK );
	}
}
