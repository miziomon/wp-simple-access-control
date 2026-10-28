/**
 * Pannello "Access control" nell'Inspector di ogni blocco.
 *
 * Contiene:
 * - `BlockAccessControls`: il pannello (modalità, ruoli, teaser);
 * - `withAccessControls`: HOC per il filtro `editor.BlockEdit` che lo aggiunge
 *   ai blocchi che hanno gli attributi di visibilità.
 *
 * Props di BlockAccessControls:
 * - attributes    {Object}   attributi del blocco selezionato.
 * - setAttributes {Function} setter degli attributi del blocco.
 */
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

import RolesSelector from '../components/RolesSelector';
import LoginMessageField from '../components/LoginMessageField';
import { MODE_ALL, MODE_LOGGED_IN, getModeOptions } from './constants';

/**
 * @param {Object}                       props               Props del componente.
 * @param {Object}                       props.attributes    Attributi del blocco.
 * @param {(attributes: Object) => void} props.setAttributes Setter degli attributi.
 * @return {Element} Pannello dell'Inspector.
 */
function BlockAccessControls( { attributes, setAttributes } ) {
	const {
		wpsacVisibility: mode,
		wpsacRoles: roles,
		wpsacShowTeaser: showTeaser,
		wpsacTeaserMessage: teaserMessage,
	} = attributes;

	return (
		<PanelBody
			title={ __( 'Access control', 'wp-simple-access-control' ) }
			initialOpen={ mode !== MODE_ALL }
		>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Visible to', 'wp-simple-access-control' ) }
				value={ mode }
				options={ getModeOptions() }
				onChange={ ( value ) =>
					setAttributes( {
						wpsacVisibility: value,
						// Tornando a "tutti" il teaser non ha più senso.
						...( value === MODE_ALL && { wpsacShowTeaser: false } ),
					} )
				}
			/>

			{ mode === MODE_LOGGED_IN && (
				<>
					<RolesSelector
						value={ roles }
						onChange={ ( value ) =>
							setAttributes( { wpsacRoles: value } )
						}
					/>
					<p className="wpsac-panel__help">
						{ __(
							'No role selected: any logged-in user can see this block. Administrators always can.',
							'wp-simple-access-control'
						) }
					</p>
				</>
			) }

			{ mode !== MODE_ALL && (
				<>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show a message instead of the block',
							'wp-simple-access-control'
						) }
						checked={ showTeaser }
						onChange={ ( value ) =>
							setAttributes( { wpsacShowTeaser: value } )
						}
					/>
					{ showTeaser && (
						<LoginMessageField
							label={ __(
								'Message',
								'wp-simple-access-control'
							) }
							help={ __(
								'Plain text only. Use [login]link text[/login] to link to the login page.',
								'wp-simple-access-control'
							) }
							value={ teaserMessage }
							onChange={ ( value ) =>
								setAttributes( { wpsacTeaserMessage: value } )
							}
						/>
					) }
				</>
			) }
		</PanelBody>
	);
}

/**
 * HOC per `editor.BlockEdit`: aggiunge il pannello ai blocchi che hanno gli attributi.
 *
 * I blocchi senza gli attributi (non toccati dal filtro `registerBlockType`)
 * vengono lasciati invariati.
 */
export const withAccessControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if (
			! props.attributes ||
			! ( 'wpsacVisibility' in props.attributes )
		) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				{ props.isSelected && (
					<InspectorControls>
						<BlockAccessControls
							attributes={ props.attributes }
							setAttributes={ props.setAttributes }
						/>
					</InspectorControls>
				) }
			</>
		);
	},
	'withAccessControls'
);
