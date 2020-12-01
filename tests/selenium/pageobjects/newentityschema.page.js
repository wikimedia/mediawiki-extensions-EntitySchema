'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NewEntitySchemaPage extends Page {
	static get NEW_SCHEMA_SELECTORS() {
		return {
			LABEL: '#entityschema-newschema-label',
			DESCRIPTION: '#entityschema-newschema-description',
			ALIASES: '#entityschema-newschema-aliases',
			SCHEMA_TEXT: '#entityschema-newschema-schema-text',
			SUBMIT_BUTTON: '#entityschema-newschema-submit'
		};
	}

	open() {
		super.openTitle( 'Special:NewEntitySchema' );
	}
	get schemaSubmitButton() {
		return $( this.constructor.NEW_SCHEMA_SELECTORS.SUBMIT_BUTTON );
	}

	showsForm() {
		browser.waitUntil( () =>
			browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL ).isDisplayed() &&
			browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION ).isDisplayed() &&
			browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES ).isDisplayed() &&
			browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT ).isDisplayed()
		);
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
			return mw.config.get( 'wgEntitySchemaNameBadgeMaxSizeChars' );
		} );
	}

	getSchemaSchemaTextMaxSizeBytes() {
		return browser.execute( () => {
			return mw.config.get( 'wgEntitySchemaSchemaTextMaxSizeBytes' );
		} );
	}

	getLabel() {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL + ' input' ).getValue();
	}

	getDescription() {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION + ' input' ).getValue();
	}

	getAliases() {
		return browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES + ' input' ).getValue();
	}

	getSchemaText() {
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
		browser.executeAsync( ( query, innerSchemaText, done ) => {
			done( window.$( query ).val( innerSchemaText ) );
		}, this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_TEXT + ' textarea', schemaText );
	}

	clickSubmit() {
		this.schemaSubmitButton.click();
	}
}

module.exports = new NewEntitySchemaPage();
