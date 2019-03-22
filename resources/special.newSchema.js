/*!
 * JavaScript for the edit form on Special:SetSchemaLabelDescriptionAliases
 */
( function () {
	'use strict';

	function aliasesLengthString( aliases ) {
		return aliases
			.split( '|' )
			.map( function ( alias ) { return alias.trim(); } )
			.join( '' );
	}

	$( function () {
		var schemaNameBadgeMaxSizeChars = mw.config.get( 'wgWBSchemaNameBadgeMaxSizeChars' ),
			schemaSchemaTextMaxSizeBytes = mw.config.get( 'wgWBSchemaSchemaTextMaxSizeBytes' ),
			labelInput = OO.ui.infuse( 'wbschema-newschema-label' ),
			descriptionInput = OO.ui.infuse( 'wbschema-newschema-description' ),
			aliasInput = OO.ui.infuse( 'wbschema-newschema-aliases' ),
			schemaTextInput = OO.ui.infuse( 'wbschema-newschema-schema-text' );

		mw.widgets.visibleCodePointLimit( labelInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( descriptionInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( aliasInput, schemaNameBadgeMaxSizeChars, aliasesLengthString );
		mw.widgets.visibleByteLimit( schemaTextInput, schemaSchemaTextMaxSizeBytes );
	} );
}() );
