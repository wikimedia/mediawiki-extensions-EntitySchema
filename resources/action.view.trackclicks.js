( function () {
	'use strict';
	$( function () {
		$( '.wbschema-check-schema' ).click( function () {
			mw.track( 'counter.MediaWiki.wikibase.schema.external.checkSchema' );
		} );
	} );
}() );
