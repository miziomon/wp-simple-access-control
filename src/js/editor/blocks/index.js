/**
 * Controllo d'accesso per singolo blocco: registrazione dei filtri.
 *
 * Il plugin non registra blocchi propri: aggiunge attributi di visibilità a
 * tutti i blocchi (registerBlockType), il pannello nell'Inspector (BlockEdit)
 * e l'indicatore visivo nell'editor (BlockListBlock).
 */
import { addFilter } from '@wordpress/hooks';

import { ATTRIBUTES } from './constants';
import { withAccessControls } from './BlockAccessControls';
import { withAccessIndicator } from './withAccessIndicator';

/**
 * Aggiunge gli attributi di visibilità alla definizione di un blocco.
 *
 * @param {Object} settings Impostazioni del blocco.
 * @return {Object} Impostazioni con gli attributi aggiunti.
 */
function addAttributes( settings ) {
	// I blocchi senza attributi non si toccano (come lato PHP in BlockVisibility::add_attributes).
	if ( ! settings.attributes ) {
		return settings;
	}

	return {
		...settings,
		attributes: { ...settings.attributes, ...ATTRIBUTES },
	};
}

addFilter(
	'blocks.registerBlockType',
	'wpsac/add-access-attributes',
	addAttributes
);
addFilter(
	'editor.BlockEdit',
	'wpsac/with-access-controls',
	withAccessControls
);
addFilter(
	'editor.BlockListBlock',
	'wpsac/with-access-indicator',
	withAccessIndicator
);
