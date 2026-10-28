/**
 * Pannello "Access control" nella sidebar del documento.
 *
 * Responsabilità: leggere e scrivere i meta `_wpsac_*` del contenuto corrente
 * (protezione attiva, ruoli, messaggio). Il salvataggio nella tabella delle
 * regole avviene lato server (RuleSynchronizer) quando il post viene salvato.
 *
 * Il pannello compare solo per i post type supportati (`window.wpsacEditor.postTypes`).
 */
import * as editor from '@wordpress/editor';
import * as editPost from '@wordpress/edit-post';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { Button, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import RolesSelector from './RolesSelector';
import LoginMessageField from './LoginMessageField';

// Da WordPress 6.6 il pannello è in @wordpress/editor, prima in @wordpress/edit-post.
// L'operatore `??` evita di toccare l'export deprecato quando quello nuovo esiste.
const PluginDocumentSettingPanel =
	editor.PluginDocumentSettingPanel ?? editPost.PluginDocumentSettingPanel;

/**
 * Contenuto del pannello: usa i meta del post corrente.
 *
 * @param {Object} props          Props del componente.
 * @param {string} props.postType Post type del contenuto aperto.
 * @param {number} props.postId   ID del contenuto aperto.
 * @return {Element} Pannello delle impostazioni.
 */
function AccessPanelContent( { postType, postId } ) {
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const enabled = Boolean( meta?._wpsac_enabled );
	const roles = meta?._wpsac_roles ?? [];
	const loginMessage = meta?._wpsac_login_message ?? '';

	// Aggiorna un singolo meta mantenendo gli altri invariati.
	const updateMeta = ( key, value ) => setMeta( { ...meta, [ key ]: value } );

	const logsUrl = window.wpsacEditor?.logsUrl;

	return (
		<PluginDocumentSettingPanel
			name="wpsac-access-panel"
			title={ __( 'Access control', 'wp-simple-access-control' ) }
			className="wpsac-panel"
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Restrict access', 'wp-simple-access-control' ) }
				checked={ enabled }
				onChange={ ( value ) => updateMeta( '_wpsac_enabled', value ) }
			/>

			{ enabled && (
				<>
					<RolesSelector
						value={ roles }
						onChange={ ( value ) =>
							updateMeta( '_wpsac_roles', value )
						}
					/>
					<p className="wpsac-panel__help">
						{ __(
							'No role selected: any logged-in user can view this content. Administrators always can.',
							'wp-simple-access-control'
						) }
					</p>

					<LoginMessageField
						value={ loginMessage }
						onChange={ ( value ) =>
							updateMeta( '_wpsac_login_message', value )
						}
					/>
				</>
			) }

			{ logsUrl && (
				<Button
					className="wpsac-panel__logs-link"
					variant="link"
					href={ `${ logsUrl }&post_id=${ postId }` }
					target="_blank"
				>
					{ __(
						'View access log for this content',
						'wp-simple-access-control'
					) }
				</Button>
			) }
		</PluginDocumentSettingPanel>
	);
}

/**
 * Pannello esterno: mostra il contenuto solo per i post type supportati.
 *
 * @return {Element|null} Il pannello, oppure null.
 */
export default function AccessPanel() {
	const { postType, postId } = useSelect( ( select ) => {
		const editorSelect = select( editor.store );
		return {
			postType: editorSelect.getCurrentPostType(),
			postId: editorSelect.getCurrentPostId(),
		};
	}, [] );

	const supported = window.wpsacEditor?.postTypes ?? [];

	if ( ! postType || ! supported.includes( postType ) ) {
		return null;
	}

	return <AccessPanelContent postType={ postType } postId={ postId } />;
}
