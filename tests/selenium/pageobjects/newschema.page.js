'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NewSchemaPage extends Page {
	static get NEW_SCHEMA_SELECTORS() {
		return {
			LABEL: '#wbschema-newschema-label',
			DESCRIPTION: '#wbschema-newschema-description',
			ALIASES: '#wbschema-newschema-aliases',
			SCHEMA_SHEXC: '#wbschema-newschema-schema-shexc',
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
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_SHEXC ).waitForVisible();

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

	setShExC( shExC ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_SHEXC + ' textarea' ).setValue( shExC );
	}

	/**
	 * Inserts the shExC via javascript/jQuery instead of "typing" it
	 *
	 * This method enables inserting the tab character
	 *
	 * @param {string} shExC
	 */
	pasteShExC( shExC ) {
		browser.executeAsync( ( query, shExC, done ) => {
			done( window.$( query ).val( shExC ) );
		}, this.constructor.NEW_SCHEMA_SELECTORS.SCHEMA_SHEXC + ' textarea', shExC );
	}

	clickSubmit() {
		this.schemaSubmitButton.click();
	}
}

module.exports = new NewSchemaPage();
