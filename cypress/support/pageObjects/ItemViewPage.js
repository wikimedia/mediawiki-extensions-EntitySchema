export class ItemViewPage {
	static get SELECTORS() {
		return {
			STATEMENTS: '#wikibase-wbui2025-statementgrouplistview',
			VUE_CLIENTSIDE_RENDERED: '[data-v-app]',
			EDIT_LINKS: '.wikibase-wbui2025-edit-link',
			MAIN_SNAK_VALUES: '.wikibase-wbui2025-main-snak .wikibase-wbui2025-snak-value',
			ADD_STATEMENT_BUTTON: '.wikibase-wbui2025-add-statement-button>.cdx-button',
		};
	}

	constructor( itemId ) {
		this.itemId = itemId;
	}

	open( lang = 'en' ) {
		// We force tests to be in English by default, to be able to make assertions
		// about texts (especially, for example, selecting items from a Codex MenuButton
		// menu) without needing to modify Codex components or introduce translation
		// support to Cypress.
		cy.visitTitleMobile( { title: 'Item:' + this.itemId, qs: { uselang: lang } } );
		return this;
	}

	statementsSection() {
		return cy.get( ItemViewPage.SELECTORS.STATEMENTS );
	}

	editLinks() {
		return cy.get(
			ItemViewPage.SELECTORS.VUE_CLIENTSIDE_RENDERED + ' ' + ItemViewPage.SELECTORS.EDIT_LINKS,
		);
	}

	mainSnakValues() {
		return cy.get( ItemViewPage.SELECTORS.MAIN_SNAK_VALUES );
	}

	addStatementButton() {
		return cy.get( ItemViewPage.SELECTORS.VUE_CLIENTSIDE_RENDERED + ' ' + ItemViewPage.SELECTORS.ADD_STATEMENT_BUTTON );
	}
}
