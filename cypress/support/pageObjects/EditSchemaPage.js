export class EditSchemaPage {
	open( entitySchemaId ) {
		cy.visit( 'index.php?action=edit&title=EntitySchema:' + entitySchemaId );
		return this;
	}

	assertSchemaText( schemaText ) {
		cy.get( 'textarea[name=wpschema-text]' ).should( 'have.value', schemaText );
		return this;
	}

	setSchemaText( schemaText ) {
		cy.get( 'textarea[name=wpschema-text]' ).invoke( 'val', schemaText );
		return this;
	}

	addSchemaText( schemaText ) {
		cy.get( 'textarea[name=wpschema-text]' ).click();
		cy.get( 'textarea[name=wpschema-text]' ).type( schemaText );
		return this;
	}

	assertSchemaTextLength( expectedLength ) {
		cy.get( 'textarea[name=wpschema-text]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	saveChanges() {
		cy.get( 'button[name=wpSave]' ).click();
		return this;
	}

	assertHasAlert() {
		cy.get( '#mw-content-text form [role=alert]' ).should( 'exist' );
		return this;
	}
}
