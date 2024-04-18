( function ( QUnit, valueview ) {
	'use strict';

	const testExpert = valueview.tests.testExpert;

	QUnit.module( 'ext.EntitySchema.experts.EntitySchema' );

	testExpert( {
		expertConstructor: require( 'ext.EntitySchema.experts.EntitySchema' ),
	} );

}( QUnit, $.valueview ) );
