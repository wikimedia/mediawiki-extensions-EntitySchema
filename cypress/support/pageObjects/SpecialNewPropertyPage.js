export class SpecialNewPropertyPage {
	open() {
		cy.visit( 'index.php?title=Special:NewProperty' );
		return this;
	}

	enterLabel( label ) {
		cy.get( 'input[name=label]' ).type( label, { force: true } );
		return this;
	}

	setPropertyType( propertyType ) {
		cy.get( 'select[name=datatype]' ).select( propertyType, { force: true } );
		return this;
	}

	submit() {
		cy.get( 'button[name=submit]' ).click();
		return this;
	}
}
