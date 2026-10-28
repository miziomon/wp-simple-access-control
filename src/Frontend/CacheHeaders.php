<?php
/**
 * Header anti-cache per i contenuti protetti.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

/**
 * Impedisce che la pagina protetta (o il form di login al suo posto) finisca
 * in una cache pubblica: la risposta dipende dall'utente che la richiede.
 */
class CacheHeaders {

	/**
	 * True dopo apply(): serve a far aggiungere `Vary: Cookie` da nocache_headers().
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'nocache_headers', array( $this, 'add_vary_cookie' ) );
	}

	/**
	 * Aggiunge `Vary: Cookie` agli header di nocache_headers() quando il gate è attivo.
	 *
	 * @param array<string,string> $headers Header generati da WordPress.
	 * @return array<string,string>
	 */
	public function add_vary_cookie( array $headers ): array {
		if ( $this->active ) {
			$headers['Vary'] = 'Cookie';
		}

		return $headers;
	}

	/**
	 * Segna la risposta come non cacheabile.
	 *
	 * @return void
	 */
	public function apply(): void {
		$this->active = true;

		// Riconosciuta dai principali plugin di page cache (WP Super Cache, W3TC, LiteSpeed...).
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- costante standard dei plugin di cache.
		}

		if ( headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0', true );
	}
}
