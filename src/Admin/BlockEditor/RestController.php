<?php
/**
 * Endpoint REST custom usato dall'editor.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Admin\BlockEditor;

/**
 * Espone `GET wpsac/v1/roles`: l'elenco dei ruoli selezionabili.
 *
 * Lo usano sia il pannello del documento sia i controlli per singolo blocco.
 */
final class RestController {

	/**
	 * Namespace delle rotte del plugin.
	 */
	public const NAMESPACE = 'wpsac/v1';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra le rotte.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/roles',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_roles' ),
				'permission_callback' => array( $this, 'can_read_roles' ),
			)
		);
	}

	/**
	 * Solo chi può modificare contenuti vede l'elenco dei ruoli.
	 *
	 * Per gli utenti loggati la REST API con cookie richiede già il nonce `X-WP-Nonce`.
	 *
	 * @return bool
	 */
	public function can_read_roles(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Restituisce i ruoli del sito come coppie slug/nome tradotto.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_roles(): \WP_REST_Response {
		$roles = array();

		foreach ( wp_roles()->roles as $slug => $details ) {
			$roles[] = array(
				'slug' => (string) $slug,
				'name' => translate_user_role( (string) $details['name'] ),
			);
		}

		return rest_ensure_response( $roles );
	}
}
