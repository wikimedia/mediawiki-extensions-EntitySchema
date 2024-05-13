export class ViewPropertyPage {

	getId() {
		return cy.window().then( ( window ) => {
			return window.mw.config.get( 'wgTitle' );
		} );
	}

	openAddStatementForm() {
		cy.get( 'span.wikibase-toolbar-button-add' ).click();
		return this;
	}

	enterPropertyName( propertyId ) {
		cy.get( 'div.wikibase-snakview-property input' ).type( propertyId );
		return this;
	}

	clickPropertySuggestion() {
		cy.get( 'span.ui-entityselector-aliases' ).click();
		return this;
	}

	enterValueName( valueId ) {
		cy.get( 'textarea.valueview-input' ).type( valueId );
		return this;
	}

	clickValueSuggestion() {
		cy.get( 'span.ui-entityselector-itemcontent' ).should( 'be.visible' ).then( () => {
			cy.get( 'span.ui-entityselector-itemcontent' ).last().click();
		} );
		return this;
	}

	saveStatement() {
		cy.get( 'span.wikibase-toolbar-button-save' )
			.should( 'have.attr', 'aria-disabled' )
			.and( 'match', /false/ )
			.then( () => {
				cy.get( 'span.wikibase-toolbar-button-save' ).click();
			} );
		return this;
	}

	followValueLink() {
		cy.get( 'div.wikibase-snakview-variation-valuesnak a' ).click();
		return this;
	}
}
