'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const chaiFriendly = require( 'eslint-plugin-chai-friendly' );
const cypress = require( 'eslint-plugin-cypress/flat' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

module.exports = defineConfig( [
	globalIgnores( [
		'vendor/',
		'cypress/.cache/',
		'cypress/downloads/',
		'cypress/screenshots/',
		'cypress/videos/',
	] ),

	{
		extends: [ wikimedia( 'common', 'language/es2018', 'jquery', 'mediawiki' ) ],
		languageOptions: {
			globals: {
				wikibase: 'readonly',
			},
		},
		rules: {
			'no-jquery/no-global-selector': 'off',
		},
	},
	{
		files: [ '**/*.js' ],
		rules: {
			'comma-dangle': [ 'error', 'always-multiline' ],
		},
	},

	{
		files: [ 'cypress.config.js' ],
		extends: [ wikimedia( 'node' ) ],
	},

	{
		files: [ 'tests/qunit/**' ],
		extends: [ wikimedia( 'qunit' ) ],
	},

	{
		files: [ 'cypress/**' ],
		extends: [
			wikimedia( 'node' ),
			cypress.configs.recommended,
			chaiFriendly.configs.recommendedFlat,
		],
		languageOptions: {
			sourceType: 'module',
		},
	},
] );
