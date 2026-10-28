<?php
/**
 * Registrazione dei meta e degli asset del pannello Gutenberg.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Admin\BlockEditor;

use WPSimpleAccessControl\Support\PostTypes;

/**
 * Collega il pannello dell'editor a WordPress:
 * - registra i post meta esposti in REST (binding con il Block Editor);
 * - carica lo script e gli stili dell'editor.
 *
 * I meta servono solo come ponte con l'editor: la fonte di verità delle regole
 * è la tabella custom, aggiornata da RuleSynchronizer.
 */
final class PanelRegistrar {

	/**
	 * Meta: protezione attiva (boolean).
	 */
	public const META_ENABLED = '_wpsac_enabled';

	/**
	 * Meta: slug dei ruoli ammessi (array di stringhe).
	 */
	public const META_ROLES = '_wpsac_roles';

	/**
	 * Meta: messaggio personalizzato sopra il form di login (stringa).
	 */
	public const META_LOGIN_MESSAGE = '_wpsac_login_message';

	/**
	 * Handle dello script e dello stile dell'editor.
	 */
	private const HANDLE = 'wpsac-editor';

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priorità 20: dopo la registrazione dei CPT (di solito su init, priorità 10).
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_canvas_assets' ) );
	}

	/**
	 * Carica lo stile dell'indicatore dentro il canvas dell'editor.
	 *
	 * Il canvas è un iframe: gli stili aggiunti con `enqueue_block_editor_assets` restano fuori
	 * e non raggiungono i blocchi. Quelli del contenuto vanno caricati con `enqueue_block_assets`.
	 *
	 * @return void
	 */
	public function enqueue_canvas_assets(): void {
		$asset_file = WPSAC_PLUGIN_DIR . 'build/editor-canvas.asset.php';

		// Solo nell'editor: sul frontend l'indicatore non serve.
		if ( ! is_admin() || ! is_readable( $asset_file ) || ! is_readable( WPSAC_PLUGIN_DIR . 'build/editor-canvas.css' ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style( 'wpsac-editor-canvas', WPSAC_PLUGIN_URL . 'build/editor-canvas.css', array(), $asset['version'] );
	}

	/**
	 * Registra i post meta per ogni post type supportato.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		/*
		 * I meta con prefisso "_" sono protetti: senza auth_callback la REST API
		 * non permetterebbe all'editor di scriverli. Basta poter modificare il post.
		 */
		$auth_callback = static fn( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id );

		$definitions = array(
			self::META_ENABLED       => array(
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			self::META_ROLES         => array(
				'type'              => 'array',
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'sanitize_callback' => static fn( $roles ): array => is_array( $roles ) ? array_values( array_filter( array_map( 'sanitize_key', array_filter( $roles, 'is_string' ) ) ) ) : array(),
			),
			self::META_LOGIN_MESSAGE => array(
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);

		foreach ( PostTypes::supported() as $post_type ) {
			foreach ( $definitions as $meta_key => $args ) {
				register_post_meta(
					$post_type,
					$meta_key,
					$args + array(
						'single'        => true,
						'auth_callback' => $auth_callback,
					)
				);
			}
		}
	}

	/**
	 * Carica script e stili dell'editor (solo se la build esiste).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$asset_file = WPSAC_PLUGIN_DIR . 'build/editor.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		// Dipendenze e versione generate da @wordpress/scripts.
		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			WPSAC_PLUGIN_URL . 'build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Stili generati dagli import SCSS dell'entry editor (opzionali).
		if ( is_readable( WPSAC_PLUGIN_DIR . 'build/editor.css' ) ) {
			wp_enqueue_style(
				self::HANDLE,
				WPSAC_PLUGIN_URL . 'build/editor.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}

		// Traduzioni JS (file JSON generati da `wp i18n make-json`).
		wp_set_script_translations( self::HANDLE, 'wp-simple-access-control', WPSAC_PLUGIN_DIR . 'languages' );

		// Dati per lo script: post type supportati e link al registro accessi.
		$data = array(
			'postTypes' => PostTypes::supported(),
			'logsUrl'   => current_user_can( 'manage_options' ) ? admin_url( 'tools.php?page=wpsac-logs' ) : '',
		);

		wp_add_inline_script( self::HANDLE, 'window.wpsacEditor = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
