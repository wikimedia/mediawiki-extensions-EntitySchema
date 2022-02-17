'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' ),
	NewEntitySchemaPage = require( '../../pageobjects/newentityschema.page' ),
	ViewSchemaPage = require( '../../pageobjects/view.schema.page' );

describe( 'NewEntitySchema:Page', () => {
	let bot;

	before( async () => {
		bot = await Api.bot();
	} );

	it( 'request with "createpage" right shows form', () => {
		NewEntitySchemaPage.open();

		assert.ok( NewEntitySchemaPage.showsForm() );
	} );

	it( 'shows a submit button', () => {
		NewEntitySchemaPage.open();
		NewEntitySchemaPage.schemaSubmitButton.waitForDisplayed();
	} );

	it( 'is possible to create a new schema', () => {
		NewEntitySchemaPage.open();
		NewEntitySchemaPage.setLabel( 'Testlabel' );
		NewEntitySchemaPage.setDescription( 'A schema created with selenium browser tests' );
		NewEntitySchemaPage.setAliases( 'Testschema |Schema created by test' );
		NewEntitySchemaPage.setSchemaText( '<empty> {}' );
		NewEntitySchemaPage.clickSubmit();

		const actualLabel = ViewSchemaPage.getLabel(),
			actualDescription = ViewSchemaPage.getDescription(),
			actualAliases = ViewSchemaPage.getAliases(),
			actualSchemaText = ViewSchemaPage.getSchemaText(),
			actualNamespace = ViewSchemaPage.getNamespace();
		assert.strictEqual( actualLabel, 'Testlabel' );
		assert.strictEqual( actualDescription, 'A schema created with selenium browser tests' );
		assert.strictEqual( actualAliases, 'Testschema | Schema created by test' );
		assert.strictEqual( actualSchemaText, '<empty> {}' );
		assert.strictEqual( actualNamespace, 'EntitySchema' );
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

	it( 'is possible to create a schema only with a label', () => {
		NewEntitySchemaPage.open();
		NewEntitySchemaPage.setLabel( 'Testlabel' );
		NewEntitySchemaPage.clickSubmit();

		const actualLabel = ViewSchemaPage.getLabel(),
			actualDescription = ViewSchemaPage.getDescription(),
			actualAliases = ViewSchemaPage.getAliases(),
			actualNamespace = ViewSchemaPage.getNamespace();
		assert.strictEqual( actualLabel, 'Testlabel' );
		assert.strictEqual( actualDescription, '' );
		assert.strictEqual( actualAliases, '' );
		assert.strictEqual( actualNamespace, 'EntitySchema' );
	} );

	it( 'limits the name badge input length', () => {
		NewEntitySchemaPage.open();
		const schemaNameBadgeMaxSizeChars = NewEntitySchemaPage
			.getSchemaNameBadgeMaxSizeChars();
		const overlyLongString = 'a'.repeat( schemaNameBadgeMaxSizeChars + 1 );

		NewEntitySchemaPage.setLabel( overlyLongString );
		assert.strictEqual(
			NewEntitySchemaPage.getLabel().length,
			schemaNameBadgeMaxSizeChars
		);

		NewEntitySchemaPage.setDescription( overlyLongString );
		assert.strictEqual(
			NewEntitySchemaPage.getDescription().length,
			schemaNameBadgeMaxSizeChars
		);

		NewEntitySchemaPage.setAliases( overlyLongString );
		assert.strictEqual(
			NewEntitySchemaPage.getAliases().length,
			schemaNameBadgeMaxSizeChars
		);

		NewEntitySchemaPage.setAliases(
			'b' + '| '.repeat( schemaNameBadgeMaxSizeChars ) + 'c'
		);
		assert.strictEqual(
			NewEntitySchemaPage.getAliases().length,
			schemaNameBadgeMaxSizeChars * 2 + 2,
			'Pipes and spaces will be trimmed from aliases before counting'
		);
	} );

	it( 'limits the schema text input length', () => {
		NewEntitySchemaPage.open();
		const schemaSchemaTextMaxSizeBytes = NewEntitySchemaPage
			.getSchemaSchemaTextMaxSizeBytes();

		NewEntitySchemaPage.pasteSchemaText(
			'a'.repeat( schemaSchemaTextMaxSizeBytes + 1 )
		);
		NewEntitySchemaPage.addSchemaText( 'b' );
		assert.strictEqual(
			NewEntitySchemaPage.getSchemaText().length,
			schemaSchemaTextMaxSizeBytes
		);
	} );

} );
