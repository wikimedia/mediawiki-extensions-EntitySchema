'use strict';

const assert = require( 'assert' ),
	NewSchemaPage = require( '../pageobjects/newschema.page' ),
	SchemaPage = require( '../pageobjects/schema.page' ),
	SchemaEditPage = require( '../pageobjects/schemaedit.page' );

describe( 'Schema Edit Page', () => {

	describe( 'given that a user is allowed', () => {
		let ShExCContent = '<some shex>';

		beforeEach( 'create new schema page and open', () => {
			NewSchemaPage.open();
			NewSchemaPage.showsForm();
			NewSchemaPage.setLabel( 'foo' );
			NewSchemaPage.setShExC( ShExCContent );
			NewSchemaPage.clickSubmit();
			// todo make with Api call
		} );

		it( 'has a text area', () => {
			let id = SchemaPage.getId();
			SchemaEditPage.open( id );
			SchemaEditPage.schemaTextArea.waitForVisible();
			assert.strictEqual( SchemaEditPage.shExCContent, ShExCContent );
			// todo assert that contents are there using api call
		} );

		it( 'it has a submit button', () => {
			let id = SchemaPage.getId();
			SchemaEditPage.open( id );
			assert.ok( SchemaEditPage.submitButton.waitForVisible() );
		} );

		it( 'returns to schema view page on submit', () => {
			let id = SchemaPage.getId(),
				viewSchemaUrl = browser.getUrl();
			SchemaEditPage.open( id );
			SchemaEditPage.clickSubmit();
			SchemaPage.getId();
			assert.strictEqual( browser.getUrl(), viewSchemaUrl );

			// todo assert that contents are saved using api call
		} );
	} );

} );
