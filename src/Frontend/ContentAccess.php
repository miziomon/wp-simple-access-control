<?php
/**
 * Verifica di accesso ai contenuti, per REST, feed e archivi.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Domain\AccessPolicy;
use WPSimpleAccessControl\Domain\AccessRule;
use WPSimpleAccessControl\Infrastructure\Repository\AccessRuleRepository;

/**
 * Risponde a "questo utente può vedere questo contenuto?" senza una query per contenuto:
 * le regole attive vengono lette una volta sola per richiesta (vedi AccessRuleRepository::find_enabled()).
 */
class ContentAccess {

	/**
	 * Repository delle regole.
	 *
	 * @var AccessRuleRepository
	 */
	private AccessRuleRepository $rules;

	/**
	 * Policy di accesso.
	 *
	 * @var AccessPolicy
	 */
	private AccessPolicy $policy;

	/**
	 * Costruttore.
	 *
	 * @param AccessRuleRepository $rules  Repository delle regole.
	 * @param AccessPolicy         $policy Policy di accesso.
	 */
	public function __construct( AccessRuleRepository $rules, AccessPolicy $policy ) {
		$this->rules  = $rules;
		$this->policy = $policy;
	}

	/**
	 * Indica se il contenuto ha una protezione attiva.
	 *
	 * @param int $post_id ID del contenuto.
	 * @return bool
	 */
	public function is_protected( int $post_id ): bool {
		return null !== $this->rule_for( $post_id );
	}

	/**
	 * Indica se l'utente può vedere il contenuto (true anche se non è protetto).
	 *
	 * @param int           $post_id ID del contenuto.
	 * @param \WP_User|null $user    Utente; null = utente corrente.
	 * @return bool
	 */
	public function can_view( int $post_id, ?\WP_User $user = null ): bool {
		$rule = $this->rule_for( $post_id );

		if ( null === $rule ) {
			return true;
		}

		return AccessOutcome::GRANTED === $this->policy->check( $user ?? wp_get_current_user(), $rule->roles );
	}

	/**
	 * ID dei contenuti protetti che l'utente NON può vedere.
	 *
	 * @param \WP_User|null $user Utente; null = utente corrente.
	 * @return array<int,int>
	 */
	public function hidden_ids( ?\WP_User $user = null ): array {
		$user   = $user ?? wp_get_current_user();
		$hidden = array();

		foreach ( $this->rules->find_enabled() as $post_id => $rule ) {
			if ( AccessOutcome::GRANTED !== $this->policy->check( $user, $rule->roles ) ) {
				$hidden[] = $post_id;
			}
		}

		return $hidden;
	}

	/**
	 * Toglie dagli argomenti di una WP_Query gli ID indicati.
	 *
	 * Non basta riempire `post__not_in`: se la query ha anche `post__in` (la REST API lo imposta
	 * con `?include=`) WP_Query ignora `post__not_in`, e il contenuto nascosto uscirebbe lo stesso.
	 * Quindi gli ID si tolgono anche da `post__in`; se non ne resta nessuno si usa `0`, perché
	 * un array vuoto significherebbe "nessun filtro".
	 *
	 * @param array<string,mixed> $args   Argomenti (o variabili) della query.
	 * @param array<int,int>      $hidden ID da escludere.
	 * @return array<string,mixed> Gli argomenti con `post__in` e `post__not_in` aggiornati.
	 */
	public function exclude_from_query( array $args, array $hidden ): array {
		if ( array() === $hidden ) {
			return $args;
		}

		$included = $this->to_ids( $args['post__in'] ?? null );

		if ( array() !== $included ) {
			$remaining        = array_values( array_diff( $included, $hidden ) );
			$args['post__in'] = array() === $remaining ? array( 0 ) : $remaining;
		}

		$args['post__not_in'] = array_values( array_unique( array_merge( $this->to_ids( $args['post__not_in'] ?? null ), $hidden ) ) );

		return $args;
	}

	/**
	 * Converte un valore di query in un elenco di interi (valori non array o vuoti diventano un array vuoto).
	 *
	 * @param mixed $value Valore di `post__in` o `post__not_in`.
	 * @return array<int,int>
	 */
	private function to_ids( mixed $value ): array {
		return is_array( $value ) ? array_values( array_map( 'intval', $value ) ) : array();
	}

	/**
	 * Regola attiva di un contenuto, se esiste.
	 *
	 * @param int $post_id ID del contenuto.
	 * @return AccessRule|null
	 */
	private function rule_for( int $post_id ): ?AccessRule {
		return $this->rules->find_enabled()[ $post_id ] ?? null;
	}
}
