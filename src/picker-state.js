/**
 * Form picker rules shared with tests.
 */

export const THEMES = [
	'auto',
	'light',
	'dark',
	'inherit',
	'unstyled',
	'match',
];

/**
 * Whether a value can be stored as a Formlyx form id.
 *
 * @param {string} value Candidate id.
 * @return {boolean} True when the id uses the embed character set.
 */
export function isFormId( value ) {
	return /^[A-Za-z0-9_-]{1,128}$/.test( String( value || '' ) );
}

/**
 * Map a theme to a stored appearance value.
 *
 * @param {string} theme Requested theme.
 * @return {string} Theme attribute.
 */
export function normalizeTheme( theme ) {
	const value = String( theme || '' )
		.trim()
		.toLowerCase();
	if ( value === 'formlyx' ) {
		return 'auto';
	}
	return THEMES.indexOf( value ) === -1 ? 'auto' : value;
}

/**
 * Filter forms locally. Typing does not call Formlyx.
 *
 * @param {Array}  forms Form records already loaded for the editor.
 * @param {string} query Search text.
 * @return {Array} Matches.
 */
export function filterForms( forms, query ) {
	const needle = String( query || '' )
		.trim()
		.toLowerCase();
	if ( ! needle ) {
		return forms;
	}
	return forms.filter( ( form ) => {
		const name = String( form.name || '' ).toLowerCase();
		const id = String( form.id || '' ).toLowerCase();
		return name.indexOf( needle ) !== -1 || id.indexOf( needle ) !== -1;
	} );
}

/**
 * Classify a WordPress REST error for the block UI.
 *
 * @param {string} code Error code.
 * @return {'disconnected'|'expired'|'error'} UI state.
 */
export function messageKind( code ) {
	if ( code === 'formlyx_disconnected' ) {
		return 'disconnected';
	}
	if ( code === 'formlyx_expired' ) {
		return 'expired';
	}
	return 'error';
}

/**
 * Notice tone for an embed-permission state.
 *
 * @param {string} state Server state.
 * @return {'success'|'warning'|'error'} Notice status.
 */
export function embedNoticeStatus( state ) {
	if ( state === 'authorized' ) {
		return 'success';
	}
	if ( state === 'unavailable' ) {
		return 'error';
	}
	return 'warning';
}

/**
 * Whether the editor should offer the one-site approval action.
 *
 * @param {string} state Server state.
 * @return {boolean} True only when this site still needs approval.
 */
export function embedShowsApproval( state ) {
	return state === 'approval_required';
}
