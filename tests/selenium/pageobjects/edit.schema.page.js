'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class EditSchemaPage extends Page {

	static get SCHEMA_EDIT_SELECTORS() {
		return {
			SCHEMAAREA: '#mw-input-wpschema-text > textarea',
			SUBMIT_BUTTON: '.mw-htmlform-submit-buttons > span > button'
		};
	}

	get schemaTextArea() {
		return browser.element( this.constructor.SCHEMA_EDIT_SELECTORS.SCHEMAAREA );
	}

	get submitButton() {
		return browser.element( this.constructor.SCHEMA_EDIT_SELECTORS.SUBMIT_BUTTON );
	}

	get schemaText() {
		return this.schemaTextArea.getValue();
	}

	clickSubmit() {
		this.submitButton.waitForVisible();
		this.submitButton.click();
	}

	getSchemaSchemaTextMaxSizeBytes() {
		return browser.execute( () => {
			return mw.config.get( 'wgWBSchemaSchemaTextMaxSizeBytes' );
		} ).value;
	}

	setSchemaText( schemaText ) {
		// Go directly through the DOM in order to avoid having to slowly "type"
		// very long schema texts char by char.
		return browser.execute(
			( selector, schemaText ) => {
				$( selector ).val( schemaText );
			},
			this.constructor.SCHEMA_EDIT_SELECTORS.SCHEMAAREA,
			schemaText
		);
	}

	open( id ) {
		super.openTitle( 'EntitySchema:' + id, { action: 'edit' } );
	}

}

module.exports = new EditSchemaPage();
