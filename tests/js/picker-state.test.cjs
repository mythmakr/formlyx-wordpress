const assert = require( 'assert' );
const fs = require( 'fs' );
const path = require( 'path' );
const test = require( 'node:test' );
const vm = require( 'vm' );

const source = fs
	.readFileSync( path.join( __dirname, '../../src/picker-state.js' ), 'utf8' )
	.replace( /\bexport\s+/g, '' );

const sandbox = { module: { exports: {} }, exports: {} };
vm.runInNewContext(
	`${ source }\nmodule.exports = { filterForms, isFormId, normalizeTheme, messageKind, embedNoticeStatus, embedShowsApproval, THEMES };`,
	sandbox
);
const {
	filterForms,
	isFormId,
	normalizeTheme,
	messageKind,
	embedNoticeStatus,
	embedShowsApproval,
	THEMES,
} = sandbox.module.exports;

test( 'theme values match the embed script', () => {
	assert.strictEqual(
		JSON.stringify( THEMES ),
		JSON.stringify( [ 'auto', 'light', 'dark', 'inherit', 'unstyled' ] )
	);
	assert.strictEqual( normalizeTheme( 'LIGHT' ), 'light' );
	assert.strictEqual( normalizeTheme( 'formlyx' ), 'auto' );
	assert.strictEqual( normalizeTheme( 'styled' ), 'auto' );
} );

test( 'form ids accept the embed character set', () => {
	assert.strictEqual( isFormId( 'clh0contact00000000000001' ), true );
	assert.strictEqual( isFormId( 'a' ), true );
	assert.strictEqual( isFormId( 'a'.repeat( 128 ) ), true );
	assert.strictEqual( isFormId( 'a'.repeat( 129 ) ), false );
	assert.strictEqual( isFormId( '' ), false );
	assert.strictEqual( isFormId( 'id with space' ), false );
	assert.strictEqual( isFormId( '"><script>' ), false );
} );

test( 'search filters the loaded list', () => {
	const forms = [
		{ id: 'clh0contact00000000000001', name: 'Contact us' },
		{ id: 'clh0other0000000000000001', name: 'Newsletter' },
	];
	assert.strictEqual( filterForms( forms, 'news' ).length, 1 );
	assert.strictEqual( filterForms( forms, 'clh0contact' ).length, 1 );
	assert.strictEqual( filterForms( forms, '' ).length, 2 );
} );

test( 'embed permission states choose the notice and the approval action', () => {
	assert.strictEqual( embedNoticeStatus( 'authorized' ), 'success' );
	assert.strictEqual( embedNoticeStatus( 'unavailable' ), 'error' );
	assert.strictEqual( embedNoticeStatus( 'approval_required' ), 'warning' );
	assert.strictEqual( embedNoticeStatus( 'local_site' ), 'warning' );
	assert.strictEqual( embedNoticeStatus( 'reconnect' ), 'warning' );
	assert.strictEqual( embedShowsApproval( 'approval_required' ), true );
	assert.strictEqual( embedShowsApproval( 'authorized' ), false );
	assert.strictEqual( embedShowsApproval( 'local_site' ), false );
	assert.strictEqual( embedShowsApproval( 'reconnect' ), false );
} );

test( 'error codes map to editor states', () => {
	assert.strictEqual( messageKind( 'formlyx_disconnected' ), 'disconnected' );
	assert.strictEqual( messageKind( 'formlyx_expired' ), 'expired' );
	assert.strictEqual( messageKind( 'formlyx_unreachable' ), 'error' );
	assert.strictEqual( messageKind( '' ), 'error' );
} );
