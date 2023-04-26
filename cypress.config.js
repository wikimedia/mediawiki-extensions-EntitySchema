/* eslint-env node */
const { defineConfig } = require( 'cypress' );

const envLogDir = process.env.LOG_DIR ? process.env.LOG_DIR + '/EntitySchema' : null;

module.exports = defineConfig( {
	e2e: {
		supportFile: false,
		baseUrl: process.env.MW_SERVER + process.env.MW_SCRIPT_PATH,
	},

	screenshotsFolder: envLogDir || 'cypress/screenshots',
	videosFolder: envLogDir || 'cypress/videos',
	downloadsFolder: envLogDir || 'cypress/downloads',
} );
