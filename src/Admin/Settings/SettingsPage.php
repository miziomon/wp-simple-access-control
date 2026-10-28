<?php
/**
 * Pagina delle impostazioni globali.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Admin\Settings;

use WPSimpleAccessControl\Support\Settings;

/**
 * Pagina "Impostazioni > Access Control", costruita con la Settings API.
 *
 * Tutte le impostazioni stanno nell'opzione `wpsac_settings`. I campi sono descritti
 * una volta sola in fields(): lo stesso elenco serve a stamparli e a sanitizzarli.
 * Fa eccezione `wpsac_drop_data_on_uninstall`, un'opzione a parte letta da uninstall.php.
 */
class SettingsPage {

	/**
	 * Slug della pagina.
	 */
	public const SLUG = 'wpsac-settings';

	/**
	 * Nome dell'opzione che dice a uninstall.php se cancellare i dati.
	 */
	public const OPTION_DROP_DATA = 'wpsac_drop_data_on_uninstall';

	/**
	 * Gruppo delle opzioni (settings_fields()).
	 */
	private const GROUP = 'wpsac_settings_group';

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Costruttore.
	 *
	 * @param Settings $settings Impostazioni globali.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPSAC_PLUGIN_FILE ), array( $this, 'add_action_link' ) );
	}

	/**
	 * Aggiunge la voce al menu Impostazioni.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'Simple Access Control', 'wp-simple-access-control' ),
			__( 'Access Control', 'wp-simple-access-control' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Aggiunge il link "Settings" nell'elenco dei plugin.
	 *
	 * @param array<int,string> $links Link esistenti.
	 * @return array<int,string>
	 */
	public function add_action_link( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'wp-simple-access-control' ) )
		);

		return $links;
	}

	/**
	 * Registra opzioni, sezioni e campi.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		register_setting(
			self::GROUP,
			self::OPTION_DROP_DATA,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		foreach ( $this->sections() as $id => $title ) {
			add_settings_section( 'wpsac_' . $id, $title, '__return_empty_string', self::SLUG );
		}

		foreach ( $this->fields() as $key => $field ) {
			add_settings_field(
				'wpsac_' . $key,
				$field['label'],
				array( $this, 'render_field' ),
				self::SLUG,
				'wpsac_' . $field['section'],
				array(
					'key'       => $key,
					'label_for' => 'wpsac-' . $key,
				)
			);
		}

		// L'opzione di disinstallazione non fa parte di wpsac_settings: ha il suo campo.
		add_settings_field(
			'wpsac_' . self::OPTION_DROP_DATA,
			__( 'Delete data on uninstall', 'wp-simple-access-control' ),
			array( $this, 'render_drop_data_field' ),
			self::SLUG,
			'wpsac_advanced'
		);
	}

	/**
	 * Sezioni della pagina (id => titolo).
	 *
	 * @return array<string,string>
	 */
	private function sections(): array {
		return array(
			'login'    => __( 'Login form', 'wp-simple-access-control' ),
			'security' => __( 'Security', 'wp-simple-access-control' ),
			'feed'     => __( 'Feeds', 'wp-simple-access-control' ),
			'advanced' => __( 'Advanced', 'wp-simple-access-control' ),
		);
	}

	/**
	 * Descrizione di tutti i campi di `wpsac_settings`.
	 *
	 * Chiavi di ogni campo: section, label, type (checkbox|number|select|textarea),
	 * description, e a seconda del tipo min/max (number) oppure options (select).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function fields(): array {
		return array(
			'login_form_mode'       => array(
				'section'     => 'login',
				'label'       => __( 'Login form mode', 'wp-simple-access-control' ),
				'type'        => 'select',
				'options'     => array(
					'inline'        => __( 'Inline, inside the theme', 'wp-simple-access-control' ),
					'full_template' => __( 'Standalone page', 'wp-simple-access-control' ),
				),
				'description' => __( 'Inline keeps the header and footer of your theme. The standalone page is a minimal, self-contained login page.', 'wp-simple-access-control' ),
			),
			'enable_remember_me'    => array(
				'section'     => 'login',
				'label'       => __( 'Remember me', 'wp-simple-access-control' ),
				'type'        => 'checkbox',
				'description' => __( 'Show the "Remember me" checkbox.', 'wp-simple-access-control' ),
			),
			'enable_lost_password'  => array(
				'section'     => 'login',
				'label'       => __( 'Lost password', 'wp-simple-access-control' ),
				'type'        => 'checkbox',
				'description' => __( 'Show the "Lost your password?" link.', 'wp-simple-access-control' ),
			),
			'enable_registration'   => array(
				'section'     => 'login',
				'label'       => __( 'Registration', 'wp-simple-access-control' ),
				'type'        => 'checkbox',
				'description' => __( 'Show the "Register" link. It only appears when "Anyone can register" is also enabled in Settings > General.', 'wp-simple-access-control' ),
			),
			'default_login_message' => array(
				'section'     => 'login',
				'label'       => __( 'Default login message', 'wp-simple-access-control' ),
				'type'        => 'textarea',
				'description' => __( 'Shown above the login form. A message set on a single content overrides it. Leave empty to use the built-in text.', 'wp-simple-access-control' ),
			),
			'default_block_teaser'  => array(
				'section'     => 'login',
				'label'       => __( 'Default block message', 'wp-simple-access-control' ),
				'type'        => 'textarea',
				'description' => __( 'Shown in place of a block restricted to logged-in users. Plain text; use [login]link text[/login] for a link to the login page. Leave empty to hide the block without a message.', 'wp-simple-access-control' ),
			),
			'rate_limit_attempts'   => array(
				'section'     => 'security',
				'label'       => __( 'Failed attempts allowed', 'wp-simple-access-control' ),
				'type'        => 'number',
				'min'         => 0,
				'max'         => 100,
				'description' => __( 'After this many failed logins from the same IP, further attempts are refused. Set 0 to disable the limit.', 'wp-simple-access-control' ),
			),
			'rate_limit_window'     => array(
				'section'     => 'security',
				'label'       => __( 'Counting window (seconds)', 'wp-simple-access-control' ),
				'type'        => 'number',
				'min'         => 30,
				'max'         => 86400,
				'description' => __( 'Failed attempts are counted within this time frame.', 'wp-simple-access-control' ),
			),
			'rate_limit_lockout'    => array(
				'section'     => 'security',
				'label'       => __( 'Lockout duration (seconds)', 'wp-simple-access-control' ),
				'type'        => 'number',
				'min'         => 30,
				'max'         => 604800,
				'description' => __( 'How long an IP stays blocked once the limit is reached.', 'wp-simple-access-control' ),
			),
			'anonymize_ip'          => array(
				'section'     => 'security',
				'label'       => __( 'Anonymize IP addresses', 'wp-simple-access-control' ),
				'type'        => 'checkbox',
				'description' => __( 'Store IP addresses in the access log with the last part removed (/24 for IPv4, /64 for IPv6).', 'wp-simple-access-control' ),
			),
			'trust_proxy_headers'   => array(
				'section'     => 'security',
				'label'       => __( 'Trust proxy headers', 'wp-simple-access-control' ),
				'type'        => 'checkbox',
				'description' => __( 'Read the visitor IP from X-Forwarded-For. Enable it only if your site is behind a proxy or CDN that sets this header: otherwise visitors can fake their IP and escape the login limit.', 'wp-simple-access-control' ),
			),
			'feed_show_excerpt'     => array(
				'section'     => 'feed',
				'label'       => __( 'Protected contents in feeds', 'wp-simple-access-control' ),
				'type'        => 'checkbox',
				'description' => __( 'Show the excerpt with a "Read more" link. When off, the feed shows only title and link. The automatic excerpt reveals the first words of the content: write a manual excerpt for protected contents.', 'wp-simple-access-control' ),
			),
			'log_retention_days'    => array(
				'section'     => 'advanced',
				'label'       => __( 'Keep the access log for (days)', 'wp-simple-access-control' ),
				'type'        => 'number',
				'min'         => 0,
				'max'         => 3650,
				'description' => __( 'Older entries are deleted automatically once a day. Set 0 to keep them forever.', 'wp-simple-access-control' ),
			),
			'twofa_integration'     => array(
				'section'     => 'advanced',
				'label'       => __( 'Two-factor authentication', 'wp-simple-access-control' ),
				'type'        => 'select',
				'options'     => array(
					'auto' => __( 'Automatic (detect compatible plugins)', 'wp-simple-access-control' ),
					'off'  => __( 'Off', 'wp-simple-access-control' ),
				),
				'description' => __( 'The login form uses the standard WordPress authentication, so two-factor plugins work as they do on wp-login.php.', 'wp-simple-access-control' ),
			),
		);
	}

	/**
	 * Stampa il controllo di un campo.
	 *
	 * @param array<string,string> $args Argomenti passati da add_settings_field() (key).
	 * @return void
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'];
		$field = $this->fields()[ $key ];
		$value = $this->settings->get( $key );
		$name  = Settings::OPTION . '[' . $key . ']';
		$id    = 'wpsac-' . $key;

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( $field['description'] )
				);
				return;

			case 'number':
				printf(
					'<input type="number" class="small-text" id="%s" name="%s" value="%s" min="%d" max="%d" step="1" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					(int) $field['min'],
					(int) $field['max']
				);
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $option => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'textarea':
				printf( '<textarea class="large-text" rows="3" id="%s" name="%s">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;
		}

		// Descrizione sotto il controllo (per i checkbox è già nell'etichetta).
		printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
	}

	/**
	 * Stampa il campo "Delete data on uninstall".
	 *
	 * @return void
	 */
	public function render_drop_data_field(): void {
		printf(
			'<label for="wpsac-drop-data"><input type="checkbox" id="wpsac-drop-data" name="%s" value="1"%s /> %s</label>',
			esc_attr( self::OPTION_DROP_DATA ),
			checked( (bool) get_option( self::OPTION_DROP_DATA, false ), true, false ),
			esc_html__( 'Remove the plugin tables and settings when the plugin is deleted. By default your data is kept.', 'wp-simple-access-control' )
		);
	}

	/**
	 * Sanitizza le impostazioni ricevute dal modulo.
	 *
	 * Ricostruisce l'elenco completo campo per campo, ciascuno secondo il proprio tipo:
	 * le chiavi sconosciute vengono scartate e un valore non valido lascia quello corrente.
	 *
	 * @param mixed $input Valori ricevuti (già senza slash, come passa options.php).
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$output = Settings::defaults();
		$saved  = $this->settings->all();

		foreach ( $this->fields() as $key => $field ) {
			$raw     = $input[ $key ] ?? null;
			$current = $saved[ $key ];

			switch ( $field['type'] ) {
				case 'checkbox':
					// Una casella non spuntata non viene inviata: l'assenza significa "no".
					$output[ $key ] = ! empty( $raw );
					break;

				case 'number':
					// Un valore non numerico lascia quello corrente; uno fuori scala viene riportato nei limiti.
					$output[ $key ] = is_numeric( $raw ) ? min( (int) $field['max'], max( (int) $field['min'], (int) $raw ) ) : $current;
					break;

				case 'select':
					$output[ $key ] = is_string( $raw ) && array_key_exists( $raw, $field['options'] ) ? $raw : $current;
					break;

				case 'textarea':
					$output[ $key ] = is_string( $raw ) ? sanitize_textarea_field( $raw ) : $current;
					break;
			}
		}

		return $output;
	}

	/**
	 * Mostra la pagina.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		printf(
			'<p>%s <a href="%s">%s</a></p>',
			esc_html__( 'Protect a page, a post or a single block from the editor sidebar.', 'wp-simple-access-control' ),
			esc_url( admin_url( 'tools.php?page=wpsac-logs' ) ),
			esc_html__( 'View the access log', 'wp-simple-access-control' )
		);

		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form></div>';
	}
}
