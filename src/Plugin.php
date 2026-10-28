<?php
/**
 * Bootstrap principale del plugin.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl;

use WPSimpleAccessControl\Admin\BlockEditor\PanelRegistrar;
use WPSimpleAccessControl\Admin\BlockEditor\RestController;
use WPSimpleAccessControl\Admin\BlockEditor\RuleSynchronizer;
use WPSimpleAccessControl\Infrastructure\Container;
use WPSimpleAccessControl\Infrastructure\Database\Migrations;
use WPSimpleAccessControl\Infrastructure\Database\Schema;
use WPSimpleAccessControl\Infrastructure\Repository\AccessLogRepository;
use WPSimpleAccessControl\Infrastructure\Repository\AccessRuleRepository;

/**
 * Punto di ingresso: registra i servizi del plugin.
 *
 * I servizi (gate, pannello editor, admin...) vengono aggiunti negli step
 * successivi della roadmap.
 */
final class Plugin {

	/**
	 * Istanza unica del plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Evita che il bootstrap venga eseguito più di una volta.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Container dei servizi.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Costruttore privato: usare Plugin::instance().
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Container dei servizi del plugin.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Restituisce l'istanza unica del plugin.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registra i servizi del plugin (idempotente).
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Servizi condivisi: repository registrati lazy nel container.
		$this->container->set( AccessRuleRepository::class, static fn(): AccessRuleRepository => new AccessRuleRepository() );
		$this->container->set( AccessLogRepository::class, static fn(): AccessLogRepository => new AccessLogRepository() );

		// Aggiorna lo schema se la versione installata è diversa (es. dopo un update del plugin).
		Migrations::maybe_upgrade();

		// Multisite: crea le tabelle nei nuovi siti se il plugin è attivo sulla rete.
		add_action( 'wp_initialize_site', array( Schema::class, 'on_new_site' ), 20 );

		// Pannello Gutenberg: meta REST, sincronizzazione con la tabella ed endpoint dei ruoli.
		$this->container->set( PanelRegistrar::class, static fn(): PanelRegistrar => new PanelRegistrar() );
		$this->container->set( RestController::class, static fn(): RestController => new RestController() );
		$this->container->set(
			RuleSynchronizer::class,
			static fn( Container $c ): RuleSynchronizer => new RuleSynchronizer( $c->get( AccessRuleRepository::class ) )
		);

		$this->container->get( PanelRegistrar::class )->register();
		$this->container->get( RestController::class )->register();
		$this->container->get( RuleSynchronizer::class )->register();

		// Carica le traduzioni del plugin.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Carica il text domain dalla cartella /languages.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-simple-access-control',
			false,
			dirname( plugin_basename( WPSAC_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
