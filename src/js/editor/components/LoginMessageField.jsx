/**
 * Campo di testo di un messaggio (messaggio di login, teaser di un blocco).
 *
 * Props:
 * - value    {string}             testo corrente.
 * - onChange {(value: string)}    chiamata a ogni modifica.
 * - label    {string}             etichetta (opzionale, ha un default).
 * - help     {string}             testo d'aiuto (opzionale, ha un default).
 *
 * Responsabilità: solo la textarea con etichetta e aiuto.
 */
import { TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}                  props          Props del componente.
 * @param {string}                  props.value    Testo corrente.
 * @param {(value: string) => void} props.onChange Callback con il nuovo testo.
 * @param {string}                  props.label    Etichetta del campo.
 * @param {string}                  props.help     Testo d'aiuto.
 * @return {Element} Textarea del messaggio.
 */
export default function LoginMessageField( {
	value = '',
	onChange,
	label = __( 'Message above the login form', 'wp-simple-access-control' ),
	help = __(
		'Optional. Overrides the default message.',
		'wp-simple-access-control'
	),
} ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			value={ value }
			onChange={ onChange }
			rows={ 3 }
		/>
	);
}
