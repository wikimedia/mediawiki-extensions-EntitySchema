export class SpecialSetLabelDescriptionAliasesPage {
	open() {
		cy.visit( 'index.php?title=Special:SetEntitySchemaLabelDescriptionAliases' );
		return this;
	}

	setIdField( id ) {
		cy.get( 'input[name=ID]' ).type( id );
		return this;
	}

	setLanguageField( language ) {
		cy.get( 'input[name=languagecode]' ).clear();
		cy.get( 'input[name=languagecode]' ).type( language );
		return this;
	}

	submitIdForm() {
		cy.get( 'button[name=submit-selection]' ).click();
		return this;
	}

	submitEditForm() {
		cy.get( 'button[name=submit-edit]' ).click();
		return this;
	}

	assertEditFormIsShown() {
		cy.get( '#entityschema-title-label' ).should( 'be.visible' );
		cy.get( '#entityschema-heading-description' ).should( 'be.visible' );
		cy.get( '#entityschema-heading-aliases' ).should( 'be.visible' );
	}

	setLabel( label ) {
		cy.get( 'input[name=label]' ).clear( { force: true } );
		cy.get( 'input[name=label]' ).type( label );
		return this;
	}

	assertLabel( expectedLabel ) {
		cy.get( 'input[name=label]' ).should( 'have.value', expectedLabel );
		return this;
	}

	assertLabelLength( expectedLength ) {
		cy.get( 'input[name=label]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	setDescription( description ) {
		cy.get( 'input[name=description]' ).clear();
		cy.get( 'input[name=description]' ).type( description );
		return this;
	}

	assertDescription( expectedDescription ) {
		cy.get( 'input[name=description]' ).should( 'have.value', expectedDescription );
		return this;
	}

	assertDescriptionLength( expectedLength ) {
		cy.get( 'input[name=description]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}

	setAliases( aliases ) {
		cy.get( 'input[name=aliases]' ).clear();
		cy.get( 'input[name=aliases]' ).type( aliases );
		return this;
	}

	assertAliases( expectedAliases ) {
		cy.get( 'input[name=aliases]' ).should( 'have.value', expectedAliases );
		return this;
	}

	assertAliasesLength( expectedLength ) {
		cy.get( 'input[name=aliases]' ).invoke( 'val' ).its( 'length' ).should( 'eq', expectedLength );
		return this;
	}
}
