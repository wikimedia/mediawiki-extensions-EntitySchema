'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ViewSchemaPage extends Page {

	static get SCHEMA_SELECTORS() {
		return {
			LABEL: '.wbschema-label[lang=en]',
			DESCRIPTION: '.wbschema-description[lang=en]',
			ALIASES: '.wbschema-aliases[lang=en]',
			SCHEMA_TEXT: '#wbschema-schema-text'
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
		browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).getText();
	}

	getDescription() {
		browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION ).getText();
	}

	getAliases() {
		browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES ).getText();
	}

	/**
	 * Note: This method unfortunately trims the content of the element
	 *
	 * @return {string}
	 */
	getSchemaText() {
		browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_TEXT ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_TEXT ).getText();
	}

	/**
	 * Return the schema text as it is in the HTML
	 *
	 * Note:
	 * that will return it without the webdriver mangling the whitespace, but with HTML entities
	 *
	 * @return {string}
	 */
	getSchemaTextHTML() {
		browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_TEXT ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.SCHEMA_TEXT ).getHTML( false );
	}

	getId() {
		browser.$( this.constructor.SCHEMA_SELECTORS.LABEL ).waitForVisible();
		let id = browser.execute( () => {
			return window.mw.config.get( 'wgTitle' );
		} );
		return id.value;
	}

}

module.exports = new ViewSchemaPage();
