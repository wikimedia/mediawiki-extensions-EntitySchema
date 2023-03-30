import { SpecialNewEntitySchemaPage } from '../../support/pageObjects/SpecialNewEntitySchemaPage';
import { ViewSchemaPage } from '../../support/pageObjects/ViewSchemaPage';
import { SpecialSetLabelDescriptionAliasesPage } from '../../support/pageObjects/SpecialSetLabelDescriptionAliasesPage';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewSchemaPage();
const specialSetLabelDescriptionAliasesPage = new SpecialSetLabelDescriptionAliasesPage();

describe( 'SetEntitySchemaLabelDescriptionAliasesPage:Page', function () {
	beforeEach( 'create new schema page and open', function () {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'browser test: Special:SetEntitySchemaLabelDescriptionAliases' )
			.submit();
		viewSchemaPage.getId().as( 'entitySchemaId' );
	} );

	it( 'limits the input length', function () {
		specialSetLabelDescriptionAliasesPage
			.open()
			.setIdField( this.entitySchemaId )
			.submitIdForm();

		cy.window().then( ( window ) => {
			const maxLength = window.mw.config.get( 'wgEntitySchemaNameBadgeMaxSizeChars' );
			const overlyLongString = 'a'.repeat( maxLength + 1 );

			specialSetLabelDescriptionAliasesPage
				.setLabel( overlyLongString )
				.assertLabelLength( maxLength );

			specialSetLabelDescriptionAliasesPage
				.setDescription( overlyLongString )
				.assertDescriptionLength( maxLength );

			specialSetLabelDescriptionAliasesPage
				.setAliases( overlyLongString )
				.assertAliasesLength( maxLength )
				.setAliases( 'b' + '| '.repeat( maxLength ) + 'c' )
				// Pipes and spaces will be trimmed from aliases before counting
				.assertAliasesLength( maxLength * 2 + 2 );
		} );

	} );

} );
