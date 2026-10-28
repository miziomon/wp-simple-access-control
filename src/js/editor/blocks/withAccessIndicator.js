/**
 * HOC per `editor.BlockListBlock`: segnala nell'editor i blocchi con accesso limitato.
 *
 * Non aggiunge un elemento DOM attorno al blocco (un <div> wrapper romperebbe i
 * layout flex/grid di Columns, Buttons, Gallery...): aggiunge solo una classe BEM
 * e un attributo `data-wpsac-label` sull'elemento del blocco. Badge e bordo sono
 * disegnati dal CSS (src/scss/editor/block-indicator.scss), così il testo del
 * badge resta traducibile.
 */
import { createHigherOrderComponent } from '@wordpress/compose';

import { MODE_ALL, getBadgeLabel } from './constants';

export const withAccessIndicator = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		const mode = props.attributes?.wpsacVisibility;

		// Nessun indicatore per i blocchi visibili a tutti o senza gli attributi.
		if ( ! mode || mode === MODE_ALL ) {
			return <BlockListBlock { ...props } />;
		}

		const className = [
			props.className,
			'wpsac-block-indicator',
			`wpsac-block-indicator--${ mode.replace( '_', '-' ) }`,
		]
			.filter( Boolean )
			.join( ' ' );

		return (
			<BlockListBlock
				{ ...props }
				className={ className }
				wrapperProps={ {
					...props.wrapperProps,
					'data-wpsac-label': getBadgeLabel( mode ),
				} }
			/>
		);
	},
	'withAccessIndicator'
);
