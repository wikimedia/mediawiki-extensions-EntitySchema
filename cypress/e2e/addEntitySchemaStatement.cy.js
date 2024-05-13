import { SpecialNewEntitySchemaPage } from '../support/pageObjects/SpecialNewEntitySchemaPage.js';
import { SpecialNewPropertyPage } from '../support/pageObjects/SpecialNewPropertyPage.js';
import { ViewEntitySchemaPage } from '../support/pageObjects/ViewEntitySchemaPage.js';
import { ViewPropertyPage } from '../support/pageObjects/ViewPropertyPage.js';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const specialNewPropertyPage = new SpecialNewPropertyPage();
const viewSchemaPage = new ViewEntitySchemaPage();
const viewPropertyPage = new ViewPropertyPage();

function randomTestId() {
	/**
	 * We want a number here that will be different every time we
	 * run the test. The current time to millisecond accuracy should
	 * work for this purpose
	 */
	return Date.now();
}

describe( 'Schema Viewing Page', () => {
	it( 'accepts statements of entity schema data value type', () => {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'browser test: schema to link to property' )
			.addSchemaText( 'some schema content' )
			.submit();
		viewSchemaPage.getId().then( ( entitySchemaId ) => {
			specialNewPropertyPage
				.open()
				.enterLabel( 'browser test ' + randomTestId() + ': property to link to schema' )
				.setPropertyType( 'EntitySchema' )
				.submit();
			viewPropertyPage.getId().then( ( propertyId ) => {
				viewPropertyPage
					.openAddStatementForm()
					.enterPropertyName( propertyId )
					.clickPropertySuggestion()
					.enterValueName( entitySchemaId )
					.clickValueSuggestion()
					.saveStatement()
					.followValueLink();
				viewSchemaPage.getId().should( 'eq', entitySchemaId );
			} );
		} );
	} );
} );
