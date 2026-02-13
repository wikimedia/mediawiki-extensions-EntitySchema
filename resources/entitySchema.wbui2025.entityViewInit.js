/**
 * @license GPL-2.0-or-later
 */
/**
 * @param {Object} wikibase Wikibase object
 */
( function () {
	'use strict';

	const wbui2025 = require( 'wikibase.wbui2025.lib' );

	class EntitySchemaValueStrategy extends wbui2025.store.EntityValueStrategy {
		constructor( editSnakStore ) {
			super( editSnakStore, 'entity-schema' );
		}

		async renderValueForTextInput( valueObject ) {
			if ( !valueObject.value.id ) {
				return '';
			}
			return wbui2025.api.renderSnakValueText( valueObject, this.editSnakStore.property );
		}
	}

	/**
	 * EntitySchema Value Strategy Registration
	 */
	wbui2025.store.snakValueStrategyFactory.registerStrategyForDatatype(
		'entity-schema',
		( store ) => new EntitySchemaValueStrategy( store ),
		( searchTerm ) => wbui2025.api.searchForEntities( searchTerm, 'entity-schema' ),
	);

}(
	wikibase,
) );
