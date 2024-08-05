import { SpecialNewEntitySchemaPage } from '../support/pageObjects/SpecialNewEntitySchemaPage.js';
import { ViewEntitySchemaPage } from '../support/pageObjects/ViewEntitySchemaPage.js';
import { EditEntitySchemaPage } from '../support/pageObjects/EditEntitySchemaPage.js';
import { LoginPage } from '../support/pageObjects/LoginPage.js';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewEntitySchemaPage();
const editSchemaPage = new EditEntitySchemaPage();
const loginPage = new LoginPage();

describe( 'Schema Edit Page', () => {
	const initialSchemaTextExample = '<empty> {}';

	beforeEach( () => {
		specialNewEntitySchemaPage
			.open()
			.enterLabel( 'browser test: EntitySchema edit page' )
			.addSchemaText( initialSchemaTextExample )
			.submit();
		viewSchemaPage.getId().as( 'entitySchemaId' );
	} );

	describe( 'given that a user is allowed', () => {

		it( 'returns to schema view page on submit', function () {
			const humanShape = '<HumanShape> {\n wd:Q5 wdt:P31 wd:Q5\n }';
			editSchemaPage
				.open( this.entitySchemaId )
				.assertSchemaText( initialSchemaTextExample )
				.addSchemaText( '{selectAll}{backspace}' + humanShape )
				.saveChanges();
			viewSchemaPage
				.assertSchemaText( humanShape )
				.getId().should( 'equal', this.entitySchemaId );

		} );

		it( 'detects an edit conflict when submitting the same form from two windows', function () {
			const conflictText = 'shex that will conflict';
			editSchemaPage
				.open( this.entitySchemaId )
				.addSchemaText( '{selectAll}{backspace}' + conflictText );

			// act like the same form is submitted in a different window or tab
			cy.get( '#mw-content-text form' ).then( ( $form ) => {
				const url = $form[ 0 ].action;
				// eslint-disable-next-line n/no-unsupported-features/node-builtins
				const formData = new FormData( $form[ 0 ] );
				formData.set( 'wpschema-text', 'shex that is actually saved first' );
				cy.request( {
					method: 'POST',
					url,
					body: formData,
				} );
			} );

			// try to submit "second" form
			editSchemaPage
				.saveChanges()
				.assertSchemaText( conflictText )
				.assertHasAlert();

			viewSchemaPage.open( this.entitySchemaId ).assertSchemaText( 'shex that is actually saved first' );
		} );

		it( 'properly limits the input length', function () {
			editSchemaPage
				.open( this.entitySchemaId );

			cy.window().then( ( window ) => {
				const maxLength = window.mw.config.get( 'wgEntitySchemaSchemaTextMaxSizeBytes' );
				const overlyLongString = 'a'.repeat( maxLength + 1 );

				editSchemaPage
					.setSchemaText( overlyLongString )
					.addSchemaText( 'some more chars' )
					.assertSchemaTextLength( maxLength );
			} );
		} );
	} );

	describe( 'given the user is blocked', () => {

		it( 'cannot be edited', function () {
			cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } )
				.then( ( { username, password } ) => {
					cy.task( 'MwApi:BlockUser', { username, reason: 'browser test: edit entity schema' } );
					loginPage.open();
					loginPage.login( username, password );
				} );

			editSchemaPage
				.open( this.entitySchemaId );

			cy.get( '.permissions-errors' ).should( 'be.visible' );
		} );
	} );
} );
