<?php
/**
 * Protezione degli estratti automatici dai blocchi riservati.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Blocks;

/**
 * Evita che il testo di un blocco riservato finisca in un estratto automatico.
 *
 * Il problema: per i blocchi contenitore (Group, Columns, Column, List) WordPress
 * genera l'estratto con `_excerpt_render_inner_blocks()`, che chiama `render_block()`
 * solo sui figli e mai sul contenitore. Il filtro `render_block` non scatta, quindi un
 * Group riservato esporrebbe il suo testo in archivi, ricerca, feed, `excerpt.rendered`
 * della REST API e meta description.
 *
 * La soluzione: prima di `wp_trim_excerpt()` si tolgono dal contenuto i blocchi non
 * visibili all'utente (a ogni livello) e l'estratto si calcola sul contenuto ripulito.
 */
class ExcerptGuard {

	/**
	 * Servizio di visibilità dei blocchi.
	 *
	 * @var BlockVisibility
	 */
	private BlockVisibility $visibility;

	/**
	 * True mentre si sta calcolando un estratto: evita la ricorsione.
	 *
	 * I filtri di `the_content` eseguiti da wp_trim_excerpt() potrebbero chiamare
	 * a loro volta get_the_excerpt() e rientrare qui all'infinito.
	 *
	 * @var bool
	 */
	private bool $busy = false;

	/**
	 * Costruttore.
	 *
	 * @param BlockVisibility $visibility Servizio di visibilità dei blocchi.
	 */
	public function __construct( BlockVisibility $visibility ) {
		$this->visibility = $visibility;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priorità 9: prima di wp_trim_excerpt() (priorità 10), che genera l'estratto automatico.
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 9, 2 );
	}

	/**
	 * Calcola l'estratto automatico sul contenuto privo dei blocchi non visibili.
	 *
	 * @param string   $excerpt Estratto scritto a mano (vuoto se va generato in automatico).
	 * @param \WP_Post $post    Contenuto di cui serve l'estratto.
	 * @return string
	 */
	public function filter_excerpt( string $excerpt, \WP_Post $post ): string {
		// Un estratto scritto a mano è una scelta dell'autore: non si tocca.
		if ( $this->busy || '' !== trim( $excerpt ) ) {
			return $excerpt;
		}

		// Nessun blocco con visibilità impostata: lascia fare a WordPress.
		if ( ! str_contains( $post->post_content, '"' . BlockVisibility::ATTR_VISIBILITY . '"' ) ) {
			return $excerpt;
		}

		$blocks = array();

		foreach ( parse_blocks( $post->post_content ) as $block ) {
			$pruned = $this->prune( $block );

			if ( null !== $pruned ) {
				$blocks[] = $pruned;
			}
		}

		// Copia del post con il contenuto ripulito: wp_trim_excerpt() legge il contenuto da qui.
		// Il filtro 'raw' impedisce a WP_Post::filter() di restituire l'istanza originale dal database.
		$clone               = clone $post;
		$clone->post_content = serialize_blocks( $blocks );
		$clone->filter       = 'raw';

		$this->busy = true;

		try {
			return wp_trim_excerpt( '', $clone );
		} finally {
			$this->busy = false;
		}
	}

	/**
	 * Toglie da un blocco, in modo ricorsivo, i blocchi non visibili all'utente.
	 *
	 * @param array<string,mixed> $block Blocco parsato.
	 * @return array<string,mixed>|null Il blocco ripulito, oppure null se va rimosso del tutto.
	 */
	private function prune( array $block ): ?array {
		if ( ! $this->visibility->is_visible( $block ) ) {
			return null;
		}

		if ( empty( $block['innerBlocks'] ) ) {
			return $block;
		}

		$inner_blocks  = array();
		$inner_content = array();
		$index         = 0;

		// innerContent alterna frammenti di HTML e `null`: ogni null è il posto di un blocco figlio.
		// Se il figlio viene rimosso va tolto anche il suo segnaposto, altrimenti la serializzazione si rompe.
		foreach ( $block['innerContent'] as $chunk ) {
			if ( null !== $chunk ) {
				$inner_content[] = $chunk;
				continue;
			}

			$pruned = $this->prune( $block['innerBlocks'][ $index++ ] );

			if ( null !== $pruned ) {
				$inner_blocks[]  = $pruned;
				$inner_content[] = null;
			}
		}

		$block['innerBlocks']  = $inner_blocks;
		$block['innerContent'] = $inner_content;

		return $block;
	}
}
