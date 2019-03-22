'use strict';

const assert = require( 'assert' ),
	NewSchemaPage = require( '../pageobjects/newschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' ),
	EditSchemaPage = require( '../pageobjects/edit.schema.page' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' );

describe( 'Schema Edit Page', () => {

	describe( 'given that a user is allowed', () => {
		let schemaText = '<some shex>';

		beforeEach( 'create new schema page and open', () => {
			NewSchemaPage.open();
			NewSchemaPage.showsForm();
			NewSchemaPage.setLabel( 'foo' );
			NewSchemaPage.setSchemaText( schemaText );
			NewSchemaPage.clickSubmit();
			// todo make with Api call
		} );

		it( 'has a text area', () => {
			let id = ViewSchemaPage.getId();
			EditSchemaPage.open( id );
			EditSchemaPage.schemaTextArea.waitForVisible();
			assert.strictEqual( EditSchemaPage.schemaText, schemaText );
			// todo assert that contents are there using api call
		} );

		it( 'it has a submit button', () => {
			let id = ViewSchemaPage.getId();
			EditSchemaPage.open( id );
			assert.ok( EditSchemaPage.submitButton.waitForVisible() );
		} );

		it( 'returns to schema view page on submit', () => {
			let id = ViewSchemaPage.getId(),
				viewSchemaUrl = browser.getUrl();
			EditSchemaPage.open( id );
			EditSchemaPage.clickSubmit();
			ViewSchemaPage.getId();
			assert.strictEqual( browser.getUrl(), viewSchemaUrl );

			// todo assert that contents are saved using api call
		} );

		it( 'detects an edit conflict when re-submitting the same form', () => {
			let id = ViewSchemaPage.getId();
			EditSchemaPage.open( id );
			EditSchemaPage.schemaTextArea.setValue( 'edit conflict shex 1' );
			EditSchemaPage.clickSubmit();

			browser.back();
			EditSchemaPage.schemaTextArea.setValue( 'edit conflict shex 2' );
			EditSchemaPage.clickSubmit();

			assert.ok( EditSchemaPage.schemaTextArea );

			ViewSchemaPage.open( id );
			assert.strictEqual( ViewSchemaPage.getSchemaText(), 'edit conflict shex 1' );
		} );

		it( 'properly limits the input length', () => {
			let id = ViewSchemaPage.getId(),
				schemaSchemaTextMaxSizeBytes;

			EditSchemaPage.open( id );

			schemaSchemaTextMaxSizeBytes = EditSchemaPage.getSchemaSchemaTextMaxSizeBytes();
			EditSchemaPage.setSchemaText( 'a'.repeat( schemaSchemaTextMaxSizeBytes ) );
			EditSchemaPage.schemaTextArea.addValue( 'b' );

			assert.strictEqual(
				EditSchemaPage.schemaTextArea.getValue().length,
				schemaSchemaTextMaxSizeBytes
			);
		} );
	} );

	describe( 'given the user is blocked', () => {

		beforeEach( () => Api.blockUser() );

		afterEach( () => Api.unblockUser() );

		it( 'cannot be edited', () => {
			LoginPage.loginAdmin();
			ViewSchemaPage.open( 'O1', { action: 'edit' } );

			const firstHeading = $( '#firstHeading' );
			firstHeading.waitForVisible();
			assert.strictEqual( firstHeading.getText(), 'Permission error' );
		} );

	} );

} );
