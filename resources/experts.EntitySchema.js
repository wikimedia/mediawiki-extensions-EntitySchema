module.exports = ( function ( wb, vv ) {
	'use strict';

	const PARENT = wb.experts.Entity;

	/**
	 * `valueview` `Expert` for specifying a reference to an `EntitySchema`.
	 *
	 * @see jQuery.valueview.expert
	 * @see jQuery.valueview.Expert
	 * @class ext.EntitySchema.experts.EntitySchema
	 * @extends wikibase.experts.Entity
	 * @license GPL-2.0-or-later
	 */
	const SELF = vv.expert( 'entity-schema', PARENT, {
		/**
		 * @inheritdoc
		 */
		_init: function () {
			// eslint-disable-next-line no-underscore-dangle
			PARENT.prototype._initEntityExpert.call( this );
		},
	} );

	/**
	 * @inheritdoc
	 */
	SELF.TYPE = 'entity-schema';

	return SELF;

}( wikibase, $.valueview ) );
