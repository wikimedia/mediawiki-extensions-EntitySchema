import { SpecialNewEntitySchemaPage } from '../../support/pageObjects/SpecialNewEntitySchemaPage.js';
import { ViewEntitySchemaPage } from '../../support/pageObjects/ViewEntitySchemaPage.js';
import { SpecialSetLabelDescriptionAliasesPage } from '../../support/pageObjects/SpecialSetLabelDescriptionAliasesPage.js';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewEntitySchemaPage();
const specialSetLabelDescriptionAliasesPage = new SpecialSetLabelDescriptionAliasesPage();

describe( 'SetEntitySchemaLabelDescriptionAliasesPage:Page', () => {
	beforeEach( 'create new schema page and open', () => {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'browser test: Special:SetEntitySchemaLabelDescriptionAliases' )
			.submit();
		viewSchemaPage.getId().as( 'entitySchemaId' );
	} );

	it( 'detects an edit conflict based on the baserev parameter', function () {
		specialSetLabelDescriptionAliasesPage
			.open()
			.setIdField( this.entitySchemaId )
			.submitIdForm();

		specialSetLabelDescriptionAliasesPage
			.setLabel( 'label that will conflict' );

		// act like the same form is submitted in a different window or tab
		cy.get( '#mw-content-text form' ).then( ( $form ) => {
			const url = $form[ 0 ].action;
			// eslint-disable-next-line n/no-unsupported-features/node-builtins
			const formData = new FormData( $form[ 0 ] );
			formData.set( 'label', 'label that was submitted first' );
			cy.request( {
				method: 'POST',
				url,
				body: formData,
			} );
		} );

		// try to submit "second" form
		specialSetLabelDescriptionAliasesPage
			.submitEditForm()
			.assertEditFormIsShown();

		viewSchemaPage.open( this.entitySchemaId ).assertLabel( 'label that was submitted first' );
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

	it( 'is possible to edit Schema in another language', function () {
		const langcode = 'de';

		specialSetLabelDescriptionAliasesPage
			.open()
			.setIdField( this.entitySchemaId )
			.setLanguageField( langcode )
			.submitIdForm();

		specialSetLabelDescriptionAliasesPage
			.setLabel( 'Label auf Deutsch' )
			.setDescription( 'Dies ist eine deutsche Testbeschreibung' )
			.setAliases( 'Alias1 | Alias2' )
			.submitEditForm();

		viewSchemaPage.open( this.entitySchemaId )
			.assertLabel( 'Label auf Deutsch', langcode )
			.assertDescription( 'Dies ist eine deutsche Testbeschreibung', langcode )
			.assertAliases( 'Alias1 | Alias2', langcode );
	} );

	it( 'has existing data already prefilled', function () {
		specialSetLabelDescriptionAliasesPage
			.open()
			.setIdField( this.entitySchemaId )
			.submitIdForm();

		specialSetLabelDescriptionAliasesPage
			.setLabel( 'Label' )
			.setDescription( 'Description' )
			.setAliases( 'Alias1 | Alias2' )
			.submitEditForm();

		specialSetLabelDescriptionAliasesPage
			.open()
			.setIdField( this.entitySchemaId )
			.submitIdForm();

		specialSetLabelDescriptionAliasesPage
			.assertLabel( 'Label' )
			.assertDescription( 'Description' )
			.assertAliases( 'Alias1|Alias2' );
	} );
} );
