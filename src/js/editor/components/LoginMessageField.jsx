/**
 * Campo del messaggio mostrato sopra il form di login.
 *
 * Props:
 * - value    {string}             testo corrente.
 * - onChange {(value: string)}    chiamata a ogni modifica.
 *
 * Responsabilità: solo la textarea con etichetta e aiuto.
 */
import { TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}                  props          Props del componente.
 * @param {string}                  props.value    Testo corrente.
 * @param {(value: string) => void} props.onChange Callback con il nuovo testo.
 * @return {Element} Textarea del messaggio.
 */
export default function LoginMessageField( { value = '', onChange } ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ __(
				'Message above the login form',
				'wp-simple-access-control'
			) }
			help={ __(
				'Optional. Overrides the default message.',
				'wp-simple-access-control'
			) }
			value={ value }
			onChange={ onChange }
			rows={ 3 }
		/>
	);
}
