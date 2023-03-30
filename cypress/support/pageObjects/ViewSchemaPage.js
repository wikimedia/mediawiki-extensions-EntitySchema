export class ViewSchemaPage {

	assertSchemaText( expectedText ) {
		cy.get( '#entityschema-schema-text' ).should( 'have.text', expectedText );
		return this;
	}
}
