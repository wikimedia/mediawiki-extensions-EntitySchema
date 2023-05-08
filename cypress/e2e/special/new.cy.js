import { SpecialNewEntitySchemaPage } from '../../support/pageObjects/SpecialNewEntitySchemaPage';
import { ViewSchemaPage } from '../../support/pageObjects/ViewSchemaPage';
import { LoginPage } from '../../support/pageObjects/LoginPage';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewSchemaPage();
const loginPage = new LoginPage();

describe( 'NewEntitySchema:Page', () => {

	it( 'is possible to create a new schema with full data', () => {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'Testlabel' )
			.enterDescription( 'A schema created with Cypress browser tests' )
			.enterAliases( 'Testschema |Schema created by test' )
			.addSchemaText( '<empty> {}' )
			.submit();

		viewSchemaPage
			.assertLabel( 'Testlabel' )
			.assertDescription( 'A schema created with Cypress browser tests' )
			.assertAliases( 'Testschema | Schema created by test' )
			.assertSchemaText( '<empty> {}' );
	} );

	it( 'is possible to create a new schema with only a label', () => {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'Testlabel' )
			.submit();

		viewSchemaPage
			.assertLabel( 'Testlabel' )
			.assertSchemaText( '' );
	} );

	describe( 'when blocked', () => {

		it( 'cannot load form', () => {
			cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password } ) => {

				cy.task( 'MwApi:BlockUser', { username } );

				loginPage.open();
				loginPage.login( username, password );
			} );

			specialNewEntitySchemaPage
				.open();

			cy.get( '.permissions-errors' ).should( 'be.visible' );
		} );

		it( 'cannot submit form', () => {
			cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password } ) => {
				loginPage.open();
				loginPage.login( username, password );

				specialNewEntitySchemaPage
					.open()
					.enterLabel( 'evil schema' )
					.enterDescription( 'should not be able to create this schema' );

				cy.task( 'MwApi:BlockUser', { username } );
			} );

			specialNewEntitySchemaPage.submit();

			cy.get( '.permissions-errors' ).should( 'be.visible' );
		} );
	} );

	it( 'limits the name badge input length', () => {
		specialNewEntitySchemaPage
			.open();

		cy.window().then( ( window ) => {
			const maxLength = window.mw.config.get( 'wgEntitySchemaNameBadgeMaxSizeChars' );
			const overlyLongString = 'a'.repeat( maxLength + 1 );

			specialNewEntitySchemaPage
				.enterLabel( overlyLongString )
				.assertLabelLength( maxLength );

			specialNewEntitySchemaPage
				.enterDescription( overlyLongString )
				.assertDescriptionLength( maxLength );

			specialNewEntitySchemaPage
				.enterAliases( overlyLongString )
				.assertAliasesLength( maxLength );

			specialNewEntitySchemaPage
				.enterAliases( 'b' + '| '.repeat( maxLength ) + 'c' )
				// 'Pipes and spaces will be trimmed from aliases before counting'
				.assertAliasesLength( maxLength * 2 + 2 );
		} );
	} );

	it( 'limits the schema text input length', () => {
		specialNewEntitySchemaPage
			.open();

		cy.window().then( ( window ) => {
			const maxLength = window.mw.config.get( 'wgEntitySchemaSchemaTextMaxSizeBytes' );
			const overlyLongString = 'a'.repeat( maxLength + 1 );

			specialNewEntitySchemaPage
				.setSchemaText( overlyLongString )
				.addSchemaText( 'some more chars' )
				.assertSchemaTextLength( maxLength );
		} );
	} );
} );
