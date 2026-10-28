/**
 * Store `wpsac/roles`: elenco dei ruoli del sito.
 *
 * I ruoli vengono caricati una sola volta (resolver) dall'endpoint
 * `wpsac/v1/roles` e condivisi tra il pannello del documento e i controlli
 * dei singoli blocchi, evitando una richiesta per ogni blocco selezionato.
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

export const STORE_NAME = 'wpsac/roles';

const store = createReduxStore( STORE_NAME, {
	// Stato: `null` finché i ruoli non sono arrivati.
	reducer( state = { roles: null }, action ) {
		if ( action.type === 'RECEIVE_ROLES' ) {
			return { roles: action.roles };
		}
		return state;
	},

	actions: {
		receiveRoles( roles ) {
			return { type: 'RECEIVE_ROLES', roles };
		},
	},

	selectors: {
		getRoles( state ) {
			return state.roles;
		},
	},

	resolvers: {
		getRoles:
			() =>
			async ( { dispatch } ) => {
				try {
					const roles = await apiFetch( {
						path: '/wpsac/v1/roles',
					} );
					dispatch.receiveRoles( roles );
				} catch {
					// In caso di errore evita lo spinner infinito: elenco vuoto.
					dispatch.receiveRoles( [] );
				}
			},
	},
} );

register( store );
