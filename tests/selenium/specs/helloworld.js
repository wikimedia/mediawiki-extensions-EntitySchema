'use strict';

const assert = require( 'assert' ),
	SpecialVersionPage = require( '../pageobjects/specialversion.page' );

describe( 'Special:Version', function () {

	it( 'has the EntitySchema extension enabled', function () {
		SpecialVersionPage.open();
		assert( SpecialVersionPage.wikibaseSchemaExtensionLink.waitForDisplayed() );
	} );

} );
