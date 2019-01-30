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
} );
