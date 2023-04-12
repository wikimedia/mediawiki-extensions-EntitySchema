'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' ),
	NewEntitySchemaPage = require( '../../pageobjects/newentityschema.page' );

describe( 'NewEntitySchema:Page', () => {
	let bot;

	before( async () => {
		bot = await Api.bot();
	} );

	describe( 'when blocked', () => {

		/** necessary to translate between regular promises and WebdriverIO's magic concurrency */
		function blockUser() {
			let blocked = false;
			Api.blockUser( bot ).then( () => {
				blocked = true;
			} );
			browser.waitUntil( () => blocked );
		}

		afterEach( () => Api.unblockUser( bot ) );

		it( 'cannot load form', () => {
			blockUser();

			LoginPage.loginAdmin();
			NewEntitySchemaPage.open();

			$( '.permissions-errors' ).waitForDisplayed();
			assert.strictEqual( $( '#firstHeading' ).getText(), 'Permission error' );
		} );

		it( 'cannot submit form', () => {
			LoginPage.loginAdmin();
			NewEntitySchemaPage.open();
			NewEntitySchemaPage.setLabel( 'evil schema' );
			NewEntitySchemaPage.setDescription( 'should not be able to create this schema' );

			blockUser();

			NewEntitySchemaPage.clickSubmit();

			$( '.permissions-errors' ).waitForDisplayed();
			assert.strictEqual( $( '#firstHeading' ).getText(), 'Permission error' );
		} );
	} );

} );
