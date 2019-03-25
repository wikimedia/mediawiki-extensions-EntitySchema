'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' ),
	NewSchemaPage = require( '../../pageobjects/newschema.page' ),
	ViewSchemaPage = require( '../../pageobjects/view.schema.page' );

describe( 'NewSchema:Page', () => {

	it( 'request with "createpage" right shows form', () => {
		NewSchemaPage.open();

		assert.ok( NewSchemaPage.showsForm() );
	} );

	it( 'shows a submit button', () => {
		NewSchemaPage.open();
		NewSchemaPage.schemaSubmitButton.waitForVisible();
	} );

	it( 'is possible to create a new schema', () => {
		NewSchemaPage.open();
		NewSchemaPage.setLabel( 'Testlabel' );
		NewSchemaPage.setDescription( 'A schema created with selenium browser tests' );
		NewSchemaPage.setAliases( 'Testschema |Schema created by test' );
		NewSchemaPage.setSchemaText( '<empty> {}' );
		NewSchemaPage.clickSubmit();

		const actualLabel = ViewSchemaPage.getLabel(),
			actualDescription = ViewSchemaPage.getDescription(),
			actualAliases = ViewSchemaPage.getAliases(),
			actualSchemaText = ViewSchemaPage.getSchemaText(),
			actualNamespace = ViewSchemaPage.getNamespace();
		assert.strictEqual( actualLabel, 'Testlabel' );
		assert.strictEqual( actualDescription, 'A schema created with selenium browser tests' );
		assert.strictEqual( actualAliases, 'Testschema | Schema created by test' );
		assert.strictEqual( actualSchemaText, '<empty> {}' );
		assert.strictEqual( actualNamespace, 'Schema' );
	} );

	describe( 'when blocked', () => {

		/** necessary to translate between regular promises and WebdriverIO's magic concurrency */
		function blockUser() {
			let blocked = false;
			Api.blockUser().then( () => {
				blocked = true;
			} );
			browser.waitUntil( () => blocked );
		}

		afterEach( () => Api.unblockUser() );

		it( 'cannot load form', () => {
			blockUser();

			LoginPage.loginAdmin();
			NewSchemaPage.open();

			$( '#mw-returnto' ).waitForVisible();
			assert.strictEqual( $( '#firstHeading' ).getText(), 'User is blocked' );
		} );

		it( 'cannot submit form', () => {
			LoginPage.loginAdmin();
			NewSchemaPage.open();
			NewSchemaPage.setLabel( 'evil schema' );
			NewSchemaPage.setDescription( 'should not be able to create this schema' );

			blockUser();

			NewSchemaPage.clickSubmit();

			$( '#mw-returnto' ).waitForVisible();
			assert.strictEqual( $( '#firstHeading' ).getText(), 'User is blocked' );
		} );
	} );

	it( 'is possible to create a schema only with a label', () => {
		NewSchemaPage.open();
		NewSchemaPage.setLabel( 'Testlabel' );
		NewSchemaPage.clickSubmit();

		const actualLabel = ViewSchemaPage.getLabel(),
			actualDescription = ViewSchemaPage.getDescription(),
			actualAliases = ViewSchemaPage.getAliases(),
			actualNamespace = ViewSchemaPage.getNamespace();
		assert.strictEqual( actualLabel, 'Testlabel' );
		assert.strictEqual( actualDescription, '' );
		assert.strictEqual( actualAliases, '' );
		assert.strictEqual( actualNamespace, 'Schema' );
	} );

	it( 'limits the name badge input length', () => {
		let schemaNameBadgeMaxSizeChars, overlyLongString;

		NewSchemaPage.open();
		schemaNameBadgeMaxSizeChars = NewSchemaPage
			.getSchemaNameBadgeMaxSizeChars();
		overlyLongString = 'a'.repeat( schemaNameBadgeMaxSizeChars + 1 );

		NewSchemaPage.setLabel( overlyLongString );
		assert.strictEqual(
			NewSchemaPage.getLabel().length,
			schemaNameBadgeMaxSizeChars
		);

		NewSchemaPage.setDescription( overlyLongString );
		assert.strictEqual(
			NewSchemaPage.getDescription().length,
			schemaNameBadgeMaxSizeChars
		);

		NewSchemaPage.setAliases( overlyLongString );
		assert.strictEqual(
			NewSchemaPage.getAliases().length,
			schemaNameBadgeMaxSizeChars
		);

		NewSchemaPage.setAliases(
			'b' + '| '.repeat( schemaNameBadgeMaxSizeChars ) + 'c'
		);
		assert.strictEqual(
			NewSchemaPage.getAliases().length,
			schemaNameBadgeMaxSizeChars * 2 + 2,
			'Pipes and spaces will be trimmed from aliases before counting'
		);
	} );

	it( 'limits the schema text input length', () => {
		let schemaSchemaTextMaxSizeBytes;

		NewSchemaPage.open();
		schemaSchemaTextMaxSizeBytes = NewSchemaPage
			.getSchemaSchemaTextMaxSizeBytes();

		NewSchemaPage.pasteSchemaText(
			'a'.repeat( schemaSchemaTextMaxSizeBytes + 1 )
		);
		NewSchemaPage.addSchemaText( 'b' );
		assert.strictEqual(
			NewSchemaPage.getSchemaText().length,
			schemaSchemaTextMaxSizeBytes
		);
	} );

} );
