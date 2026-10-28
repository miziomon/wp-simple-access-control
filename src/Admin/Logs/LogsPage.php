<?php
/**
 * Pagina di amministrazione "Access log".
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Admin\Logs;

use WPSimpleAccessControl\Domain\AccessLogEntry;
use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Infrastructure\Repository\AccessLogRepository;

/**
 * Pagina sotto "Strumenti" per consultare il registro accessi:
 * filtri, tabella ordinabile e paginata, cancellazione multipla ed export CSV.
 *
 * Riservata a chi ha la capability `manage_options`.
 */
class LogsPage {

	/**
	 * Slug della pagina (usato anche dal link nel pannello dell'editor).
	 */
	public const SLUG = 'wpsac-logs';

	/**
	 * Azione del nonce dell'export CSV.
	 */
	private const EXPORT_NONCE = 'wpsac_export_csv';

	/**
	 * Repository del registro.
	 *
	 * @var AccessLogRepository
	 */
	private AccessLogRepository $logs;

	/**
	 * Suffisso dell'hook della pagina (vuoto finché il menu non è registrato).
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Costruttore.
	 *
	 * @param AccessLogRepository $logs Repository del registro.
	 */
	public function __construct( AccessLogRepository $logs ) {
		$this->logs = $logs;
	}

	/**
	 * Aggancia gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Aggiunge la voce "Access log" al menu Strumenti.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$hook = add_management_page(
			__( 'Access log', 'wp-simple-access-control' ),
			__( 'Access log', 'wp-simple-access-control' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		if ( is_string( $hook ) ) {
			$this->hook_suffix = $hook;
			// Eseguito prima di qualsiasi output: serve a export e cancellazione, che reindirizzano o inviano un file.
			add_action( "load-{$hook}", array( $this, 'handle_requests' ) );
		}
	}

	/**
	 * Carica gli stili della pagina.
	 *
	 * @param string $hook_suffix Suffisso della pagina admin corrente.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$asset_file = WPSAC_PLUGIN_DIR . 'build/admin.asset.php';

		if ( $hook_suffix !== $this->hook_suffix || ! is_readable( $asset_file ) || ! is_readable( WPSAC_PLUGIN_DIR . 'build/admin.css' ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style( 'wpsac-admin', WPSAC_PLUGIN_URL . 'build/admin.css', array(), $asset['version'] );
	}

	/**
	 * Gestisce export CSV e cancellazione multipla.
	 *
	 * @return void
	 */
	public function handle_requests(): void {
		// Export CSV (GET con nonce).
		if ( isset( $_GET['wpsac_export_csv'] ) ) {
			check_admin_referer( self::EXPORT_NONCE );
			$this->export_csv( $this->filters() );
			exit;
		}

		// Cancellazione multipla (POST con il nonce standard delle list table).
		$table = $this->table();

		if ( 'delete' === $table->current_action() ) {
			check_admin_referer( 'bulk-logs' );

			$ids     = isset( $_POST['log'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['log'] ) ) : array();
			$deleted = $this->logs->delete_by_ids( $ids );

			wp_safe_redirect( add_query_arg( 'wpsac_deleted', $deleted, $this->page_url() ) );
			exit;
		}
	}

	/**
	 * Mostra la pagina.
	 *
	 * @return void
	 */
	public function render(): void {
		$table = $this->table();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html( get_admin_page_title() ) . '</h1><hr class="wp-header-end" />';

		// Esito dell'ultima cancellazione.
		if ( isset( $_GET['wpsac_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola visualizzazione di un contatore.
			$count = absint( $_GET['wpsac_deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					/* translators: %d: number of deleted log entries. */
					sprintf( _n( '%d entry deleted.', '%d entries deleted.', $count, 'wp-simple-access-control' ), $count )
				)
			);
		}

		$this->render_filters();

		// Il form della tabella è in POST (cancellazione multipla); i filtri restano nell'URL dell'action.
		echo '<form method="post" action="' . esc_url( $this->page_url() ) . '">';
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Mostra il modulo dei filtri (GET) con i link a "Reset" ed "Export CSV".
	 *
	 * @return void
	 */
	private function render_filters(): void {
		$filters = $this->filters();
		$outcome = $filters['outcome'] ?? '';

		echo '<form class="wpsac-logs-filters" method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';

		$this->render_field( 'post_id', __( 'Content ID', 'wp-simple-access-control' ), (string) ( $filters['post_id'] ?? '' ), 'number' );
		$this->render_field( 'user_id', __( 'User ID', 'wp-simple-access-control' ), (string) ( $filters['user_id'] ?? '' ), 'number' );

		echo '<p class="wpsac-logs-filters__field"><label for="wpsac-outcome">' . esc_html__( 'Outcome', 'wp-simple-access-control' ) . '</label>';
		echo '<select id="wpsac-outcome" name="outcome"><option value="">' . esc_html__( 'All outcomes', 'wp-simple-access-control' ) . '</option>';
		foreach ( AccessOutcome::cases() as $case ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $case->value ), selected( $outcome, $case->value, false ), esc_html( $case->label() ) );
		}
		echo '</select></p>';

		$this->render_field( 'date_from', __( 'From', 'wp-simple-access-control' ), (string) ( $filters['date_from'] ?? '' ), 'date' );
		$this->render_field( 'date_to', __( 'To', 'wp-simple-access-control' ), (string) ( $filters['date_to'] ?? '' ), 'date' );

		echo '<p class="wpsac-logs-filters__actions">';
		submit_button( __( 'Filter', 'wp-simple-access-control' ), 'secondary', '', false );
		printf( ' <a class="button" href="%s">%s</a>', esc_url( admin_url( 'tools.php?page=' . self::SLUG ) ), esc_html__( 'Reset', 'wp-simple-access-control' ) );
		printf(
			' <a class="button" href="%s">%s</a>',
			esc_url( wp_nonce_url( add_query_arg( 'wpsac_export_csv', 1, $this->page_url() ), self::EXPORT_NONCE ) ),
			esc_html__( 'Export CSV', 'wp-simple-access-control' )
		);
		echo '</p></form>';
	}

	/**
	 * Stampa un campo del modulo dei filtri.
	 *
	 * @param string $name  Nome del campo (e del parametro GET).
	 * @param string $label Etichetta.
	 * @param string $value Valore corrente.
	 * @param string $type  Tipo di input.
	 * @return void
	 */
	private function render_field( string $name, string $label, string $value, string $type ): void {
		printf(
			'<p class="wpsac-logs-filters__field"><label for="wpsac-%1$s">%2$s</label><input id="wpsac-%1$s" type="%3$s" name="%1$s" value="%4$s"%5$s /></p>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			'number' === $type ? ' min="1" class="small-text"' : ''
		);
	}

	/**
	 * Crea la tabella (carica prima la classe base di WordPress).
	 *
	 * @return LogsListTable
	 */
	private function table(): LogsListTable {
		if ( ! class_exists( \WP_List_Table::class ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		return new LogsListTable( $this->logs, $this->filters() );
	}

	/**
	 * Filtri richiesti, validati. I valori non validi vengono ignorati.
	 *
	 * @return array<string,int|string>
	 */
	private function filters(): array {
		// Solo lettura di parametri di filtro: nessuna modifica, quindi niente nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filters = array();

		foreach ( array( 'post_id', 'user_id' ) as $key ) {
			$value = isset( $_GET[ $key ] ) ? absint( wp_unslash( $_GET[ $key ] ) ) : 0;

			if ( $value > 0 ) {
				$filters[ $key ] = $value;
			}
		}

		$outcome = isset( $_GET['outcome'] ) ? sanitize_key( wp_unslash( $_GET['outcome'] ) ) : '';

		if ( null !== AccessOutcome::tryFrom( $outcome ) ) {
			$filters['outcome'] = $outcome;
		}

		foreach ( array( 'date_from', 'date_to' ) as $key ) {
			$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			$date  = \DateTime::createFromFormat( 'Y-m-d', $value );

			// Accetta solo date vere in formato Y-m-d (scarta, ad esempio, 2026-02-31).
			if ( false !== $date && $date->format( 'Y-m-d' ) === $value ) {
				$filters[ $key ] = $value;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $filters;
	}

	/**
	 * URL della pagina con i filtri e l'ordinamento correnti.
	 *
	 * @return string
	 */
	private function page_url(): string {
		$args = array( 'page' => self::SLUG ) + $this->filters();

		foreach ( array( 'orderby', 'order' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametri di sola visualizzazione.
				$args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}

	/**
	 * Invia il registro (con i filtri attivi) come file CSV.
	 *
	 * @param array<string,int|string> $filters Filtri validati.
	 * @return void
	 */
	private function export_csv( array $filters ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wpsac-access-log-' . gmdate( 'Y-m-d' ) . '.csv"' );

		// Scrittura su php://output: è lo stream della risposta, non il filesystem.
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// BOM UTF-8: senza, Excel apre il file con la codifica sbagliata.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		$this->put_csv_row( $out, array( 'id', 'created_at_utc', 'post_id', 'post_title', 'user_id', 'user_login', 'ip_address', 'outcome', 'user_agent', 'referer' ) );

		foreach ( $this->logs->stream( $filters ) as $entry ) {
			$this->put_csv_row( $out, $this->csv_row( $entry ) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Valori di una voce nell'ordine delle colonne del CSV.
	 *
	 * @param AccessLogEntry $entry Voce.
	 * @return array<int,string>
	 */
	private function csv_row( AccessLogEntry $entry ): array {
		$user = null === $entry->user_id ? false : get_userdata( $entry->user_id );

		return array(
			(string) $entry->id,
			(string) $entry->created_at,
			(string) $entry->post_id,
			$entry->post_id > 0 ? get_the_title( $entry->post_id ) : '',
			(string) $entry->user_id,
			false === $user ? '' : $user->user_login,
			(string) $entry->ip_address,
			$entry->outcome->value,
			(string) $entry->user_agent,
			(string) $entry->referer,
		);
	}

	/**
	 * Scrive una riga del CSV neutralizzando le formule.
	 *
	 * User agent e referer arrivano dal visitatore: una cella che inizia con = + - @ verrebbe
	 * interpretata come formula da Excel o LibreOffice (CSV injection). Il prefisso `'` la rende testo.
	 *
	 * @param resource          $out Stream di destinazione.
	 * @param array<int,string> $row Valori della riga.
	 * @return void
	 */
	private function put_csv_row( $out, array $row ): void {
		$row = array_map(
			static fn( string $cell ): string => 1 === preg_match( '/^[=+\-@\t\r]/', $cell ) ? "'" . $cell : $cell,
			$row
		);

		// Il parametro $escape vuoto evita il deprecation di PHP 8.4 e il comportamento ambiguo del backslash.
		fputcsv( $out, $row, ',', '"', '' );
	}
}
