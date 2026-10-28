/**
 * Elenco di checkbox dei ruoli del sito.
 *
 * Props:
 * - value    {string[]}            slug dei ruoli selezionati.
 * - onChange {(roles: string[])}   chiamata con il nuovo elenco a ogni modifica.
 *
 * Responsabilità: mostra i ruoli (dallo store `wpsac/roles`) e gestisce la
 * selezione. Non conosce né il post né il blocco: è condiviso da pannello e blocchi.
 */
import { useSelect } from '@wordpress/data';
import { CheckboxControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { STORE_NAME } from '../store';

/**
 * @param {Object}                    props          Props del componente.
 * @param {string[]}                  props.value    Slug dei ruoli selezionati.
 * @param {(roles: string[]) => void} props.onChange Callback con il nuovo elenco di slug.
 * @return {Element} Elenco di checkbox.
 */
export default function RolesSelector( { value = [], onChange } ) {
	const roles = useSelect(
		( select ) => select( STORE_NAME ).getRoles(),
		[]
	);

	// Ruoli ancora in caricamento.
	if ( roles === null ) {
		return <Spinner />;
	}

	if ( roles.length === 0 ) {
		return (
			<p>{ __( 'No roles available.', 'wp-simple-access-control' ) }</p>
		);
	}

	// Aggiunge o toglie lo slug dall'elenco dei selezionati.
	const toggle = ( slug, checked ) => {
		onChange(
			checked ? [ ...value, slug ] : value.filter( ( r ) => r !== slug )
		);
	};

	return (
		<fieldset className="wpsac-panel__roles">
			<legend className="wpsac-panel__legend">
				{ __( 'Allowed roles', 'wp-simple-access-control' ) }
			</legend>
			{ roles.map( ( role ) => (
				<CheckboxControl
					__nextHasNoMarginBottom
					key={ role.slug }
					label={ role.name }
					checked={ value.includes( role.slug ) }
					onChange={ ( checked ) => toggle( role.slug, checked ) }
				/>
			) ) }
		</fieldset>
	);
}
