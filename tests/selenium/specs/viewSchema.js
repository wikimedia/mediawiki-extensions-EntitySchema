'use strict';

const assert = require( 'assert' ),
	NewEntitySchemaPage = require( '../pageobjects/newentityschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' );

describe( 'Schema Viewing Page', () => {
	it( 'doesn\'t touch the whitespace inside the schema text', () => {
		const schemaTextWithSpaces = 'content\t is \n\n\n here';
		NewEntitySchemaPage.open();
		NewEntitySchemaPage.showsForm();
		NewEntitySchemaPage.setLabel( 'Testing inner whitespace' );
		NewEntitySchemaPage.pasteSchemaText( schemaTextWithSpaces );
		NewEntitySchemaPage.clickSubmit();
		assert.strictEqual( schemaTextWithSpaces, ViewSchemaPage.getSchemaTextHTML() );
	} );
} );
