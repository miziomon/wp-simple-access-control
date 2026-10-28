<?php
/**
 * Bootstrap di PHPStan: dichiara le costanti che il plugin definisce a runtime.
 *
 * Serve solo all'analisi statica (le costanti reali sono in wp-simple-access-control.php).
 * Non viene mai caricato da WordPress.
 */

define( 'WPSAC_VERSION', '0.0.0' );
define( 'WPSAC_PLUGIN_FILE', __DIR__ . '/wp-simple-access-control.php' );
define( 'WPSAC_PLUGIN_DIR', __DIR__ . '/' );
define( 'WPSAC_PLUGIN_URL', 'http://example.org/wp-content/plugins/wp-simple-access-control/' );
