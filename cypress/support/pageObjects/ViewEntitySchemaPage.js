export class ViewEntitySchemaPage {

	open( entitySchemaId ) {
		cy.visit( 'index.php?title=EntitySchema:' + entitySchemaId );
		return this;
	}

	getId() {
		return cy.window().then( ( window ) => {
			return window.mw.config.get( 'wgTitle' );
		} );
	}

	assertLabel( expectedLabel, langCode = 'en' ) {
		cy.get( `.entityschema-label[lang=${langCode}]` ).should( 'have.text', expectedLabel );
		return this;
	}

	assertDescription( expectedDescription, langCode = 'en' ) {
		cy.get( `.entityschema-description[lang=${langCode}]` ).should( 'have.text', expectedDescription );
		return this;
	}

	assertAliases( expectedAliases, langCode = 'en' ) {
		cy.get( `.entityschema-aliases[lang=${langCode}]` ).should( 'have.text', expectedAliases );
		return this;
	}

	assertSchemaText( expectedText ) {
		cy.get( '#entityschema-schema-text' ).should( ( $schemaTextPreElement ) => {
			expect( $schemaTextPreElement.text().trim() ).to.eq( expectedText );
		} );
		return this;
	}
}
