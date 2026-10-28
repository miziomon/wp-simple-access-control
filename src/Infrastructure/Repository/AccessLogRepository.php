<?php
/**
 * Repository del registro accessi.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Infrastructure\Repository;

use WPSimpleAccessControl\Domain\AccessLogEntry;
use WPSimpleAccessControl\Domain\AccessOutcome;
use WPSimpleAccessControl\Infrastructure\Database\Schema;

/**
 * Legge e scrive la tabella `{prefix}wpsac_access_logs`.
 *
 * Filtri supportati dalle query di lettura (tutti opzionali):
 * - `post_id`   int     solo le voci di quel contenuto;
 * - `user_id`   int     solo le voci di quell'utente;
 * - `outcome`   string  uno dei valori di AccessOutcome;
 * - `date_from` string  `Y-m-d`, giorno del sito (incluso);
 * - `date_to`   string  `Y-m-d`, giorno del sito (incluso).
 *
 * @phpstan-type LogFilters array{post_id?:int,user_id?:int,outcome?:string,date_from?:string,date_to?:string}
 */
class AccessLogRepository {

	/**
	 * Lunghezza massima di user agent e referer salvati.
	 */
	private const MAX_TEXT_LENGTH = 500;

	/**
	 * Colonne per cui è consentito ordinare (chiave pubblica => colonna SQL).
	 */
	private const SORTABLE = array(
		'created_at' => 'created_at',
		'post'       => 'post_id',
		'user'       => 'user_id',
		'outcome'    => 'outcome',
	);

	/**
	 * Inserisce una voce nel registro.
	 *
	 * @param AccessLogEntry $entry Voce da salvare.
	 * @return int|false ID della riga inserita, false in caso di errore.
	 */
	public function add( AccessLogEntry $entry ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			Schema::logs_table(),
			array(
				'post_id'    => $entry->post_id,
				'user_id'    => $entry->user_id,
				'ip_address' => $this->pack_ip( $entry->ip_address ),
				'user_agent' => $this->truncate( $entry->user_agent ),
				'outcome'    => $entry->outcome->value,
				'referer'    => $this->truncate( $entry->referer ),
				'created_at' => $entry->created_at ?? current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Voci del registro, filtrate, ordinate e paginate.
	 *
	 * @param array<string,mixed> $filters  Filtri (vedi la descrizione della classe).
	 * @param string              $orderby  Chiave di ordinamento: created_at, post, user, outcome.
	 * @param string              $order    `asc` o `desc`.
	 * @param int                 $per_page Voci per pagina.
	 * @param int                 $page     Numero di pagina, da 1.
	 * @return array<int,AccessLogEntry>
	 */
	public function query( array $filters, string $orderby = 'created_at', string $order = 'desc', int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$table = Schema::logs_table();

		// Colonna e verso arrivano da un elenco chiuso: non da input diretto.
		$column    = self::SORTABLE[ $orderby ] ?? 'created_at';
		$direction = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

		list( $where, $params ) = $this->build_where( $filters );

		$params[] = max( 1, $per_page );
		$params[] = max( 0, ( $page - 1 ) * $per_page );

		// id come secondo criterio: rende stabile l'ordine tra righe con lo stesso valore.
		$sql = "SELECT * FROM {$table}{$where} ORDER BY {$column} {$direction}, id {$direction} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabella e colonna da elenchi interni.

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql è costruito sopra con soli segnaposto.

		return $this->hydrate_all( (array) $rows );
	}

	/**
	 * Numero totale di voci che soddisfano i filtri.
	 *
	 * @param array<string,mixed> $filters Filtri (vedi la descrizione della classe).
	 * @return int
	 */
	public function count( array $filters ): int {
		global $wpdb;

		$table = Schema::logs_table();

		list( $where, $params ) = $this->build_where( $filters );

		$sql = "SELECT COUNT(*) FROM {$table}{$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella interno.

		// Senza filtri non ci sono segnaposto e prepare() non va chiamato.
		return (int) ( array() === $params ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql contiene solo segnaposto.
	}

	/**
	 * Scorre tutte le voci che soddisfano i filtri, a blocchi, per l'export.
	 *
	 * Pagina per ID crescente (keyset) invece che con OFFSET: resta veloce anche su tabelle grandi.
	 *
	 * @param array<string,mixed> $filters Filtri (vedi la descrizione della classe).
	 * @param int                 $chunk   Voci lette per volta.
	 * @return \Generator<int,AccessLogEntry>
	 */
	public function stream( array $filters, int $chunk = 1000 ): \Generator {
		global $wpdb;

		$table   = Schema::logs_table();
		$last_id = 0;

		list( $where, $params ) = $this->build_where( $filters );

		// Alle condizioni dei filtri si aggiunge quella sull'ultimo ID già letto.
		$where = '' === $where ? ' WHERE id > %d' : $where . ' AND id > %d';

		do {
			$sql  = "SELECT * FROM {$table}{$where} ORDER BY id ASC LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella interno.
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $last_id, $chunk ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql contiene solo segnaposto.

			$fetched = count( (array) $rows );

			foreach ( $this->hydrate_all( (array) $rows ) as $entry ) {
				$last_id = (int) $entry->id;
				yield $entry;
			}
		} while ( $fetched === $chunk );
	}

	/**
	 * Elimina le voci con gli ID indicati.
	 *
	 * @param array<int,int> $ids ID delle righe.
	 * @return int Numero di righe eliminate.
	 */
	public function delete_by_ids( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( array() === $ids ) {
			return 0;
		}

		$table        = Schema::logs_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$sql = "DELETE FROM {$table} WHERE id IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabella interna; i segnaposto sono generati qui.

		return (int) $wpdb->query( $wpdb->prepare( $sql, $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql contiene solo segnaposto.
	}

	/**
	 * Elimina le voci più vecchie del numero di giorni indicato (retention).
	 *
	 * @param int $days Giorni da conservare; con valori < 1 non elimina nulla.
	 * @return int Numero di righe eliminate.
	 */
	public function delete_older_than( int $days ): int {
		global $wpdb;

		if ( $days < 1 ) {
			return 0;
		}

		$table  = Schema::logs_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome tabella interno.
	}

	/**
	 * Costruisce la clausola WHERE e i relativi parametri dai filtri.
	 *
	 * @param array<string,mixed> $filters Filtri (vedi la descrizione della classe).
	 * @return array{0:string,1:array<int,mixed>} Clausola (vuota o con spazio iniziale) e parametri per prepare().
	 */
	private function build_where( array $filters ): array {
		$clauses = array();
		$params  = array();

		if ( ! empty( $filters['post_id'] ) ) {
			$clauses[] = 'post_id = %d';
			$params[]  = absint( $filters['post_id'] );
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$clauses[] = 'user_id = %d';
			$params[]  = absint( $filters['user_id'] );
		}

		// Solo esiti noti: un valore diverso non filtra nulla invece di diventare SQL.
		if ( ! empty( $filters['outcome'] ) && null !== AccessOutcome::tryFrom( (string) $filters['outcome'] ) ) {
			$clauses[] = 'outcome = %s';
			$params[]  = (string) $filters['outcome'];
		}

		// I giorni sono nel fuso del sito, le date salvate in UTC: si converte.
		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = 'created_at >= %s';
			$params[]  = get_gmt_from_date( $filters['date_from'] . ' 00:00:00' );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'created_at <= %s';
			$params[]  = get_gmt_from_date( $filters['date_to'] . ' 23:59:59' );
		}

		return array( array() === $clauses ? '' : ' WHERE ' . implode( ' AND ', $clauses ), $params );
	}

	/**
	 * Converte righe del database in entità.
	 *
	 * @param array<int,array<string,mixed>> $rows Righe.
	 * @return array<int,AccessLogEntry>
	 */
	private function hydrate_all( array $rows ): array {
		$entries = array();

		foreach ( $rows as $row ) {
			$outcome = AccessOutcome::tryFrom( (string) $row['outcome'] );

			// Un esito sconosciuto (es. scritto da una versione futura) non si può rappresentare: si salta.
			if ( null === $outcome ) {
				continue;
			}

			$ip = null;

			if ( isset( $row['ip_address'] ) && in_array( strlen( (string) $row['ip_address'] ), array( 4, 16 ), true ) ) {
				$text = inet_ntop( (string) $row['ip_address'] );
				$ip   = false === $text ? null : $text;
			}

			$entries[] = new AccessLogEntry(
				(int) $row['post_id'],
				$outcome,
				null === $row['user_id'] ? null : (int) $row['user_id'],
				$ip,
				null === $row['user_agent'] ? null : (string) $row['user_agent'],
				null === $row['referer'] ? null : (string) $row['referer'],
				(string) $row['created_at'],
				(int) $row['id']
			);
		}

		return $entries;
	}

	/**
	 * Converte un IP testuale in binario (inet_pton), per IPv4 e IPv6.
	 *
	 * @param string|null $ip Indirizzo IP testuale.
	 * @return string|null Binario di 4 o 16 byte, null se assente o non valido.
	 */
	private function pack_ip( ?string $ip ): ?string {
		if ( null === $ip || '' === $ip ) {
			return null;
		}

		$packed = inet_pton( $ip );

		return false === $packed ? null : $packed;
	}

	/**
	 * Tronca una stringa alla lunghezza massima della colonna.
	 *
	 * @param string|null $value Valore da troncare.
	 * @return string|null
	 */
	private function truncate( ?string $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return mb_substr( $value, 0, self::MAX_TEXT_LENGTH );
	}
}
