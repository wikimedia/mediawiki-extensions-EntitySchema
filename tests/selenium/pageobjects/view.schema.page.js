'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ViewSchemaPage extends Page {

	static get SCHEMA_SELECTORS() {
		return {
			LABEL: '.wbschema-label',
			DESCRIPTION: '.wbschema-description',
			ALIASES: '.wbschema-aliases',
			SCHEMA_TEXT: '#wbschema-schema-text'
		};
	}

	open( schemaId, query = {}, fragment = '' ) {
		super.openTitle( `EntitySchema:${schemaId}`, query, fragment );
	}

	getNamespace() {
		const namespace = browser.executeAsync( ( done ) => {
			done( window.mw.config.get( 'wgCanonicalNamespace' ) );
		} ).value;
		return namespace;
	}

	getLabel( langCode = 'en' ) {
		browser.$( this.constructor.SCHEMA_SELECTORS.LABEL + `[lang=${langCode}]` ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.LABEL + `[lang=${langCode}]` ).getText();
	}

	getDescription( langCode = 'en' ) {
		browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION + `[lang=${langCode}]` ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.DESCRIPTION + `[lang=${langCode}]` ).getText();
	}

	getAliases( langCode = 'en' ) {
		browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES + `[lang=${langCode}]` ).waitForExist();
		return browser.$( this.constructor.SCHEMA_SELECTORS.ALIASES + `[lang=${langCode}]` ).getText();
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
