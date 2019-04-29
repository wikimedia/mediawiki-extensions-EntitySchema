( function () {
	'use strict';
	$( function () {
		$( '.entityschema-check-schema' ).click( function () {
			mw.track( 'counter.MediaWiki.EntitySchema.external.checkSchema' );
		} );
	} );
}() );
