'use strict';

const assert = require( 'assert' ),
	NewEntitySchemaPage = require( '../../pageobjects/newentityschema.page' ),
	SetEntitySchemaLabelDescriptionAliasesPage = require( '../../pageobjects/setentityschemalabeldecriptionaliases.page' ),
	ViewSchemaPage = require( '../../pageobjects/view.schema.page' );

describe( 'SetEntitySchemaLabelDescriptionAliasesPage:Page', () => {

	beforeEach( 'create new schema page and open', () => {
		NewEntitySchemaPage.open();
		NewEntitySchemaPage.showsForm();
		NewEntitySchemaPage.setLabel( 'Test Label' );
		NewEntitySchemaPage.clickSubmit();
	} );

	it( 'request shows first form', () => {
		SetEntitySchemaLabelDescriptionAliasesPage.open();
		assert.ok( SetEntitySchemaLabelDescriptionAliasesPage.showsForm() );
	} );

	it( 'shows a schema set label, description aliases form and submit button', () => {
		SetEntitySchemaLabelDescriptionAliasesPage.open();
		SetEntitySchemaLabelDescriptionAliasesPage.schemaSubmitButton.waitForDisplayed();
	} );

	it( 'is possible to get Schema identifying information', () => {
		const id = ViewSchemaPage.getId();
		SetEntitySchemaLabelDescriptionAliasesPage.open();
		SetEntitySchemaLabelDescriptionAliasesPage.setIdField( id );
		SetEntitySchemaLabelDescriptionAliasesPage.clickSubmit();

		assert.ok( SetEntitySchemaLabelDescriptionAliasesPage.showsEditForm() );

	} );

	it( 'is possible to edit Schema identifying information', () => {

		const id = ViewSchemaPage.getId();
		SetEntitySchemaLabelDescriptionAliasesPage.open();
		SetEntitySchemaLabelDescriptionAliasesPage.setIdField( id );
		SetEntitySchemaLabelDescriptionAliasesPage.clickSubmit();
		SetEntitySchemaLabelDescriptionAliasesPage.showsEditForm();
		SetEntitySchemaLabelDescriptionAliasesPage.setDescription( 'This is a test description' );
		SetEntitySchemaLabelDescriptionAliasesPage.setAliases( 'Alias1 | Alias2' );
		SetEntitySchemaLabelDescriptionAliasesPage.clickSubmit();

		assert.strictEqual( ViewSchemaPage.getDescription(), 'This is a test description' );
		assert.strictEqual( ViewSchemaPage.getAliases(), 'Alias1 | Alias2' );
	} );

	it( 'is possible to edit Schema in another language', () => {

		const id = ViewSchemaPage.getId(),
			langCode = 'de';
		SetEntitySchemaLabelDescriptionAliasesPage.open();
		SetEntitySchemaLabelDescriptionAliasesPage.setIdField( id );
		SetEntitySchemaLabelDescriptionAliasesPage.setLanguageField( langCode );
		SetEntitySchemaLabelDescriptionAliasesPage.clickSubmit();
		SetEntitySchemaLabelDescriptionAliasesPage.showsEditForm();
		SetEntitySchemaLabelDescriptionAliasesPage.setDescription( 'Dies ist eine deutsche Testbeschreibung' );
		SetEntitySchemaLabelDescriptionAliasesPage.setAliases( 'Alias1 | Alias2' );
		SetEntitySchemaLabelDescriptionAliasesPage.clickSubmit();

		assert.strictEqual( ViewSchemaPage.getDescription( langCode ), 'Dies ist eine deutsche Testbeschreibung' );
		assert.strictEqual( ViewSchemaPage.getAliases( langCode ), 'Alias1 | Alias2' );
		assert.strictEqual( ViewSchemaPage.getLabel(), 'Test Label' );
	} );

	it( 'limits the input length', () => {
		const id = ViewSchemaPage.getId();

		SetEntitySchemaLabelDescriptionAliasesPage.open();
		SetEntitySchemaLabelDescriptionAliasesPage.setIdField( id );
		SetEntitySchemaLabelDescriptionAliasesPage.clickSubmit();
		SetEntitySchemaLabelDescriptionAliasesPage.showsEditForm();
		const schemaNameBadgeMaxSizeChars = SetEntitySchemaLabelDescriptionAliasesPage
			.getSchemaNameBadgeMaxSizeChars();
		const overlyLongString = 'a'.repeat( schemaNameBadgeMaxSizeChars + 1 );

		SetEntitySchemaLabelDescriptionAliasesPage.setLabel( overlyLongString );
		assert.strictEqual(
			SetEntitySchemaLabelDescriptionAliasesPage.getLabel().length,
			schemaNameBadgeMaxSizeChars
		);

		SetEntitySchemaLabelDescriptionAliasesPage.setDescription( overlyLongString, false );
		assert.strictEqual(
			SetEntitySchemaLabelDescriptionAliasesPage.getDescription().length,
			schemaNameBadgeMaxSizeChars
		);

		SetEntitySchemaLabelDescriptionAliasesPage.setAliases( overlyLongString );
		assert.strictEqual(
			SetEntitySchemaLabelDescriptionAliasesPage.getAliases().length,
			schemaNameBadgeMaxSizeChars
		);

		SetEntitySchemaLabelDescriptionAliasesPage.setAliases(
			'b' + '| '.repeat( schemaNameBadgeMaxSizeChars ) + 'c'
		);
		assert.strictEqual(
			SetEntitySchemaLabelDescriptionAliasesPage.getAliases().length,
			schemaNameBadgeMaxSizeChars * 2 + 2,
			'Pipes and spaces will be trimmed from aliases before counting'
		);
	} );
} );
