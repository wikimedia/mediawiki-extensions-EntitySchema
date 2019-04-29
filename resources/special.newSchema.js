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
			labelInput = OO.ui.infuse( 'entityschema-newschema-label' ),
			descriptionInput = OO.ui.infuse( 'entityschema-newschema-description' ),
			aliasInput = OO.ui.infuse( 'entityschema-newschema-aliases' ),
			schemaTextInput = OO.ui.infuse( 'entityschema-newschema-schema-text' );

		mw.widgets.visibleCodePointLimit( labelInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( descriptionInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( aliasInput, schemaNameBadgeMaxSizeChars, aliasesLengthString );
		mw.widgets.visibleByteLimit( schemaTextInput, schemaSchemaTextMaxSizeBytes );
	} );
}() );
