'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class SchemaPage extends Page {

	static get SCHEMA_SELECTORS() {
		return {
			LABEL: '#wbschema-title-label',
			DESCRIPTION: '#wbschema-heading-description',
			ALIASES: '#wbschema-heading-aliases',
			SCHEMA_SHEXC: '#wbschema-schema-shexc'
		};
	}

	open( schemaId, query = {}, fragment = '' ) {
		super.openTitle( `Schema:${schemaId}`, query, fragment );
	}

	getNamespace() {
		const namespace = browser.executeAsync( ( done ) => {
			done( window.mw.config.get( 'wgCanonicalNamespace' ) );
		} ).value;
		return namespace;
	}

	getLabel() {
		browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).waitForVisible();
		return browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).getText();
	}

	getDescription() {
		browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION ).waitForVisible();
		return browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION ).getText();
	}

	getAliases() {
		browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES ).waitForVisible();
		return browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES ).getText();
	}

	getShExC() {
		browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_SHEXC ).waitForVisible();
		return browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_SHEXC ).getText();
	}

}

module.exports = new SchemaPage();
