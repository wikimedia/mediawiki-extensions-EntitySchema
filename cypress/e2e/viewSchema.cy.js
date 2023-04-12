import { SpecialNewEntitySchemaPage } from '../support/pageObjects/SpecialNewEntitySchemaPage';
import { ViewSchemaPage } from '../support/pageObjects/ViewSchemaPage';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewSchemaPage();

describe( 'Schema Viewing Page', () => {
	it( 'doesn\'t touch the whitespace inside the schema text', () => {
		const schemaTextWithSpaces = 'content\t is \n\n\n here';
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'browser test: whitespace in schema' )
			.addSchemaText( schemaTextWithSpaces )
			.submit();

		viewSchemaPage.assertSchemaText( schemaTextWithSpaces );
	} );
} );
