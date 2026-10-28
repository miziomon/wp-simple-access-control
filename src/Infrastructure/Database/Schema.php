<?php
/**
 * Creazione e rimozione delle tabelle custom del plugin.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Infrastructure\Database;

/**
 * Gestisce lo schema del database (per-sito, anche in multisite).
 *
 * Le tabelle usano `$wpdb->prefix`, quindi in multisite ogni sito ha le sue
 * (es. `wp_2_wpsac_access_rules`).
 */
final class Schema {

	/**
	 * Versione corrente dello schema: incrementarla a ogni modifica delle tabelle.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Nome dell'opzione che memorizza la versione dello schema installato.
	 */
	public const OPTION_DB_VERSION = 'wpsac_db_version';

	/**
	 * Nome completo della tabella delle regole di accesso del sito corrente.
	 *
	 * @return string
	 */
	public static function rules_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpsac_access_rules';
	}

	/**
	 * Nome completo della tabella del registro accessi del sito corrente.
	 *
	 * @return string
	 */
	public static function logs_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpsac_access_logs';
	}

	/**
	 * Crea o aggiorna le tabelle (via dbDelta) per un sito.
	 *
	 * @param int|null $blog_id ID del sito; null = sito corrente.
	 * @return void
	 */
	public static function install( ?int $blog_id = null ): void {
		$switched = self::switch_to( $blog_id );

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$rules           = self::rules_table();
		$logs            = self::logs_table();

		// Regole di accesso: una riga per contenuto protetto.
		// Nota dbDelta: due spazi dopo PRIMARY KEY, un campo per riga, KEY e non INDEX.
		$sql_rules = "CREATE TABLE {$rules} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 0,
			roles text NOT NULL,
			login_message text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id)
		) {$charset_collate};";

		// Registro accessi: IP salvato in binario (inet_pton) per supportare IPv4 e IPv6.
		$sql_logs = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NULL,
			ip_address varbinary(16) NULL,
			user_agent varchar(500) NULL,
			outcome varchar(32) NOT NULL,
			referer varchar(500) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_created (post_id,created_at),
			KEY user_created (user_id,created_at),
			KEY outcome (outcome),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_rules );
		dbDelta( $sql_logs );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );

		self::restore( $switched );
	}

	/**
	 * Elimina le tabelle e la versione dello schema di un sito.
	 *
	 * @param int|null $blog_id ID del sito; null = sito corrente.
	 * @return void
	 */
	public static function uninstall( ?int $blog_id = null ): void {
		$switched = self::switch_to( $blog_id );

		global $wpdb;

		// I nomi delle tabelle sono costruiti internamente, non arrivano da input.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::rules_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::logs_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		delete_option( self::OPTION_DB_VERSION );

		self::restore( $switched );
	}

	/**
	 * Gestisce l'attivazione del plugin (anche network-wide in multisite).
	 *
	 * @param bool $network_wide True se attivato per tutta la rete.
	 * @return void
	 */
	public static function activate( bool $network_wide ): void {
		if ( is_multisite() && $network_wide ) {
			// Crea le tabelle in tutti i siti esistenti.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				self::install( (int) $site_id );
			}
			return;
		}

		self::install();
	}

	/**
	 * Crea le tabelle in un sito appena creato (hook `wp_initialize_site`).
	 *
	 * Interviene solo se il plugin è attivo a livello di rete: negli altri
	 * casi lo schema viene creato dall'attivazione sul singolo sito.
	 *
	 * @param \WP_Site $new_site Sito appena creato.
	 * @return void
	 */
	public static function on_new_site( \WP_Site $new_site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active_for_network( plugin_basename( WPSAC_PLUGIN_FILE ) ) ) {
			self::install( (int) $new_site->blog_id );
		}
	}

	/**
	 * Aggiunge le tabelle del plugin a quelle che WordPress elimina insieme a un sito (filtro `wpmu_drop_tables`).
	 *
	 * Il core elimina solo le proprie tabelle: senza questo filtro, cancellando un sito
	 * della rete le tabelle del plugin resterebbero orfane nel database.
	 *
	 * @param array<int|string,string> $tables  Tabelle che verranno eliminate.
	 * @param int                      $site_id ID del sito che si sta eliminando.
	 * @return array<int|string,string>
	 */
	public static function drop_tables_with_site( array $tables, int $site_id ): array {
		global $wpdb;

		$prefix = $wpdb->get_blog_prefix( $site_id );

		$tables[] = $prefix . 'wpsac_access_rules';
		$tables[] = $prefix . 'wpsac_access_logs';

		return $tables;
	}

	/**
	 * Passa al sito indicato, se diverso da quello corrente.
	 *
	 * @param int|null $blog_id ID del sito.
	 * @return bool True se è stato fatto lo switch (da ripristinare con restore()).
	 */
	private static function switch_to( ?int $blog_id ): bool {
		if ( null === $blog_id || ! is_multisite() || get_current_blog_id() === $blog_id ) {
			return false;
		}

		switch_to_blog( $blog_id );

		return true;
	}

	/**
	 * Ripristina il sito originale dopo switch_to().
	 *
	 * @param bool $switched Valore restituito da switch_to().
	 * @return void
	 */
	private static function restore( bool $switched ): void {
		if ( $switched ) {
			restore_current_blog();
		}
	}
}
