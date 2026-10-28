<?php
/**
 * Visibilità dei singoli blocchi per utente e ruolo.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Blocks;

use WPSimpleAccessControl\Domain\AccessPolicy;
use WPSimpleAccessControl\Frontend\CacheHeaders;

/**
 * Aggiunge a tutti i blocchi quattro attributi di visibilità e li applica al rendering.
 *
 * Il plugin non registra blocchi propri: gli attributi vengono aggiunti ai
 * blocchi esistenti (lato PHP qui, lato editor in src/js/editor/blocks).
 * Per proteggere una sezione intera si imposta la visibilità su un blocco
 * contenitore (Group, Columns...): se il contenitore è nascosto non viene
 * renderizzato nulla di ciò che contiene.
 */
class BlockVisibility {

	/**
	 * Attributo: modalità di visibilità (all, logged_in, logged_out).
	 */
	public const ATTR_VISIBILITY = 'wpsacVisibility';

	/**
	 * Attributo: slug dei ruoli ammessi (solo con `logged_in`).
	 */
	public const ATTR_ROLES = 'wpsacRoles';

	/**
	 * Attributo: mostra un messaggio al posto del blocco nascosto.
	 */
	public const ATTR_SHOW_TEASER = 'wpsacShowTeaser';

	/**
	 * Attributo: testo del messaggio.
	 */
	public const ATTR_TEASER_MESSAGE = 'wpsacTeaserMessage';

	/**
	 * Policy di accesso.
	 *
	 * @var AccessPolicy
	 */
	private AccessPolicy $policy;

	/**
	 * Rendering del teaser.
	 *
	 * @var TeaserRenderer
	 */
	private TeaserRenderer $teaser;

	/**
	 * Header anti-cache.
	 *
	 * @var CacheHeaders
	 */
	private CacheHeaders $cache;

	/**
	 * Costruttore.
	 *
	 * @param AccessPolicy   $policy Policy di accesso.
	 * @param TeaserRenderer $teaser Rendering del teaser.
	 * @param CacheHeaders   $cache  Header anti-cache.
	 */
	public function __construct( AccessPolicy $policy, TeaserRenderer $teaser, CacheHeaders $cache ) {
		$this->policy = $policy;
		$this->teaser = $teaser;
		$this->cache  = $cache;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		// Gli attributi vanno registrati prima di `init`, quando i blocchi core si registrano:
		// per questo il servizio viene avviato su plugins_loaded.
		add_filter( 'register_block_type_args', array( $this, 'add_attributes' ) );
		add_filter( 'render_block', array( $this, 'filter_block' ), 10, 2 );

		// Priorità 2: subito dopo il gate dei contenuti interi.
		add_action( 'template_redirect', array( $this, 'maybe_disable_cache' ), 2 );
	}

	/**
	 * Aggiunge gli attributi di visibilità alla definizione di ogni blocco.
	 *
	 * Senza questa registrazione lato server, l'anteprima dei blocchi dinamici
	 * nell'editor (endpoint block-renderer) rifiuterebbe gli attributi sconosciuti.
	 *
	 * @param array<string,mixed> $args Argomenti di registrazione del blocco.
	 * @return array<string,mixed>
	 */
	public function add_attributes( array $args ): array {
		// Come lato editor: i blocchi senza attributi non vengono toccati.
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			return $args;
		}

		// L'operatore + non sovrascrive eventuali attributi con lo stesso nome già definiti.
		$args['attributes'] += array(
			self::ATTR_VISIBILITY     => array(
				'type'    => 'string',
				'default' => AccessPolicy::MODE_ALL,
			),
			self::ATTR_ROLES          => array(
				'type'    => 'array',
				'items'   => array( 'type' => 'string' ),
				'default' => array(),
			),
			self::ATTR_SHOW_TEASER    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			self::ATTR_TEASER_MESSAGE => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		return $args;
	}

	/**
	 * Applica la visibilità al rendering di un blocco.
	 *
	 * @param string              $content Output HTML del blocco.
	 * @param array<string,mixed> $block   Blocco parsato.
	 * @return string Il blocco, il teaser al suo posto oppure stringa vuota.
	 */
	public function filter_block( string $content, array $block ): string {
		$mode = $this->mode_of( $block );

		// Caso più frequente: nessuna restrizione, nessun costo aggiuntivo.
		if ( AccessPolicy::MODE_ALL === $mode ) {
			return $content;
		}

		// L'output dipende dall'utente: la pagina non deve finire in una cache pubblica.
		// Nei block theme i blocchi sono renderizzati prima dell'output, quindi gli header partono ancora.
		$this->cache->apply();

		if ( $this->is_visible( $block ) ) {
			return $content;
		}

		/**
		 * Scatta quando un blocco viene nascosto all'utente corrente.
		 *
		 * Gli accessi negati ai blocchi non vengono scritti nel log (una riga per blocco
		 * a ogni visita sarebbe ingestibile): chi lo desidera può farlo da qui.
		 *
		 * @param array<string,mixed> $block Blocco parsato.
		 * @param string              $mode  Modalità di visibilità del blocco.
		 */
		do_action( 'wpsac_block_hidden', $block, $mode );

		return $this->teaser->render( $block, $mode );
	}

	/**
	 * Indica se un blocco (con la sua configurazione) è visibile all'utente corrente.
	 *
	 * Non guarda i blocchi figli: vale solo per il blocco passato.
	 *
	 * @param array<string,mixed> $block Blocco parsato.
	 * @return bool
	 */
	public function is_visible( array $block ): bool {
		$mode = $this->mode_of( $block );

		if ( AccessPolicy::MODE_ALL === $mode ) {
			return true;
		}

		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$roles = is_array( $attrs[ self::ATTR_ROLES ] ?? null ) ? array_values( array_filter( $attrs[ self::ATTR_ROLES ], 'is_string' ) ) : array();

		$visible = $this->policy->can_view_block( $mode, $roles, wp_get_current_user() );

		/**
		 * Filtra la visibilità di un blocco per l'utente corrente.
		 *
		 * Permette di aggiungere criteri propri (capability, date, utenti specifici...).
		 *
		 * @param bool                $visible True se il blocco è visibile.
		 * @param array<string,mixed> $block   Blocco parsato.
		 * @param string              $mode    Modalità di visibilità del blocco.
		 */
		return (bool) apply_filters( 'wpsac_block_should_render', $visible, $block, $mode );
	}

	/**
	 * Segna come non cacheabili le pagine il cui contenuto ha blocchi riservati.
	 *
	 * Nei temi classici i blocchi vengono renderizzati quando gli header sono già partiti,
	 * quindi si guarda il contenuto in anticipo. Pattern sincronizzati e template part
	 * non sono visibili da qui: li copre filter_block() dove possibile.
	 *
	 * @return void
	 */
	public function maybe_disable_cache(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( $post instanceof \WP_Post && str_contains( $post->post_content, '"' . self::ATTR_VISIBILITY . '"' ) ) {
			$this->cache->apply();
		}
	}

	/**
	 * Modalità di visibilità di un blocco ("all" se non impostata).
	 *
	 * Un valore non stringa viene reso invalido, che AccessPolicy tratta come "nascosto".
	 *
	 * @param array<string,mixed> $block Blocco parsato.
	 * @return string
	 */
	private function mode_of( array $block ): string {
		$mode = $block['attrs'][ self::ATTR_VISIBILITY ] ?? AccessPolicy::MODE_ALL;

		return is_string( $mode ) ? $mode : '';
	}
}
