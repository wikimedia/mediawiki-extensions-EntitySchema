( function () {
	'use strict';
	$( () => {
		$( '.entityschema-check-schema' ).on( 'click', () => {
			mw.track( 'counter.MediaWiki.EntitySchema.external.checkSchema' );
		} );
	} );
}() );
