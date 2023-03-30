export class ViewSchemaPage {

	getId() {
		return cy.window().then( ( window ) => {
			return window.mw.config.get( 'wgTitle' );
		} );
	}

	assertSchemaText( expectedText ) {
		cy.get( '#entityschema-schema-text' ).should( 'have.text', expectedText );
		return this;
	}
}
