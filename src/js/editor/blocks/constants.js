/**
 * Costanti condivise dai filtri dei blocchi.
 *
 * I nomi degli attributi e i valori di visibilità devono restare identici a
 * quelli di src/Blocks/BlockVisibility.php e src/Domain/AccessPolicy.php.
 */
import { __ } from '@wordpress/i18n';

// Valori di visibilità.
export const MODE_ALL = 'all';
export const MODE_LOGGED_IN = 'logged_in';
export const MODE_LOGGED_OUT = 'logged_out';

// Attributi aggiunti a ogni blocco.
export const ATTRIBUTES = {
	wpsacVisibility: { type: 'string', default: MODE_ALL },
	wpsacRoles: { type: 'array', items: { type: 'string' }, default: [] },
	wpsacShowTeaser: { type: 'boolean', default: false },
	wpsacTeaserMessage: { type: 'string', default: '' },
};

/**
 * Opzioni della select "Visible to".
 *
 * @return {Array<{value: string, label: string}>} Opzioni per SelectControl.
 */
export function getModeOptions() {
	return [
		{
			value: MODE_ALL,
			label: __( 'Everyone', 'wp-simple-access-control' ),
		},
		{
			value: MODE_LOGGED_IN,
			label: __( 'Logged-in users', 'wp-simple-access-control' ),
		},
		{
			value: MODE_LOGGED_OUT,
			label: __( 'Logged-out visitors', 'wp-simple-access-control' ),
		},
	];
}

/**
 * Etichetta breve mostrata nel badge del blocco nell'editor.
 *
 * @param {string} mode Modalità di visibilità.
 * @return {string} Testo del badge.
 */
export function getBadgeLabel( mode ) {
	if ( mode === MODE_LOGGED_OUT ) {
		return __( 'Logged-out only', 'wp-simple-access-control' );
	}
	return __( 'Logged-in only', 'wp-simple-access-control' );
}
