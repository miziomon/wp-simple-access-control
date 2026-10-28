<?php
/**
 * Gate dei contenuti protetti.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Frontend;

use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Domain\AccessPolicy;
use WPSimpleAccessControl\Infrastructure\Repository\AccessRuleRepository;
use WPSimpleAccessControl\Logging\AccessLogger;

/**
 * Decide, per ogni pagina singola, se l'utente può vedere il contenuto o se
 * al suo posto va mostrato il form di login.
 */
class Gate {

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
	 * Rendering del form di login.
	 *
	 * @var LoginRenderer
	 */
	private LoginRenderer $login;

	/**
	 * Header anti-cache.
	 *
	 * @var CacheHeaders
	 */
	private CacheHeaders $cache;

	/**
	 * Registro accessi.
	 *
	 * @var AccessLogger
	 */
	private AccessLogger $logger;

	/**
	 * Costruttore.
	 *
	 * @param AccessRuleRepository $rules  Repository delle regole.
	 * @param AccessPolicy         $policy Policy di accesso.
	 * @param LoginRenderer        $login  Rendering del form di login.
	 * @param CacheHeaders         $cache  Header anti-cache.
	 * @param AccessLogger         $logger Registro accessi.
	 */
	public function __construct( AccessRuleRepository $rules, AccessPolicy $policy, LoginRenderer $login, CacheHeaders $cache, AccessLogger $logger ) {
		$this->rules  = $rules;
		$this->policy = $policy;
		$this->login  = $login;
		$this->cache  = $cache;
		$this->logger = $logger;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priorità 1: prima dei redirect canonici e di qualsiasi altro template_redirect.
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}

	/**
	 * Controlla l'accesso al contenuto richiesto.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$rule = $this->rules->find_by_post_id( $post->ID );

		// Nessuna regola o protezione disattivata: il contenuto è pubblico.
		if ( null === $rule || ! $rule->enabled ) {
			return;
		}

		// La risposta dipende dall'utente: mai in una cache pubblica, sia che passi o no.
		$this->cache->apply();

		$outcome = $this->policy->check( wp_get_current_user(), $rule->roles );

		// Ogni richiesta a un contenuto protetto finisce nel registro, con il suo esito.
		$this->logger->log( $post->ID, $outcome );

		if ( AccessOutcome::GRANTED === $outcome ) {
			return;
		}

		$this->login->render( $outcome, $rule, $post );
	}
}
