<?php
/**
 * Protezione dei contenuti riservati nella REST API.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

use WPSimpleAccessControl\Support\PostTypes;

/**
 * Impedisce di leggere dalla REST API ciò che il gate nasconde sul frontend:
 * - singolo contenuto: 403 se l'utente non può vederlo;
 * - collezioni: i contenuti non visibili vengono esclusi dalla query.
 *
 * Il contesto `edit` resta aperto a chi può modificare il contenuto: l'editor
 * di blocchi lo usa per aprire la pagina, anche se l'autore non rientra nei
 * ruoli ammessi alla lettura.
 */
class RestGate {

	/**
	 * Verifica di accesso ai contenuti.
	 *
	 * @var ContentAccess
	 */
	private ContentAccess $access;

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
		add_filter( 'rest_request_before_callbacks', array( $this, 'guard_single' ), 10, 3 );

		// Priorità 30: dopo la registrazione dei CPT, per conoscere i post type supportati.
		add_action( 'init', array( $this, 'register_collection_filters' ), 30 );
	}

	/**
	 * Aggancia il filtro delle collezioni a ogni post type supportato.
	 *
	 * @return void
	 */
	public function register_collection_filters(): void {
		foreach ( PostTypes::supported() as $post_type ) {
			add_filter( "rest_{$post_type}_query", array( $this, 'filter_collection' ), 10, 2 );
		}
	}

	/**
	 * Blocca la lettura di un singolo contenuto non visibile all'utente.
	 *
	 * @param mixed            $response Risposta già prodotta (o null).
	 * @param array<mixed>     $handler  Handler della rotta (callback, permission_callback...).
	 * @param \WP_REST_Request $request  Richiesta corrente.
	 * @return mixed La risposta invariata oppure un WP_Error 403.
	 */
	public function guard_single( $response, array $handler, \WP_REST_Request $request ) {
		$callback = $handler['callback'] ?? null;

		// Solo la lettura di un singolo contenuto (GET /wp/v2/<tipo>/<id>).
		if ( ! is_array( $callback ) || ! $callback[0] instanceof \WP_REST_Posts_Controller || 'get_item' !== ( $callback[1] ?? '' ) ) {
			return $response;
		}

		$post_id = (int) $request->get_param( 'id' );

		if ( $this->access->can_view( $post_id ) || $this->can_edit_in_editor( $post_id, $request ) ) {
			return $response;
		}

		return new \WP_Error(
			'wpsac_forbidden',
			__( 'You are not allowed to view this content.', 'wp-simple-access-control' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Esclude dalle collezioni i contenuti che l'utente non può vedere.
	 *
	 * @param array<string,mixed> $args    Argomenti di WP_Query.
	 * @param \WP_REST_Request    $request Richiesta corrente.
	 * @return array<string,mixed>
	 */
	public function filter_collection( array $args, \WP_REST_Request $request ): array {
		$hidden = array_filter(
			$this->access->hidden_ids(),
			fn( int $post_id ): bool => ! $this->can_edit_in_editor( $post_id, $request )
		);

		// Gestisce anche `?include=` (post__in), che altrimenti farebbe ignorare l'esclusione.
		return $this->access->exclude_from_query( $args, array_values( $hidden ) );
	}

	/**
	 * Vero se la richiesta è in contesto `edit` e l'utente può modificare il contenuto.
	 *
	 * @param int              $post_id ID del contenuto.
	 * @param \WP_REST_Request $request Richiesta corrente.
	 * @return bool
	 */
	private function can_edit_in_editor( int $post_id, \WP_REST_Request $request ): bool {
		return 'edit' === $request->get_param( 'context' ) && current_user_can( 'edit_post', $post_id );
	}
}
