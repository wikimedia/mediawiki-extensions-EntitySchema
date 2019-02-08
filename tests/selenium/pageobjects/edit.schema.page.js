'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class EditSchemaPage extends Page {

	static get SCHEMA_EDIT_SELECTORS() {
		return {
			SCHEMAAREA: '#mw-input-wpschema > textarea',
			SUBMIT_BUTTON: '.mw-htmlform-submit-buttons > span > button'
		};
	}

	get schemaTextArea() {
		return browser.element( this.constructor.SCHEMA_EDIT_SELECTORS.SCHEMAAREA );
	}

	get submitButton() {
		return browser.element( this.constructor.SCHEMA_EDIT_SELECTORS.SUBMIT_BUTTON );
	}

	get ShExCContent() {
		return this.schemaTextArea.getValue();
	}

	clickSubmit() {
		this.submitButton.waitForVisible();
		this.submitButton.click();
	}

	open( id ) {
		super.openTitle( 'Schema:' + id, { action: 'edit' } );
	}

}

module.exports = new EditSchemaPage();
