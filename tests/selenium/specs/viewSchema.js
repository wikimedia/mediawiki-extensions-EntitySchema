'use strict';

const assert = require( 'assert' ),
	NewSchemaPage = require( '../pageobjects/newschema.page' ),
	ViewSchemaPage = require( '../pageobjects/view.schema.page' );

describe( 'Schema Viewing Page', () => {
	it( 'has an edit link', () => {
		NewSchemaPage.open();
		NewSchemaPage.showsForm();
		NewSchemaPage.setLabel( 'foo' );
		NewSchemaPage.setShExC( '<content:here>' );
		NewSchemaPage.clickSubmit();
		assert.ok( ViewSchemaPage.editLink.waitForVisible() );
	} );

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
