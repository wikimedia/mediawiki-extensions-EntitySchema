import { SpecialNewEntitySchemaPage } from '../support/pageObjects/SpecialNewEntitySchemaPage';
import { ViewSchemaPage } from '../support/pageObjects/ViewSchemaPage';
import { EditSchemaPage } from '../support/pageObjects/EditSchemaPage';

const specialNewEntitySchemaPage = new SpecialNewEntitySchemaPage();
const viewSchemaPage = new ViewSchemaPage();
const editSchemaPage = new EditSchemaPage();

describe( 'Schema Edit Page', () => {

	describe( 'given that a user is allowed', () => {
		const initialSchemaTextExample = '<empty> {}';

		beforeEach( function () {
			specialNewEntitySchemaPage
				.open()
				.enterLabel( 'browser test: EntitySchema edit page' )
				.addSchemaText( initialSchemaTextExample )
				.submit();
			viewSchemaPage.getId().as( 'entitySchemaId' );
		} );

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

	describe.skip( 'given the user is blocked' );
} );
