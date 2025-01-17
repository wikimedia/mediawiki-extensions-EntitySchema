( function () {
	'use strict';
	$( () => {
		$( '.entityschema-check-schema' ).on( 'click', () => {
			mw.track( 'counter.MediaWiki.EntitySchema.external.checkSchema' );
			mw.track( 'stats.mediawiki_EntitySchema_external_checkschema_total' );
		} );
	} );
}() );
