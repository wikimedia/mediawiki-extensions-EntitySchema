( function () {
	'use strict';
	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.entityschema-check-schema' ).on( 'click', function () {
			mw.track( 'counter.MediaWiki.EntitySchema.external.checkSchema' );
		} );
	} );
}() );
