/* eslint-env node */
const { defineConfig } = require( 'cypress' );
const { mwApiCommands } = require( './cypress/support/MwApiPlugin.js' );

const envLogDir = process.env.LOG_DIR ? process.env.LOG_DIR + '/EntitySchema' : null;

module.exports = defineConfig( {
	e2e: {
		supportFile: 'cypress/support/e2e.js',
		baseUrl: process.env.MW_SERVER + process.env.MW_SCRIPT_PATH,
		mediawikiAdminUsername: process.env.MEDIAWIKI_USER,
		mediawikiAdminPassword: process.env.MEDIAWIKI_PASSWORD,
		setupNodeEvents( on, config ) {
			require( 'cypress-terminal-report/src/installLogsPrinter' )( on );
			on( 'task', {
				...mwApiCommands( config ),
			} );
		},
	},

	screenshotsFolder: envLogDir || 'cypress/screenshots',
	video: true,
	videosFolder: envLogDir || 'cypress/videos',
	downloadsFolder: envLogDir || 'cypress/downloads',
} );
