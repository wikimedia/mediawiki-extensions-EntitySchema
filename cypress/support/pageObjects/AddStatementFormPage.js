export class AddStatementFormPage {
	static get SELECTORS() {
		return {
			PROPERTY_LOOKUP: '.wikibase-wbui2025-add-statement-form_property-selector',
			PROPERTY_INPUT: '.wikibase-wbui2025-property-lookup input',
			SUBMIT_BUTTONS: '.wikibase-wbui2025-modal-overlay__footer__actions > .cdx-button',
			SNAK_VALUE_INPUT: '.wikibase-wbui2025-edit-statement-snak-value input',
			FORM: '.wikibase-wbui2025-add-statement-form',
			TIME_OPTION_SELECT: '.time-options .option-and-select select',
		};
	}

	propertyLookup() {
		return cy.get( AddStatementFormPage.SELECTORS.PROPERTY_LOOKUP );
	}

	propertyInput() {
		return cy.get( AddStatementFormPage.SELECTORS.PROPERTY_INPUT );
	}

	publishButton() {
		return cy.get( AddStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).last();
	}

	snakValueInput() {
		return cy.get( AddStatementFormPage.SELECTORS.SNAK_VALUE_INPUT );
	}

	cancelButton() {
		return cy.get( AddStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).first();
	}

	form() {
		return cy.get( AddStatementFormPage.SELECTORS.FORM );
	}

	setProperty( searchTerm ) {
		this.propertyInput().clear();
		this.propertyInput().type( searchTerm, { parseSpecialCharSequences: false } );
		this.propertyInput().should( 'have.value', searchTerm );
		this.propertyInput().focus();

		cy.get( '.wikibase-wbui2025-property-lookup .cdx-menu' ).should( 'be.visible' );
		this.getFirstPropertyLookupItem().click();

		return this;
	}

	getFirstPropertyLookupItem() {
		return cy.get( '.wikibase-wbui2025-property-lookup .cdx-menu-item:first:not(.cdx-menu__no-results)' );
	}

	setSnakValue( inputText ) {
		this.snakValueInput().clear();
		this.snakValueInput().type( inputText, { parseSpecialCharSequences: false } );
		this.snakValueInput().should( 'have.value', inputText );
		return this;
	}

	selectFirstSnakValueLookupItem() {
		cy.get( '.wikibase-wbui2025-snak-value .cdx-menu-item:first' ).click();
		return this;
	}

}
