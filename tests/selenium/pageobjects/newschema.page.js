'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NewSchemaPage extends Page {
	static get NEW_SCHEMA_SELECTORS() {
		return {
			LABEL: '#wbschema-newschema-label',
			DESCRIPTION: '#wbschema-newschema-description',
			ALIASES: '#wbschema-newschema-aliases',
			SUBMIT_BUTTON: '#wbschema-newschema-submit'
		};
	}

	open() {
		super.openTitle( 'Special:NewSchema' );
	}

	showsForm() {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL ).waitForVisible();
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION ).waitForVisible();
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES ).waitForVisible();

		return true;
	}

	setLabel( label ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.LABEL + ' input' ).setValue( label );
	}

	setDescription( description ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.DESCRIPTION + ' input' ).setValue( description );
	}

	setAliases( aliases ) {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.ALIASES + ' input' ).setValue( aliases );
	}

	clickSubmit() {
		browser.$( this.constructor.NEW_SCHEMA_SELECTORS.SUBMIT_BUTTON ).click();
	}
}

module.exports = new NewSchemaPage();
