'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class SchemaPage extends Page {

	static get SCHEMA_SELECTORS() {
		return {
			LABEL: '#wbschema-title-label',
			DESCRIPTION: '#wbschema-heading-description',
			ALIASES: '#wbschema-heading-aliases'
		};
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

}

module.exports = new SchemaPage();
