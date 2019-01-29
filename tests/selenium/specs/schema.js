'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' ),
	SchemaPage = require( '../pageobjects/schema.page.js' );

describe( 'Schema Editing page', () => {

	describe( 'when blocked', () => {

		beforeEach( () => Api.blockUser() );

		afterEach( () => Api.unblockUser() );

		it( 'cannot be edited', () => {
			LoginPage.loginAdmin();
			SchemaPage.open( 'O1', { action: 'edit' } );

			$( '#mw-returnto' ).waitForVisible();
			assert.strictEqual( $( '#firstHeading' ).getText(), 'User is blocked' );
		} );

	} );

} );

describe( 'Schema Viewing Page', () => {
	it( 'has an edit link', () => {
		assert.ok( SchemaPage.editLink.waitForVisible() );
	} );
} );
