export class EditStatementFormPage {

	static get SELECTORS() {
		return {
			MODAL_ROOT: '.wikibase-wbui2025-edit-statement-group-modal',
			FORM_HEADING: '.wikibase-wbui2025-edit-statement-heading',
			PROPERTY_NAME: '.wikibase-wbui2025-property-name > a',
			SUBMIT_BUTTONS: '.wikibase-wbui2025-edit-form-actions > .cdx-button',
			TEXT_INPUT: '.wikibase-wbui2025-edit-statement-value-input .cdx-text-input input',
			LOOKUP_INPUT: '.wikibase-wbui2025-edit-statement-value-input .cdx-lookup input',
			LOOKUP_COMPONENT: '.wikibase-wbui2025-edit-statement-value-input .cdx-lookup',
			MENU: '.wikibase-wbui2025-edit-statement-value-input .cdx-menu',
			MENU_ITEM: '.wikibase-wbui2025-edit-statement-value-input .cdx-menu-item',
			VALUE_FORMS: '.wikibase-wbui2025-edit-statement-value-form',
		};
	}

	propertyName() {
		return this.root().find( EditStatementFormPage.SELECTORS.PROPERTY_NAME );
	}

	formHeading() {
		return cy.get( EditStatementFormPage.SELECTORS.FORM_HEADING );
	}

	textInput() {
		return cy.get( EditStatementFormPage.SELECTORS.TEXT_INPUT ).first();
	}

	publishButton() {
		return cy.get( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).last();
	}

	cancelButton() {
		return cy.get( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).first();
	}

	lookupInput() {
		return cy.get( EditStatementFormPage.SELECTORS.LOOKUP_INPUT );
	}

	lookupComponent() {
		return cy.get( EditStatementFormPage.SELECTORS.LOOKUP_COMPONENT );
	}

	menu() {
		return cy.get( EditStatementFormPage.SELECTORS.MENU );
	}

	menuItems() {
		return cy.get( EditStatementFormPage.SELECTORS.MENU_ITEM ).filter( ':visible' );
	}

	root() {
		return cy.get( EditStatementFormPage.SELECTORS.MODAL_ROOT );
	}

}
