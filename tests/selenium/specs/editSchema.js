'use strict';

const assert = require( 'assert' ),
	NewEntitySchemaPage = require( '../pageobjects/newentityschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' ),
	EditSchemaPage = require( '../pageobjects/edit.schema.page' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' );

function createNewSchemaAndOpen( schemaText ) {
	NewEntitySchemaPage.open();
	NewEntitySchemaPage.showsForm();
	NewEntitySchemaPage.setLabel( 'foo' );
	NewEntitySchemaPage.setSchemaText( schemaText );
	NewEntitySchemaPage.clickSubmit();
}

describe( 'Schema Edit Page', () => {

	describe( 'given that a user is allowed', () => {
		const schemaText = '<some shex>';

		beforeEach( 'create new schema page and open', () => {
			createNewSchemaAndOpen( schemaText );
			// todo make with Api call
		} );

		it( 'has a text area', () => {
			const id = ViewSchemaPage.getId();
			EditSchemaPage.open( id );
			EditSchemaPage.schemaTextArea.waitForDisplayed();
			assert.strictEqual( EditSchemaPage.schemaText, schemaText );
			// todo assert that contents are there using api call
		} );

		it( 'it has a submit button', () => {
			const id = ViewSchemaPage.getId();
			EditSchemaPage.open( id );
			assert.ok( EditSchemaPage.submitButton.waitForDisplayed() );
		} );

		it( 'returns to schema view page on submit', () => {
			const id = ViewSchemaPage.getId(),
				viewSchemaUrl = browser.getUrl();
			EditSchemaPage.open( id );
			EditSchemaPage.clickSubmit();
			ViewSchemaPage.getId();
			assert.strictEqual( browser.getUrl(), viewSchemaUrl );

			// todo assert that contents are saved using api call
		} );

		it( 'detects an edit conflict when submitting the same form from two windows', () => {
			const id = ViewSchemaPage.getId();
			// First browser window
			EditSchemaPage.open( id );
			EditSchemaPage.schemaTextArea.setValue( 'shex that will conflict' );
			// not submitting the form yet

			const secondWindowUrl = browser.config.baseUrl + '/index.php?title=EntitySchema:' + id + '&action=edit&second=window';
			browser.newWindow( secondWindowUrl, { windowName: 'second window' } );
			browser.waitUntil( () => {
				try {
					browser.switchWindow( 'second=window' );
					return true;
				} catch ( e ) {
					return false;
				}
			} );
			// Second browser window
			EditSchemaPage.schemaTextArea.setValue( 'shex that is actually saved first' );
			EditSchemaPage.clickSubmit();
			browser.closeWindow();

			// back to first window
			EditSchemaPage.clickSubmit();
			assert.ok( EditSchemaPage.schemaTextArea );
			ViewSchemaPage.open( id );
			assert.strictEqual( ViewSchemaPage.getSchemaText(), 'shex that is actually saved first' );
		} );

		it( 'properly limits the input length', () => {
			const id = ViewSchemaPage.getId();

			EditSchemaPage.open( id );

			const schemaSchemaTextMaxSizeBytes = EditSchemaPage.getSchemaSchemaTextMaxSizeBytes();
			EditSchemaPage.setSchemaText( 'a'.repeat( schemaSchemaTextMaxSizeBytes ) );
			EditSchemaPage.schemaTextArea.addValue( 'b' );

			assert.strictEqual(
				EditSchemaPage.schemaTextArea.getValue().length,
				schemaSchemaTextMaxSizeBytes
			);
		} );
	} );

	describe( 'given the user is blocked', () => {
		let bot;

		before( async () => {
			bot = await Api.bot();
		} );

		beforeEach( () => {
			createNewSchemaAndOpen( '<bar>' );
			Api.blockUser( bot );
		} );

		afterEach( () => Api.unblockUser( bot ) );

		it( 'cannot be edited', () => {
			const id = ViewSchemaPage.getId();
			LoginPage.loginAdmin();
			EditSchemaPage.open( id );

			const $firstHeading = $( '#firstHeading' );
			$firstHeading.waitForDisplayed();
			assert.strictEqual( $firstHeading.getText(), 'Permission error' );
		} );

	} );

} );
