export class LoginPage {
	open() {
		cy.visit( 'index.php?title=Special:UserLogin' );
		return this;
	}

	loginAdmin() {
		this.login( Cypress.config( 'mediawikiAdminUsername' ), Cypress.config( 'mediawikiAdminPassword' ) );
		return this;
	}

	login( username, password ) {
		cy.get( '#wpName1' ).clear();
		cy.get( '#wpName1' ).type( username );
		cy.get( '#wpPassword1' ).type( password );
		cy.get( '#wpLoginAttempt' ).click();

		cy.window().its( 'mw.config' ).invoke( 'get', 'wgUserName' ).should( 'eq', username );

		return this;
	}
}
