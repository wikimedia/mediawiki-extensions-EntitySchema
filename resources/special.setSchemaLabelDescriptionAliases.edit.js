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
		var schemaNameBadgeMaxSizeChars = mw.config.get( 'wgEntitySchemaNameBadgeMaxSizeChars' ),
			labelInput = OO.ui.infuse( 'entityschema-title-label' ),
			descriptionInput = OO.ui.infuse( 'entityschema-heading-description' ),
			aliasInput = OO.ui.infuse( 'entityschema-heading-aliases' );

		mw.widgets.visibleCodePointLimit( labelInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( descriptionInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( aliasInput, schemaNameBadgeMaxSizeChars, aliasesLengthString );
	} );
}() );
