<?php
/**
 * Tabella del registro accessi.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Admin\Logs;

use WPSimpleAccessControl\Domain\AccessLogEntry;
use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Infrastructure\Repository\AccessLogRepository;

/**
 * WP_List_Table del registro accessi: colonne ordinabili, paginazione e cancellazione multipla.
 *
 * Il file che definisce WP_List_Table va caricato prima di questa classe (lo fa LogsPage).
 */
final class LogsListTable extends \WP_List_Table {

	/**
	 * Repository del registro.
	 *
	 * @var AccessLogRepository
	 */
	private AccessLogRepository $logs;

	/**
	 * Filtri già validati da LogsPage.
	 *
	 * @var array<string,mixed>
	 */
	private array $filters;

	/**
	 * Costruttore.
	 *
	 * @param AccessLogRepository $logs    Repository del registro.
	 * @param array<string,mixed> $filters Filtri già validati.
	 */
	public function __construct( AccessLogRepository $logs, array $filters ) {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);

		$this->logs    = $logs;
		$this->filters = $filters;
	}

	/**
	 * Colonne della tabella.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'created_at' => __( 'Date', 'wp-simple-access-control' ),
			'post'       => __( 'Content', 'wp-simple-access-control' ),
			'user'       => __( 'User', 'wp-simple-access-control' ),
			'ip'         => __( 'IP address', 'wp-simple-access-control' ),
			'outcome'    => __( 'Outcome', 'wp-simple-access-control' ),
			'user_agent' => __( 'User agent', 'wp-simple-access-control' ),
		);
	}

	/**
	 * Colonne ordinabili (chiavi accettate da AccessLogRepository::query()).
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'created_at' => array( 'created_at', true ),
			'post'       => array( 'post', false ),
			'user'       => array( 'user', false ),
			'outcome'    => array( 'outcome', false ),
		);
	}

	/**
	 * Azioni multiple.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'delete' => __( 'Delete', 'wp-simple-access-control' ),
		);
	}

	/**
	 * Classi CSS della tabella.
	 *
	 * @return array<int,string>
	 */
	protected function get_table_classes(): array {
		return array_merge( parent::get_table_classes(), array( 'wpsac-logs-table' ) );
	}

	/**
	 * Carica le voci della pagina corrente.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		/**
		 * Filtra il numero di voci del registro mostrate per pagina.
		 *
		 * @param int $per_page Voci per pagina (default 20).
		 */
		$per_page = max( 1, (int) apply_filters( 'wpsac_logs_per_page', 20 ) );

		// Ordinamento richiesto: sola lettura, la query lo valida con un elenco chiuso.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->items = $this->logs->query( $this->filters, $orderby, $order, $per_page, $this->get_pagenum() );

		$this->set_pagination_args(
			array(
				'total_items' => $this->logs->count( $this->filters ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Messaggio quando non ci sono voci.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No log entries found.', 'wp-simple-access-control' );
	}

	/**
	 * Casella di selezione della riga.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="log[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Data e ora, nel fuso del sito.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_created_at( AccessLogEntry $item ): string {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return esc_html( get_date_from_gmt( (string) $item->created_at, $format ) );
	}

	/**
	 * Contenuto richiesto, con link alla modifica se esiste ancora.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_post( AccessLogEntry $item ): string {
		$post = get_post( $item->post_id );
		$link = $post instanceof \WP_Post ? get_edit_post_link( $post->ID ) : null;

		if ( null === $link || '' === $link ) {
			/* translators: %d: ID of a content that no longer exists. */
			return esc_html( 0 === $item->post_id ? '—' : sprintf( __( '#%d (deleted)', 'wp-simple-access-control' ), $item->post_id ) );
		}

		return sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( get_the_title( $post ) ) );
	}

	/**
	 * Utente, con link al profilo; "Guest" se non era autenticato.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_user( AccessLogEntry $item ): string {
		if ( null === $item->user_id ) {
			return '<span class="wpsac-logs-table__muted">' . esc_html__( 'Guest', 'wp-simple-access-control' ) . '</span>';
		}

		$user = get_userdata( $item->user_id );

		if ( false === $user ) {
			/* translators: %d: ID of a user that no longer exists. */
			return esc_html( sprintf( __( '#%d (deleted)', 'wp-simple-access-control' ), $item->user_id ) );
		}

		return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_user_link( $user->ID ) ), esc_html( $user->user_login ) );
	}

	/**
	 * Indirizzo IP.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_ip( AccessLogEntry $item ): string {
		return esc_html( $item->ip_address ?? '—' );
	}

	/**
	 * Esito, come badge colorato.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_outcome( AccessLogEntry $item ): string {
		$kind = match ( $item->outcome ) {
			AccessOutcome::GRANTED, AccessOutcome::LOGIN_SUCCESS => 'success',
			AccessOutcome::DENIED_NOT_LOGGED, AccessOutcome::DENIED_ROLE, AccessOutcome::DENIED_DISABLED => 'warning',
			AccessOutcome::LOGIN_FAILED, AccessOutcome::LOGIN_RATE_LIMITED => 'danger',
		};

		return sprintf( '<span class="wpsac-badge wpsac-badge--%s">%s</span>', esc_attr( $kind ), esc_html( $item->outcome->label() ) );
	}

	/**
	 * User agent, abbreviato; il testo completo è nel tooltip.
	 *
	 * @param AccessLogEntry $item Voce.
	 * @return string
	 */
	protected function column_user_agent( AccessLogEntry $item ): string {
		$agent = (string) $item->user_agent;

		if ( '' === $agent ) {
			return '—';
		}

		return sprintf( '<span title="%s">%s</span>', esc_attr( $agent ), esc_html( wp_html_excerpt( $agent, 40, '…' ) ) );
	}
}
