<?php
/**
 * Disinstallazione del plugin.
 *
 * Per default i dati vengono conservati: tabelle, impostazioni e meta si rimuovono solo
 * se nelle impostazioni è attiva l'opzione `wpsac_drop_data_on_uninstall`. In multisite
 * l'opzione è per sito, quindi ogni sito decide per sé.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

// Eseguibile solo da WordPress durante la disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wpsac_autoload = __DIR__ . '/vendor/autoload.php';

// Senza l'autoload non si può usare Schema: meglio non toccare nulla che farlo a metà.
if ( ! is_readable( $wpsac_autoload ) ) {
	return;
}

require_once $wpsac_autoload;

// Rimuove i dati del sito corrente, se ha scelto di farlo.
$wpsac_cleanup_site = static function (): void {
	global $wpdb;

	if ( ! get_option( 'wpsac_drop_data_on_uninstall' ) ) {
		return;
	}

	// Tabelle delle regole e del registro, più la versione dello schema.
	\WPSimpleAccessControl\Infrastructure\Database\Schema::uninstall();

	// Impostazioni.
	delete_option( 'wpsac_settings' );
	delete_option( 'wpsac_drop_data_on_uninstall' );

	// Meta del pannello dell'editor e bandiera "shadow".
	foreach ( array( '_wpsac_protected', '_wpsac_enabled', '_wpsac_roles', '_wpsac_login_message' ) as $wpsac_meta_key ) {
		delete_post_meta_by_key( $wpsac_meta_key );
	}

	// Contatori e blocchi del rate limiter (transient e relative scadenze).
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella di WordPress.
			$wpdb->esc_like( '_transient_wpsac_rl_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wpsac_rl_' ) . '%'
		)
	);

	// Evento di pulizia periodica del registro.
	wp_clear_scheduled_hook( 'wpsac_purge_logs' );
};

if ( is_multisite() ) {
	$wpsac_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wpsac_site_ids as $wpsac_site_id ) {
		switch_to_blog( (int) $wpsac_site_id );
		$wpsac_cleanup_site();
		restore_current_blog();
	}
} else {
	$wpsac_cleanup_site();
}
