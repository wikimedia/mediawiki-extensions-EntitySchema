module.exports = {
	root: true,
	extends: [
		'wikimedia',
		'wikimedia/language/es2018',
		'wikimedia/jquery',
		'wikimedia/mediawiki',
	],
	rules: {
		'no-jquery/no-global-selector': 'off',
	},
	overrides: [
		{
			files: [
				'**/*.js',
			],
			rules: {
				'comma-dangle': [ 'error', 'always-multiline' ],
			},
		},
	],
};
