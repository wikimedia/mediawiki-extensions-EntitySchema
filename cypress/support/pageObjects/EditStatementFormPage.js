export class EditStatementFormPage {

	static get SELECTORS() {
		return {
			MODAL_ROOT: '.wikibase-wbui2025-edit-statement-group-modal',
			PROPERTY_NAME: '.wikibase-wbui2025-property-name > a',
			// Temporary backward-compatibility, to be removed in a followup (T440572)
			SUBMIT_BUTTONS: '.wikibase-wbui2025-edit-form-actions > .cdx-button, ' +
				'.wikibase-wbui2025-modal-overlay__footer__actions > .cdx-button',
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

	textInput() {
		return this.root().find( EditStatementFormPage.SELECTORS.TEXT_INPUT ).first();
	}

	publishButton() {
		return this.root().find( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).last();
	}

	cancelButton() {
		return this.root().find( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).first();
	}

	lookupInput() {
		return this.root().find( EditStatementFormPage.SELECTORS.LOOKUP_INPUT );
	}

	lookupComponent() {
		return this.root().find( EditStatementFormPage.SELECTORS.LOOKUP_COMPONENT );
	}

	menu() {
		return this.root().find( EditStatementFormPage.SELECTORS.MENU );
	}

	menuItems() {
		return this.root().find( EditStatementFormPage.SELECTORS.MENU_ITEM ).filter( ':visible' );
	}

	root() {
		return cy.get( EditStatementFormPage.SELECTORS.MODAL_ROOT );
	}

}
