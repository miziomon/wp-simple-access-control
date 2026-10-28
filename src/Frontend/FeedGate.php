<?php
/**
 * Protezione dei contenuti riservati nei feed.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

use WPSimpleAccessControl\Support\Settings;

/**
 * Nei feed i contenuti protetti compaiono solo come estratto, con un link al sito.
 *
 * Chi legge un feed non può fare login, quindi vale per tutti. Con l'opzione
 * `feed_show_excerpt` disattivata il feed non riporta nemmeno l'estratto.
 *
 * Nota: l'estratto automatico di un contenuto protetto mostra le sue prime parole.
 * Per non anticipare nulla conviene scrivere a mano un estratto.
 */
class FeedGate {

	/**
	 * Verifica di accesso ai contenuti.
	 *
	 * @var ContentAccess
	 */
	private ContentAccess $access;

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Costruttore.
	 *
	 * @param ContentAccess $access   Verifica di accesso ai contenuti.
	 * @param Settings      $settings Impostazioni globali.
	 */
	public function __construct( ContentAccess $access, Settings $settings ) {
		$this->access   = $access;
		$this->settings = $settings;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'the_content_feed', array( $this, 'filter_content' ) );
		add_filter( 'the_excerpt_rss', array( $this, 'filter_excerpt' ) );
	}

	/**
	 * Sostituisce il testo completo con l'estratto e un link "Read more".
	 *
	 * @param string $content Contenuto del feed.
	 * @return string
	 */
	public function filter_content( string $content ): string {
		$post = get_post();

		if ( ! $post instanceof \WP_Post || ! $this->access->is_protected( $post->ID ) ) {
			return $content;
		}

		if ( ! $this->settings->get( 'feed_show_excerpt' ) ) {
			return '';
		}

		return wp_kses_post( wpautop( get_the_excerpt( $post ) ) ) . $this->read_more_link( $post );
	}

	/**
	 * Aggiunge il link "Read more" all'estratto dei contenuti protetti.
	 *
	 * @param string $excerpt Estratto del feed.
	 * @return string
	 */
	public function filter_excerpt( string $excerpt ): string {
		$post = get_post();

		if ( ! $post instanceof \WP_Post || ! $this->access->is_protected( $post->ID ) ) {
			return $excerpt;
		}

		return $excerpt . $this->read_more_link( $post );
	}

	/**
	 * Link al contenuto (che sul sito mostrerà il form di login).
	 *
	 * @param \WP_Post $post Contenuto del feed.
	 * @return string
	 */
	private function read_more_link( \WP_Post $post ): string {
		return sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( get_permalink( $post ) ),
			esc_html__( 'Read more', 'wp-simple-access-control' )
		);
	}
}
