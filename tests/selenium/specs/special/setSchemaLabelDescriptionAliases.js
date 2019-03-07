'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api.js' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage.js' ),
	NewSchemaPage = require( '../../pageobjects/newschema.page' ),
	SetSchemaLabelDescriptionAliasesPage = require( '../../pageobjects/setlabeldecriptionaliases.schema.page.js' ),
	ViewSchemaPage = require( '../../pageobjects/view.schema.page' );

describe( 'SetSchemaLabelDescriptionAliasesPage:Page', () => {

	beforeEach( 'create new schema page and open', () => {
		NewSchemaPage.open();
		NewSchemaPage.showsForm();
		NewSchemaPage.setLabel( 'Test Label' );
		NewSchemaPage.clickSubmit();
	} );

	it( 'request shows first form', () => {
		SetSchemaLabelDescriptionAliasesPage.open();
		assert.ok( SetSchemaLabelDescriptionAliasesPage.showsForm() );
	} );

	it( 'shows a schema set label, description aliases form and submit button', () => {
		let id = ViewSchemaPage.getId();
		SetSchemaLabelDescriptionAliasesPage.open();
		SetSchemaLabelDescriptionAliasesPage.schemaSubmitButton.waitForVisible();
	} );

	it( 'is possible to get Schema identifying information', () => {

		let id = ViewSchemaPage.getId();
		SetSchemaLabelDescriptionAliasesPage.open();
		SetSchemaLabelDescriptionAliasesPage.setIdField( id );
		SetSchemaLabelDescriptionAliasesPage.clickSubmit();

		assert.ok( SetSchemaLabelDescriptionAliasesPage.showsEditForm() );

	} );

	it( 'is possible to edit Schema identifying information', () => {

		let id = ViewSchemaPage.getId();
		SetSchemaLabelDescriptionAliasesPage.open();
		SetSchemaLabelDescriptionAliasesPage.setIdField( id );
		SetSchemaLabelDescriptionAliasesPage.clickSubmit();
		SetSchemaLabelDescriptionAliasesPage.setDescription( 'This is a test description' );
		SetSchemaLabelDescriptionAliasesPage.setAliases( 'Alias1 | Alias2' );
		SetSchemaLabelDescriptionAliasesPage.clickSubmit();

		assert.strictEqual( ViewSchemaPage.getDescription(), 'This is a test description' );
		assert.strictEqual( ViewSchemaPage.getAliases(), 'Alias1 | Alias2' );
	} );
} );
