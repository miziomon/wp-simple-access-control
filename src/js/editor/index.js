/**
 * Entry point dell'editor Gutenberg.
 *
 * Registra lo store dei ruoli e il pannello del documento.
 * I controlli per singolo blocco vengono aggiunti allo step 5.
 */
import { registerPlugin } from '@wordpress/plugins';

import './store';
import AccessPanel from './components/AccessPanel';
import '../../scss/editor/panel.scss';

registerPlugin( 'wpsac-access-control', { render: AccessPanel } );
