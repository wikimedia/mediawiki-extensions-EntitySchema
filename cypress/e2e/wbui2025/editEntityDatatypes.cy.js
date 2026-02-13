import { Util } from 'cypress-wikibase-api';

import { AddStatementFormPage } from '../../support/pageObjects/AddStatementFormPage';
import { ItemViewPage } from '../../support/pageObjects/ItemViewPage';
import { EditStatementFormPage } from '../../support/pageObjects/EditStatementFormPage';
import { SpecialNewEntitySchemaPage } from '../../support/pageObjects/SpecialNewEntitySchemaPage';
import { ViewEntitySchemaPage } from '../../support/pageObjects/ViewEntitySchemaPage';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewEntitySchemaPage();

describe( 'wbui2025 entity schema datatype', () => {

	let itemId;
	let firstEntitySchemaId;
	const firstEntitySchemaLabel = Util.getTestString( 'entityschema-label' );
	let secondEntitySchemaId;
	const secondEntitySchemaLabel = Util.getTestString( 'entityschema-label' );
	const propertyName = Util.getTestString( 'entityschemaproperty-' );

	before( () => {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( firstEntitySchemaLabel )
			.enterDescription( 'A schema created with Cypress browser tests' )
			.enterAliases( 'Testschema |Schema created by test' )
			.addSchemaText( '<empty> {}' )
			.submit();

		viewSchemaPage.getId().then( ( newEntitySchemaId ) => {
			firstEntitySchemaId = newEntitySchemaId;
		} );

		specialNewEntitySchemaPage
			.open()
			.enterLabel( secondEntitySchemaLabel )
			.enterDescription( 'A schema created with Cypress browser tests' )
			.enterAliases( 'Testschema |Schema created by test' )
			.addSchemaText( '<empty> {}' )
			.submit();

		viewSchemaPage.getId().then( ( newEntitySchemaId ) => {
			secondEntitySchemaId = newEntitySchemaId;
			cy.task( 'MwApi:CreateItem', {
				label: Util.getTestString( 'testitem-' ),
				data: { claims: [] },
			} ).then( ( newItemId ) => {
				itemId = newItemId;
				cy.task( 'MwApi:CreateProperty', {
					label: propertyName,
					data: { datatype: 'entity-schema' },
				} ).as( 'propertyId' );
			} );
		} );
	} );

	function selectEntityById(
		editFormPage,
		newEntityId,
		newEntityLabel,
	) {
		editFormPage.lookupComponent()
			.should( 'exist' ).should( 'be.visible' );

		editFormPage.lookupInput().clear();
		editFormPage.lookupInput().type( newEntityId, { parseSpecialCharSequences: false } );
		editFormPage.lookupInput().should( 'have.value', newEntityId );
		editFormPage.lookupInput().focus();

		editFormPage.menu().should( 'be.visible' );

		editFormPage.menuItems().first().click();
		editFormPage.lookupInput().should( 'have.value', newEntityLabel );
	}

	context( 'mobile view - entity-schema datatype', () => {

		beforeEach( () => {
			cy.viewport( 375, 1280 );
		} );

		it( 'displays item statement and supports full editing workflow', () => {
			const itemViewPage = new ItemViewPage( itemId );
			itemViewPage.open().addStatementButton().click();

			const addStatementFormPage = new AddStatementFormPage();
			addStatementFormPage.propertyLookup().should( 'exist' );
			cy.get( '@propertyId' ).then( ( propertyId ) => {
				addStatementFormPage.setProperty( propertyId );
			} );
			addStatementFormPage.publishButton().should( 'be.disabled' );
			addStatementFormPage.snakValueInput().should( 'exist' );
			addStatementFormPage.setSnakValue( firstEntitySchemaId );
			addStatementFormPage.selectFirstSnakValueLookupItem();
			addStatementFormPage.publishButton().click();
			addStatementFormPage.form().should( 'not.exist' );
			itemViewPage.mainSnakValues().first().should( 'contain.text', firstEntitySchemaLabel );

			// Edit the property to point to the second schema
			itemViewPage.editLinks().first().should( 'exist' ).should( 'be.visible' );
			itemViewPage.editLinks().first().click();

			const editFormPage = new EditStatementFormPage();
			editFormPage.formHeading().should( 'exist' );
			editFormPage.propertyName().should( 'have.text', propertyName );

			selectEntityById( editFormPage, secondEntitySchemaId, secondEntitySchemaLabel );

			editFormPage.publishButton().click();

			/* Wait for the form to close, and check the value is changed */
			editFormPage.formHeading().should( 'not.exist' );
			itemViewPage.mainSnakValues().first().should( 'contain.text', secondEntitySchemaLabel );
		} );
	} );
} );
