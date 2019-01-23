'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class SpecialVersionPage extends Page {
	get wikibaseSchemaExtensionLink() {
		return browser.element( '#mw-version-ext-wikibase-WikibaseSchema' );
	}

	open() {
		super.openTitle( 'Special:Version' );
	}
}

module.exports = new SpecialVersionPage();
