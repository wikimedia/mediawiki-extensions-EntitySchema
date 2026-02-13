/**
 * This file is loaded before all spec files.
 */

require( 'cypress-terminal-report/src/installLogsCollector' )();
require( 'cypress-axe' );

Cypress.Commands.add( 'visitTitle', ( args, qsDefaults = {} ) => {
	let options = null;
	let title = null;
	if ( typeof args === 'string' ) {
		title = args;
		options = {
			qs: Object.assign( qsDefaults, {
				title: args,
			} ),
		};
	} else {
		options = args;
		title = options.title;
		if ( options.qs !== undefined ) {
			options.qs = Object.assign( qsDefaults, options.qs, { title } );
		} else {
			options.qs = Object.assign( qsDefaults, {
				title,
			} );
		}
	}
	cy.visit( Object.assign( options, { url: 'index.php' } ) );
	cy.injectAxe();
	return cy.window();
} );

Cypress.Commands.add( 'visitTitleMobile', ( args ) => cy.visitTitle( args, { useformat: 'mobile' } ) );
