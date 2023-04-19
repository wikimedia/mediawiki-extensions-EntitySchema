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
	NewEntitySchemaPage.setLabel( 'Edit Schema Browser test' );
	NewEntitySchemaPage.setSchemaText( schemaText );
	NewEntitySchemaPage.clickSubmit();
}

describe( 'Schema Edit Page', () => {

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
