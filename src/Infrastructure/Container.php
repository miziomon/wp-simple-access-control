<?php
/**
 * Service container minimale.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Infrastructure;

/**
 * Registra factory di servizi e li istanzia al primo uso (lazy, una sola volta).
 *
 * Tiene le dipendenze esplicite e sostituibili, così il codice resta testabile
 * senza introdurre un container esterno.
 */
final class Container {

	/**
	 * Factory registrate, indicizzate per id.
	 *
	 * @var array<string,callable(Container):object>
	 */
	private array $factories = array();

	/**
	 * Istanze già create.
	 *
	 * @var array<string,object>
	 */
	private array $instances = array();

	/**
	 * Registra la factory di un servizio.
	 *
	 * @param string                     $id      Identificativo (di solito il nome della classe).
	 * @param callable(Container):object $factory Funzione che crea il servizio.
	 * @return void
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Restituisce il servizio, creandolo se serve.
	 *
	 * L'id deve essere il nome della classe del servizio, così il tipo restituito è noto.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T> $id
	 * @param string $id Nome della classe del servizio.
	 * @return T
	 *
	 * @throws \InvalidArgumentException Se il servizio non è registrato o ha un tipo diverso.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Service "%s" is not registered.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno per sviluppatori.
			}

			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		}

		$instance = $this->instances[ $id ];

		if ( ! $instance instanceof $id ) {
			throw new \InvalidArgumentException( sprintf( 'Service "%s" has an unexpected type.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno per sviluppatori.
		}

		return $instance;
	}
}
