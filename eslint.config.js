const wordpress = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wordpress,
	{
		files: [ 'src/**/*.js' ],
		languageOptions: {
			globals: {
				formlyxEditor: 'readonly',
			},
		},
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
			'import/no-extraneous-dependencies': 'off',
			'import/named': 'off',
			'import/default': 'off',
		},
	},
];
