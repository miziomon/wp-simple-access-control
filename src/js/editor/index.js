/**
 * Entry point dell'editor Gutenberg.
 *
 * Registra lo store dei ruoli, il pannello del documento e i controlli per singolo blocco.
 */
import { registerPlugin } from '@wordpress/plugins';

import './store';
import './blocks';
import AccessPanel from './components/AccessPanel';
import '../../scss/editor/panel.scss';

registerPlugin( 'wpsac-access-control', { render: AccessPanel } );
