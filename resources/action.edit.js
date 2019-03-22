/*!
 * JavaScript for the Schema action=edit form
 */
( function () {
	'use strict';

	$( function () {
		var schemaSchemaTextMaxSizeBytes = mw.config.get( 'wgWBSchemaSchemaTextMaxSizeBytes' ),
			schemaTextInput = OO.ui.infuse( 'mw-input-wpschema-text' );

		mw.widgets.visibleByteLimit( schemaTextInput, schemaSchemaTextMaxSizeBytes );
	} );
}() );
