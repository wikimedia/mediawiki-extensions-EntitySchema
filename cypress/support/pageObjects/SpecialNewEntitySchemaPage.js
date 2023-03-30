export class SpecialNewEntitySchemaPage {
	open() {
		cy.visit( 'index.php?title=Special:NewEntitySchema' );
		return this;
	}

	enterLabel( label ) {
		cy.get( 'input[name=label]' ).type( label, { force: true } );
		return this;
	}

	enterSchemaText( schemaText ) {
		cy.get( 'textarea[name=schema-text]' ).type( schemaText );
		return this;
	}

	submit() {
		cy.get( '#entityschema-newschema-submit' ).click();
		return this;
	}
}
