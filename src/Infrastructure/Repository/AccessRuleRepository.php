<?php
/**
 * Repository delle regole di accesso.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Infrastructure\Repository;

use WPSimpleAccessControl\Domain\AccessRule;
use WPSimpleAccessControl\Infrastructure\Database\Schema;

/**
 * Legge e scrive la tabella `{prefix}wpsac_access_rules`.
 *
 * È l'unica fonte di verità delle regole: il postmeta `_wpsac_protected`
 * è solo una bandiera leggera tenuta sincronizzata (vedi SHADOW_META_KEY).
 */
class AccessRuleRepository {

	/**
	 * Meta "shadow" ('1'/'0') usata come bandiera leggera per filtrare i loop.
	 */
	public const SHADOW_META_KEY = '_wpsac_protected';

	/**
	 * Cache per richiesta dell'elenco delle regole attive (null = non ancora caricato).
	 *
	 * @var array<int,AccessRule>|null
	 */
	private ?array $enabled_cache = null;

	/**
	 * Cerca la regola di un contenuto.
	 *
	 * @param int $post_id ID del contenuto.
	 * @return AccessRule|null Null se il contenuto non ha una regola.
	 */
	public function find_by_post_id( int $post_id ): ?AccessRule {
		global $wpdb;

		$table = Schema::rules_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, post_id, enabled, roles, login_message FROM {$table} WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella interno.
				$post_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Salva (inserisce o aggiorna) la regola e allinea il meta shadow.
	 *
	 * @param AccessRule $rule Regola da salvare.
	 * @return bool True se il salvataggio è andato a buon fine.
	 */
	public function save( AccessRule $rule ): bool {
		global $wpdb;

		$table = Schema::rules_table();
		$now   = current_time( 'mysql', true );
		$roles = wp_json_encode( array_values( $rule->roles ) );

		$data = array(
			'enabled'       => $rule->enabled ? 1 : 0,
			'roles'         => false === $roles ? '[]' : $roles,
			'login_message' => $rule->login_message,
			'updated_at'    => $now,
		);

		if ( null !== $this->find_by_post_id( $rule->post_id ) ) {
			// Aggiorna la riga esistente senza toccare created_at.
			$result = $wpdb->update( $table, $data, array( 'post_id' => $rule->post_id ), array( '%d', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$data['post_id']    = $rule->post_id;
			$data['created_at'] = $now;
			$result             = $wpdb->insert( $table, $data, array( '%d', '%s', '%s', '%s', '%d', '%s' ) );
		}

		$this->enabled_cache = null;

		if ( false === $result ) {
			return false;
		}

		update_post_meta( $rule->post_id, self::SHADOW_META_KEY, $rule->enabled ? '1' : '0' );

		return true;
	}

	/**
	 * Elimina la regola di un contenuto (e il relativo meta shadow).
	 *
	 * @param int $post_id ID del contenuto.
	 * @return bool True se la query è riuscita.
	 */
	public function delete_by_post_id( int $post_id ): bool {
		global $wpdb;

		$result = $wpdb->delete( Schema::rules_table(), array( 'post_id' => $post_id ), array( '%d' ) );

		$this->enabled_cache = null;

		delete_post_meta( $post_id, self::SHADOW_META_KEY );

		return false !== $result;
	}

	/**
	 * Tutte le regole con protezione attiva, indicizzate per ID del contenuto.
	 *
	 * Una sola query per richiesta: serve a REST, feed e archivi, che altrimenti
	 * dovrebbero interrogare il database per ogni contenuto elencato.
	 *
	 * @return array<int,AccessRule>
	 */
	public function find_enabled(): array {
		if ( null !== $this->enabled_cache ) {
			return $this->enabled_cache;
		}

		global $wpdb;

		$table = Schema::rules_table();

		$rows = $wpdb->get_results( "SELECT id, post_id, enabled, roles, login_message FROM {$table} WHERE enabled = 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella interno, nessun input.

		$this->enabled_cache = array();

		foreach ( (array) $rows as $row ) {
			$rule                                  = $this->hydrate( $row );
			$this->enabled_cache[ $rule->post_id ] = $rule;
		}

		return $this->enabled_cache;
	}

	/**
	 * Elenca gli ID di tutti i contenuti con protezione attiva.
	 *
	 * @return array<int,int>
	 */
	public function find_protected_post_ids(): array {
		global $wpdb;

		$table = Schema::rules_table();

		$ids = $wpdb->get_col( "SELECT post_id FROM {$table} WHERE enabled = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella interno, nessun input.

		return array_map( 'intval', $ids );
	}

	/**
	 * Costruisce l'entità da una riga del database.
	 *
	 * @param array<string,mixed> $row Riga con chiavi id, post_id, enabled, roles, login_message.
	 * @return AccessRule
	 */
	private function hydrate( array $row ): AccessRule {
		$roles = json_decode( (string) $row['roles'], true );

		// Tiene solo slug validi: un JSON corrotto diventa "nessun ruolo specifico".
		$roles = is_array( $roles ) ? array_values( array_filter( array_map( 'sanitize_key', array_filter( $roles, 'is_string' ) ) ) ) : array();

		return new AccessRule(
			(int) $row['post_id'],
			(bool) $row['enabled'],
			$roles,
			null === $row['login_message'] ? null : (string) $row['login_message'],
			(int) $row['id']
		);
	}
}
