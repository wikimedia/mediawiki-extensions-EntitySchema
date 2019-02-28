'use strict';

const assert = require( 'assert' ),
	NewSchemaPage = require( '../pageobjects/newschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' );

describe( 'Schema Viewing Page', () => {
	it( 'doesn\'t touch the whitespace inside the schema', () => {
		const ShExCWithSpaces = 'content\t is \n\n\n here';
		NewSchemaPage.open();
		NewSchemaPage.showsForm();
		NewSchemaPage.setLabel( 'Testing inner whitespace' );
		NewSchemaPage.pasteShExC( ShExCWithSpaces );
		NewSchemaPage.clickSubmit();
		assert.strictEqual( ShExCWithSpaces, ViewSchemaPage.getShExCHTML() );
	} );
} );
