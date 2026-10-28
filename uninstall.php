<?php
/**
 * Disinstallazione del plugin.
 *
 * Per ora è solo uno scheletro: la rimozione di tabelle e opzioni
 * (controllata da `wpsac_drop_data_on_uninstall`) arriva allo step 10.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

// Eseguibile solo da WordPress durante la disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
