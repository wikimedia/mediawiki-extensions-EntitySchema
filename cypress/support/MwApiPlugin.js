/**
 * Helper methods for generic MediaWiki API functionality separate from the Cypress browser context
 *
 * This file is intended to be extracted into a separate npm package,
 * so that it can be used across extensions.
 */

// needed for api-testing library, see api-testing/lib/config.js
process.env.REST_BASE_URL = process.env.MW_SERVER + process.env.MW_SCRIPT_PATH + '/';

const { clientFactory, utils } = require( 'api-testing' );

const state = {
	users: {},
};

module.exports = {
	mwApiCommands( cypressConfig ) {
		async function root() {
			if ( state.users.root ) {
				return state.users.root;
			}
			const rootClient = clientFactory.getActionClient( null );
			await rootClient.login(
				cypressConfig.mediawikiAdminUsername,
				cypressConfig.mediawikiAdminPassword,
			);
			await rootClient.loadTokens( [ 'createaccount', 'userrights', 'csrf' ] );

			const rightsToken = await rootClient.token( 'userrights' );
			if ( rightsToken === '+\\' ) {
				throw new Error( 'Failed to get the root user tokens.' );
			}

			state.users.root = rootClient;
			return rootClient;
		}

		return {
			async 'MwApi:BlockUser'( { username, reason, expiry } ) {
				const rootClient = await root();
				const blockResult = await rootClient.action( 'block', {
					user: username,
					assert: 'user',
					reason: reason || 'Set up blocked user',
					expiry: expiry || 'never',
					token: await rootClient.token(),
				}, 'POST' );

				if ( !blockResult.block ) {
					return Promise.reject( new Error( 'Failed to block user.' ) );
				}

				return Promise.resolve( null );
			},
			async 'MwApi:CreateUser'( { usernamePrefix } ) {
				const rootUser = await root();
				const username = utils.title( usernamePrefix + '-' );
				const password = utils.uniq();
				await rootUser.createAccount( { username: username, password: password } );

				return Promise.resolve( { username, password } );
			},
		};
	},
};
