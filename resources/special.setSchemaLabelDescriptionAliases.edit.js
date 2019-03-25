/*!
 * JavaScript for the edit form on Special:SetSchemaLabelDescriptionAliases
 */
( function () {
	'use strict';

	var codePointLength = require( 'mediawiki.String' ).codePointLength;

	/**
	 * Add a visible codepoint (character) limit label to a WikibaseSchema alias
	 * TextInputWidget, adapted from mw.widgets.visibleCodePointLimit.
	 *
	 * Uses jQuery#codePointLimit to enforce the limit.
	 *
	 * @param {OO.ui.TextInputWidget} textInputWidget Text input widget
	 * @param {number} limit Character limit
	 */
	function aliasVisibleCodePointLimit( textInputWidget, limit ) {
		function updateCount() {
			var valuesString = textInputWidget.getValue()
					.split( '|' )
					.map( function ( alias ) { return alias.trim(); } )
					.join( '' ),
				valuesStringLength = codePointLength( valuesString ),
				inputLength = codePointLength( textInputWidget.getValue() ),
				remaining = limit - valuesStringLength,
				hardLimit = limit + inputLength - valuesStringLength;

			if ( remaining > 99 ) {
				remaining = '';
			} else {
				remaining = mw.language.convertNumber( remaining );
			}
			textInputWidget.setLabel( remaining );

			// Actually enforce the limit, but make sure that stripped chars don't count
			textInputWidget.$input.codePointLimit( hardLimit );
		}

		textInputWidget.on( 'change', updateCount );
		// Initialise value
		updateCount();
	}

	$( function () {
		var schemaNameBadgeMaxSizeChars = mw.config.get( 'wgWBSchemaNameBadgeMaxSizeChars' ),
			labelInput = OO.ui.infuse( 'wbschema-title-label' ),
			descriptionInput = OO.ui.infuse( 'wbschema-heading-description' ),
			aliasInput = OO.ui.infuse( 'wbschema-heading-aliases' );

		mw.widgets.visibleCodePointLimit( labelInput, schemaNameBadgeMaxSizeChars );
		mw.widgets.visibleCodePointLimit( descriptionInput, schemaNameBadgeMaxSizeChars );
		aliasVisibleCodePointLimit( aliasInput, schemaNameBadgeMaxSizeChars );
	} );
}() );
