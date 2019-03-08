'use strict';

const assert = require( 'assert' ),
	NewSchemaPage = require( '../pageobjects/newschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' );

describe( 'Schema Viewing Page', () => {
	it( 'doesn\'t touch the whitespace inside the schema text', () => {
		const schemaTextWithSpaces = 'content\t is \n\n\n here';
		NewSchemaPage.open();
		NewSchemaPage.showsForm();
		NewSchemaPage.setLabel( 'Testing inner whitespace' );
		NewSchemaPage.pasteSchemaText( schemaTextWithSpaces );
		NewSchemaPage.clickSubmit();
		assert.strictEqual( schemaTextWithSpaces, ViewSchemaPage.getSchemaTextHTML() );
	} );
} );
