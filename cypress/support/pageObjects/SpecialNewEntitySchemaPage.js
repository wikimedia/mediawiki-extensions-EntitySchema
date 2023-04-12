export class SpecialNewEntitySchemaPage {
	open() {
		cy.visit( 'index.php?title=Special:NewEntitySchema' );
		return this;
	}

	enterLabel( label ) {
		cy.get( 'input[name=label]' ).type( label, { force: true } );
		return this;
	}

	assertLabelLength( expectedLength ) {
		cy.get( 'input[name=label]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	enterDescription( description ) {
		cy.get( 'input[name=description]' ).type( description );
		return this;
	}

	assertDescriptionLength( expectedLength ) {
		cy.get( 'input[name=description]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	enterAliases( aliases ) {
		cy.get( 'input[name=aliases]' ).clear();
		cy.get( 'input[name=aliases]' ).type( aliases );
		return this;
	}

	assertAliasesLength( expectedLength ) {
		cy.get( 'input[name=aliases]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	setSchemaText( schemaText ) {
		cy.get( 'textarea[name=schema-text]' ).invoke( 'val', schemaText );
		return this;
	}

	addSchemaText( schemaText ) {
		cy.get( 'textarea[name=schema-text]' ).type( schemaText );
		return this;
	}

	assertSchemaTextLength( expectedLength ) {
		cy.get( 'textarea[name=schema-text]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	submit() {
		cy.get( '#entityschema-newschema-submit' ).click();
		return this;
	}
}
