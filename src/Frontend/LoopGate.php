<?php
/**
 * Protezione dei contenuti riservati negli archivi e nel loop principale.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

/**
 * Negli archivi, nella ricerca e in ogni elenco del loop principale i contenuti
 * che l'utente non può vedere compaiono come estratto più una nota "contenuto riservato".
 *
 * Per default i contenuti restano negli elenchi (rispetta SEO e conteggi). Chi preferisce
 * nasconderli del tutto può attivare il filtro `wpsac_hide_protected_in_loops`.
 *
 * Limite: i loop secondari (WP_Query personalizzate, widget) non passano da qui; per quelli
 * si può usare il filtro sopra oppure `ContentAccess::can_view()`.
 */
class LoopGate {

	/**
	 * Verifica di accesso ai contenuti.
	 *
	 * @var ContentAccess
	 */
	private ContentAccess $access;

	/**
	 * True mentre si calcola l'estratto: evita la ricorsione.
	 *
	 * Il calcolo dell'estratto applica a sua volta `the_content`, che rientrerebbe qui all'infinito.
	 *
	 * @var bool
	 */
	private bool $busy = false;

	/**
	 * Costruttore.
	 *
	 * @param ContentAccess $access Verifica di accesso ai contenuti.
	 */
	public function __construct( ContentAccess $access ) {
		$this->access = $access;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priorità 8: prima di do_blocks() (9), così i blocchi di un contenuto riservato non vengono nemmeno renderizzati.
		add_filter( 'the_content', array( $this, 'filter_content' ), 8 );
		add_action( 'pre_get_posts', array( $this, 'maybe_hide_from_main_query' ) );
	}

	/**
	 * Sostituisce il testo di un contenuto riservato con l'estratto e una nota.
	 *
	 * @param mixed $content Contenuto del post (alcuni plugin chiamano il filtro con null).
	 * @return string
	 */
	public function filter_content( $content ): string {
		$content = (string) $content;

		// Feed e REST hanno i loro gate; nel back office il contenuto serve a chi lo modifica.
		if ( $this->busy || is_feed() || ( is_admin() && ! wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		// Solo elenchi del loop principale: il singolo contenuto è già gestito dal Gate.
		if ( is_singular() || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post || $this->access->can_view( $post->ID ) ) {
			return $content;
		}

		Assets::enqueue();

		$this->busy = true;

		try {
			$excerpt = get_the_excerpt( $post );
		} finally {
			$this->busy = false;
		}

		return wp_kses_post( wpautop( $excerpt ) ) . sprintf(
			'<div class="wpsac-teaser"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'This content is reserved.', 'wp-simple-access-control' ),
			esc_url( get_permalink( $post ) ),
			esc_html__( 'Log in to read more', 'wp-simple-access-control' )
		);
	}

	/**
	 * Se richiesto, toglie dal loop principale i contenuti riservati.
	 *
	 * @param \WP_Query $query Query in preparazione.
	 * @return void
	 */
	public function maybe_hide_from_main_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}

		/**
		 * Permette di nascondere del tutto, dal loop principale, i contenuti riservati.
		 *
		 * @param bool $hide True per escluderli dagli elenchi (default: false).
		 */
		if ( ! apply_filters( 'wpsac_hide_protected_in_loops', false ) ) {
			return;
		}

		$vars = $this->access->exclude_from_query(
			array(
				'post__in'     => $query->get( 'post__in', array() ),
				'post__not_in' => $query->get( 'post__not_in', array() ),
			),
			$this->access->hidden_ids()
		);

		$query->set( 'post__in', $vars['post__in'] ?? array() );
		$query->set( 'post__not_in', $vars['post__not_in'] ?? array() );
	}
}
