<?php
/**
 * Asset del frontend.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

/**
 * Carica il CSS del frontend (form di login, teaser) solo quando serve.
 */
final class Assets {

	/**
	 * Handle dello stile.
	 */
	private const HANDLE = 'wpsac-frontend';

	/**
	 * Mette in coda il CSS del frontend (solo se la build esiste).
	 *
	 * Si può chiamare più volte: WordPress non carica due volte lo stesso handle.
	 * Se chiamata a pagina già avviata, lo stile viene stampato nel footer.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$asset_file = WPSAC_PLUGIN_DIR . 'build/frontend.asset.php';

		if ( ! is_readable( $asset_file ) || ! is_readable( WPSAC_PLUGIN_DIR . 'build/frontend.css' ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style( self::HANDLE, WPSAC_PLUGIN_URL . 'build/frontend.css', array(), $asset['version'] );
	}
}
