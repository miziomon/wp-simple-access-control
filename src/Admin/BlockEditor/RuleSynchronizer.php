<?php
/**
 * Sincronizzazione dei meta dell'editor con la tabella delle regole.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Admin\BlockEditor;

use WPSimpleAccessControl\Domain\AccessRule;
use WPSimpleAccessControl\Infrastructure\Repository\AccessRuleRepository;
use WPSimpleAccessControl\Support\PostTypes;

/**
 * Copia i meta del pannello (`_wpsac_*`) nella tabella `wpsac_access_rules`,
 * che resta l'unica fonte di verità per il gate.
 *
 * Usa `wp_after_insert_post` e non `save_post`: con il Block Editor i meta
 * vengono scritti dalla REST API *dopo* `save_post`, che leggerebbe valori vecchi.
 * `wp_after_insert_post` viene invece riemesso dalla REST API a meta aggiornati.
 */
final class RuleSynchronizer {

	/**
	 * Repository delle regole.
	 *
	 * @var AccessRuleRepository
	 */
	private AccessRuleRepository $rules;

	/**
	 * Costruttore.
	 *
	 * @param AccessRuleRepository $rules Repository delle regole.
	 */
	public function __construct( AccessRuleRepository $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_after_insert_post', array( $this, 'sync' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'on_deleted' ) );
	}

	/**
	 * Allinea la regola del contenuto ai meta appena salvati.
	 *
	 * @param int      $post_id ID del contenuto.
	 * @param \WP_Post $post    Oggetto del contenuto.
	 * @return void
	 */
	public function sync( int $post_id, \WP_Post $post ): void {
		// Revisioni e autosalvataggi non hanno regole proprie.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! PostTypes::is_supported( $post->post_type ) ) {
			return;
		}

		// Se il pannello non ha mai scritto nulla non c'è niente da sincronizzare.
		if ( ! metadata_exists( 'post', $post_id, PanelRegistrar::META_ENABLED ) ) {
			return;
		}

		$enabled = (bool) get_post_meta( $post_id, PanelRegistrar::META_ENABLED, true );

		// Tiene solo i ruoli che esistono davvero sul sito.
		$roles = get_post_meta( $post_id, PanelRegistrar::META_ROLES, true );
		$roles = is_array( $roles ) ? array_values( array_intersect( $roles, array_keys( wp_roles()->get_names() ) ) ) : array();

		$message = (string) get_post_meta( $post_id, PanelRegistrar::META_LOGIN_MESSAGE, true );

		$this->rules->save(
			new AccessRule(
				$post_id,
				$enabled,
				$roles,
				'' === $message ? null : $message
			)
		);
	}

	/**
	 * Elimina la regola quando il contenuto viene cancellato definitivamente.
	 *
	 * @param int $post_id ID del contenuto eliminato.
	 * @return void
	 */
	public function on_deleted( int $post_id ): void {
		$this->rules->delete_by_post_id( $post_id );
	}
}
