<?php
/**
 * Plugin Name:       WP Simple Access Control
 * Plugin URI:        https://github.com/miziomon/wp-simple-access-control
 * Description:       Restrict access to single contents and to single blocks by user role, with an inline login form and an access log.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Maurizio Pelizzone
 * Author URI:        https://maurizio.mavida.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-simple-access-control
 * Domain Path:       /languages
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

// Blocca l'accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Costanti principali del plugin.
define( 'WPSAC_VERSION', '0.1.0' );
define( 'WPSAC_PLUGIN_FILE', __FILE__ );
define( 'WPSAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload PSR-4 generato da Composer.
$wpsac_autoload = WPSAC_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! is_readable( $wpsac_autoload ) ) {
	// Senza vendor/ il plugin non può partire: avvisa l'amministratore invece di dare un fatal error.
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'WP Simple Access Control: dependencies are missing. Run "composer install --no-dev" in the plugin folder.', 'wp-simple-access-control' )
			);
		}
	);
	return;
}

require_once $wpsac_autoload;

// Attivazione: crea le tabelle (in multisite anche network-wide, su tutti i siti).
register_activation_hook( WPSAC_PLUGIN_FILE, array( \WPSimpleAccessControl\Infrastructure\Database\Schema::class, 'activate' ) );

// Disattivazione: rimuove l'evento di pulizia periodica del registro.
register_deactivation_hook( WPSAC_PLUGIN_FILE, array( \WPSimpleAccessControl\Logging\LogRetention::class, 'unschedule' ) );

// Avvio del plugin dopo il caricamento di tutti i plugin.
add_action(
	'plugins_loaded',
	static function (): void {
		\WPSimpleAccessControl\Plugin::instance()->boot();
	}
);
