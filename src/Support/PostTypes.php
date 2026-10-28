<?php
/**
 * Elenco dei post type per cui è disponibile la protezione.
 *
 * @package WPSimpleAccessControl
 */

declare(strict_types=1);

namespace WPSimpleAccessControl\Support;

/**
 * Stabilisce quali post type possono essere protetti.
 */
final class PostTypes {

	/**
	 * Post type supportati: tutti quelli pubblici, tranne gli allegati.
	 *
	 * Va chiamato da `init` in poi (priorità > 10), quando i CPT sono registrati.
	 *
	 * @return array<int,string>
	 */
	public static function supported(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		/**
		 * Filtra i post type per cui è disponibile la protezione d'accesso.
		 *
		 * @param array<int,string> $types Slug dei post type.
		 */
		$types = apply_filters( 'wpsac_supported_post_types', array_values( $types ) );

		return array_values( array_filter( (array) $types, 'is_string' ) );
	}

	/**
	 * Indica se un post type è supportato.
	 *
	 * @param string $post_type Slug del post type.
	 * @return bool
	 */
	public static function is_supported( string $post_type ): bool {
		return in_array( $post_type, self::supported(), true );
	}
}
