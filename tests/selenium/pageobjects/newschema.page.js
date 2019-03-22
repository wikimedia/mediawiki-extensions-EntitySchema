'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NewSchemaPage extends Page {
	static get NEW_SCHEMA_SELECTORS() {
		return {
			LABEL: '#wbschema-newschema-label',
			DESCRIPTION: '#wbschema-newschema-description',
			ALIASES: '#wbschema-newschema-aliases',
			SCHEMA_TEXT: '#wbschema-newschema-schema-text',
			SUBMIT_BUTTON: '#wbschema-newschema-submit'
		};
	}

	open() {
		super.openTitle( 'Special:NewSchema' );
	}
	get schemaSubmitButton() {
		return browser.element( this.constructor.NEW_SCHEMA_SELECTORS.SUBMIT_BUTTON );
	}

	showsForm() {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL ).waitForVisible();
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION ).waitForVisible();
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES ).waitForVisible();
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT ).waitForVisible();

		return true;
	}

	setLabel( label ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL + ' input' ).setValue( label );
	}

	setDescription( description ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION + ' input' ).setValue( description );
	}

	setAliases( aliases ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES + ' input' ).setValue( aliases );
	}

	setSchemaText( schemaText ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT + ' textarea' ).setValue( schemaText );
	}

	addSchemaText( schemaText ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT + ' textarea' ).addValue( schemaText );
	}

	getSchemaNameBadgeMaxSizeChars() {
		return browser.execute( () => {
			return mw.config.get( 'wgWBSchemaNameBadgeMaxSizeChars' );
		} ).value;
	}

	getSchemaSchemaTextMaxSizeBytes() {
		return browser.execute( () => {
			return mw.config.get( 'wgWBSchemaSchemaTextMaxSizeBytes' );
		} ).value;
	}

	getLabel( label ) {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL + ' input' ).getValue();
	}

	getDescription( description ) {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION + ' input' ).getValue();
	}

	getAliases( aliases ) {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES + ' input' ).getValue();
	}

	getSchemaText( schemaText ) {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT + ' textarea' ).getValue();
	}

	/**
	 * Inserts the SchemaText via javascript/jQuery instead of "typing" it
	 *
	 * This method enables inserting the tab character
	 *
	 * @param {string} schemaText
	 */
	pasteSchemaText( schemaText ) {
		browser.executeAsync( ( query, schemaText, done ) => {
			done( window.$( query ).val( schemaText ) );
		}, this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT + ' textarea', schemaText );
	}

	clickSubmit() {
		this.schemaSubmitButton.click();
	}
}

module.exports = new NewSchemaPage();
