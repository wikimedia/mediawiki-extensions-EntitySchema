'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class SetEntitySchemaLabelDescriptionAliasesPage extends Page {
	static get SCHEMA_NAMEBADGE_SELECTORS() {
		return {
			ID: '#entityschema-special-schema-id',
			LANGUAGE: '#entityschema-language-code',
			SUBMIT_BUTTON: '#entityschema-special-schema-id-submit',
			LABEL: '#entityschema-title-label',
			DESCRIPTION: '#entityschema-heading-description',
			ALIASES: '#entityschema-heading-aliases'
		};
	}

	open() {
		super.openTitle( 'Special:SetEntitySchemaLabelDescriptionAliases' );
	}

	get schemaSubmitButton() {
		return browser.element( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.SUBMIT_BUTTON );
	}

	showsForm() {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.ID ).waitForVisible();
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.LANGUAGE ).waitForVisible();

		return true;
	}

	showsEditForm() {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.LABEL ).waitForVisible();
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.DESCRIPTION ).waitForVisible();
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.ALIASES ).waitForVisible();
		return true;
	}

	getLabel() {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.LABEL ).waitForExist();
		return browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.LABEL + ' input' ).getValue();
	}

	getDescription() {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.DESCRIPTION ).waitForExist();
		return browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.DESCRIPTION + ' input' ).getValue();
	}

	getAliases() {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.ALIASES ).waitForExist();
		return browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.ALIASES + ' input' ).getValue();
	}

	getSchemaNameBadgeMaxSizeChars() {
		return browser.execute( () => {
			return mw.config.get( 'wgWBSchemaNameBadgeMaxSizeChars' );
		} ).value;
	}

	setIdField( id ) {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.ID + ' input' ).setValue( id );
	}

	setLanguageField( langCode ) {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.LANGUAGE + ' input' ).setValue( langCode );
	}

	setLabel( label ) {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.LABEL + ' input' ).setValue( label );
	}

	setDescription( description ) {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.DESCRIPTION + ' input' ).setValue( description );
	}

	setAliases( aliases ) {
		browser.$( this.constructor.SCHEMA_NAMEBADGE_SELECTORS.ALIASES + ' input' ).setValue( aliases );
	}

	clickSubmit() {
		this.schemaSubmitButton.click();
	}
}

module.exports = new SetEntitySchemaLabelDescriptionAliasesPage();
