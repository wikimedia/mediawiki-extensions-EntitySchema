( function () {
	'use strict';
	$( function () {
		$( '.entityschema-check-schema' ).on( 'click', function () {
			mw.track( 'counter.MediaWiki.EntitySchema.external.checkSchema' );
		} );
	} );
}() );
