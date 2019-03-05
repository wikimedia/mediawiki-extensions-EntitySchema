'use strict';

const assert = require( 'assert' ),
	NewSchemaPage = require( '../pageobjects/newschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' ),
	EditSchemaPage = require( '../pageobjects/edit.schema.page' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' );

describe( 'Schema Edit Page', () => {

	describe( 'given that a user is allowed', () => {
		let ShExCContent = '<some shex>';

		beforeEach( 'create new schema page and open', () => {
			NewSchemaPage.open();
			NewSchemaPage.showsForm();
			NewSchemaPage.setLabel( 'foo' );
			NewSchemaPage.setSchemaText( ShExCContent );
			NewSchemaPage.clickSubmit();
			// todo make with Api call
		} );

		it( 'has a text area', () => {
			let id = ViewSchemaPage.getId();
			EditSchemaPage.open( id );
			EditSchemaPage.schemaTextArea.waitForVisible();
			assert.strictEqual( EditSchemaPage.ShExCContent, ShExCContent );
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
