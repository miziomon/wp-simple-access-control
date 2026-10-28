/**
 * Configurazione ESLint del progetto.
 *
 * Estende quella di default di @wordpress/scripts. I pacchetti `@wordpress/*`
 * non sono installati in node_modules: li fornisce WordPress a runtime
 * (vengono trattati come "external" in fase di build), quindi non vanno
 * risolti né dichiarati come dipendenze.
 */
const wpConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpConfig,
	{
		files: [ '**/*.{js,jsx}' ],
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
			'import/no-extraneous-dependencies': 'off',
		},
	},
];
