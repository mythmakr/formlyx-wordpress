/**
 * Formlyx Form block editor.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState } from '@wordpress/element';
import {
	embedNeedsAdministrator,
	embedNoticeStatus,
	embedShowsApproval,
	filterForms,
	isFormId,
	messageKind,
	normalizeTheme,
} from './picker-state';

const THEME_OPTIONS = [
	{ label: __( 'Auto', 'formlyx-forms' ), value: 'auto' },
	{ label: __( 'Light', 'formlyx-forms' ), value: 'light' },
	{ label: __( 'Dark', 'formlyx-forms' ), value: 'dark' },
	{ label: __( 'Inherit', 'formlyx-forms' ), value: 'inherit' },
	{ label: __( 'Unstyled', 'formlyx-forms' ), value: 'unstyled' },
	{ label: __( 'Match WordPress Theme', 'formlyx-forms' ), value: 'match' },
];

/**
 * Settings URL printed before the editor script.
 *
 * @return {string} Admin URL.
 */
function settingsUrl() {
	if ( typeof formlyxEditor !== 'undefined' && formlyxEditor.settingsUrl ) {
		return formlyxEditor.settingsUrl;
	}
	return '';
}

/**
 * Whether the current user may approve this site as an embed origin.
 *
 * @return {boolean} True for administrators with manage_options.
 */
function canApproveEmbed() {
	if ( typeof formlyxEditor !== 'undefined' ) {
		return !! formlyxEditor.canApproveEmbed;
	}
	return false;
}

/**
 * Block edit component.
 *
 * @param {Object}                   props               Block props.
 * @param {Object}                   props.attributes    Saved attributes.
 * @param {( next: Object ) => void} props.setAttributes Attribute setter.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { formId, theme } = attributes;
	const [ forms, setForms ] = useState( [] );
	const [ status, setStatus ] = useState( 'loading' );
	const [ query, setQuery ] = useState( '' );
	const blockProps = useBlockProps();
	const previewLink = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		if ( ! editor || ! editor.getEditedPostPreviewLink ) {
			return '';
		}
		return editor.getEditedPostPreviewLink() || '';
	}, [] );
	const selected = forms.find( ( form ) => form.id === formId );
	const options = filterForms( forms, query ).map( ( form ) => ( {
		value: form.id,
		label: form.name,
	} ) );
	const validId = isFormId( formId );
	const [ embed, setEmbed ] = useState( null );
	const [ embedBusy, setEmbedBusy ] = useState( false );
	const loadForms = useCallback( ( refresh ) => {
		setStatus( 'loading' );
		const path = refresh
			? '/formlyx/v1/forms?refresh=1'
			: '/formlyx/v1/forms';
		apiFetch( { path } )
			.then( ( data ) => {
				setForms(
					data && Array.isArray( data.forms ) ? data.forms : []
				);
				setStatus( 'ready' );
			} )
			.catch( ( error ) => {
				const code = error && error.code ? error.code : '';
				setStatus( messageKind( code ) );
			} );
	}, [] );

	useEffect( () => {
		loadForms( false );
	}, [ loadForms ] );

	const loadEmbed = useCallback( ( id ) => {
		if ( ! isFormId( id ) ) {
			setEmbed( null );
			return;
		}
		setEmbed( { state: 'loading', message: '', authorized: false } );
		apiFetch( { path: `/formlyx/v1/forms/${ id }/embed-site` } )
			.then( ( data ) => {
				setEmbed(
					data && data.state
						? data
						: {
								state: 'unavailable',
								message: __(
									'Formlyx could not check embed permission for this site.',
									'formlyx-forms'
								),
								authorized: false,
							}
				);
			} )
			.catch( () => {
				setEmbed( {
					state: 'unavailable',
					message: __(
						'Formlyx could not check embed permission for this site.',
						'formlyx-forms'
					),
					authorized: false,
				} );
			} );
	}, [] );

	useEffect( () => {
		loadEmbed( formId );
	}, [ formId, loadEmbed ] );

	const approveEmbed = () => {
		if ( ! validId || embedBusy ) {
			return;
		}
		setEmbedBusy( true );
		apiFetch( {
			path: `/formlyx/v1/forms/${ formId }/embed-site`,
			method: 'POST',
		} )
			.then( ( data ) => {
				setEmbed(
					data && data.state
						? data
						: {
								state: 'unavailable',
								message: __(
									'Formlyx could not allow this site.',
									'formlyx-forms'
								),
								authorized: false,
							}
				);
			} )
			.catch( ( error ) => {
				const httpStatus =
					error && error.data && error.data.status
						? error.data.status
						: 0;
				const forbidden =
					( error && error.code === 'rest_forbidden' ) ||
					httpStatus === 403;
				setEmbed( {
					state: 'unavailable',
					message: forbidden
						? __(
								'An administrator must allow embedding on this site.',
								'formlyx-forms'
							)
						: __(
								'Formlyx could not allow this site.',
								'formlyx-forms'
							),
					authorized: false,
				} );
			} )
			.finally( () => {
				setEmbedBusy( false );
			} );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Formlyx form', 'formlyx-forms' ) }>
					<SelectControl
						label={ __( 'Appearance', 'formlyx-forms' ) }
						value={ normalizeTheme( theme ) }
						options={ THEME_OPTIONS }
						help={
							normalizeTheme( theme ) === 'match'
								? __(
										"Uses this site's theme on the hosted form. The appearance saved in Formlyx stays unchanged.",
										'formlyx-forms'
									)
								: undefined
						}
						onChange={ ( value ) =>
							setAttributes( { theme: normalizeTheme( value ) } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ status === 'loading' && <Spinner /> }
			{ status === 'disconnected' && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Connect Formlyx to select a form.',
						'formlyx-forms'
					) }{ ' ' }
					{ settingsUrl() && (
						<a href={ settingsUrl() }>
							{ __( 'Settings → Formlyx', 'formlyx-forms' ) }
						</a>
					) }
				</Notice>
			) }
			{ status === 'expired' && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The Formlyx connection has expired. Reconnect from Settings → Formlyx.',
						'formlyx-forms'
					) }{ ' ' }
					{ settingsUrl() && (
						<a href={ settingsUrl() }>
							{ __( 'Settings → Formlyx', 'formlyx-forms' ) }
						</a>
					) }
				</Notice>
			) }
			{ status === 'error' && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'The form list could not be loaded.',
						'formlyx-forms'
					) }
				</Notice>
			) }
			{ status === 'ready' && forms.length === 0 && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'No published forms are available for embedding.',
						'formlyx-forms'
					) }
				</Notice>
			) }
			{ status === 'ready' && forms.length > 0 && (
				<ComboboxControl
					label={ __( 'Published form', 'formlyx-forms' ) }
					value={ selected ? formId : '' }
					options={ options }
					onChange={ ( value ) =>
						setAttributes( { formId: value || '' } )
					}
					onFilterValueChange={ setQuery }
					help={ __(
						'Search uses the forms already loaded.',
						'formlyx-forms'
					) }
				/>
			) }
			{ status !== 'loading' && (
				<TextControl
					label={ __( 'Form ID', 'formlyx-forms' ) }
					value={ formId }
					onChange={ ( value ) => setAttributes( { formId: value } ) }
					help={ __(
						'Enter a Formlyx form ID when it is not in the list. A public form does not require a connection.',
						'formlyx-forms'
					) }
				/>
			) }
			{ formId !== '' && ! validId && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Use the form ID from Formlyx. It can contain letters, numbers, hyphens, and underscores.',
						'formlyx-forms'
					) }
				</Notice>
			) }
			{ status !== 'loading' && (
				<p>
					<Button
						variant="secondary"
						onClick={ () => loadForms( true ) }
					>
						{ __( 'Refresh forms', 'formlyx-forms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ formId === '' }
						onClick={ () => setAttributes( { formId: '' } ) }
					>
						{ __( 'Clear selection', 'formlyx-forms' ) }
					</Button>
				</p>
			) }
			{ validId && embed && embed.state === 'loading' && (
				<p className="formlyx-embed-auth">
					<Spinner />
					{ __(
						'Checking whether this site can embed the form.',
						'formlyx-forms'
					) }
				</p>
			) }
			{ validId && embed && embed.state !== 'loading' && (
				<div className="formlyx-embed-auth">
					<Notice
						status={ embedNoticeStatus( embed.state ) }
						isDismissible={ false }
					>
						{ embed.message }
					</Notice>
					{ embedShowsApproval( embed.state, canApproveEmbed() ) && (
						<p>
							<Button
								variant="secondary"
								isBusy={ embedBusy }
								disabled={ embedBusy }
								onClick={ approveEmbed }
							>
								{ __(
									'Allow embedding on this site',
									'formlyx-forms'
								) }
							</Button>
						</p>
					) }
					{ embedNeedsAdministrator(
						embed.state,
						canApproveEmbed()
					) && (
						<p>
							{ __(
								'An administrator must allow embedding on this site.',
								'formlyx-forms'
							) }
						</p>
					) }
					{ ( embed.state === 'reconnect' ||
						embed.state === 'mismatch' ) &&
						settingsUrl() && (
							<p>
								<a href={ settingsUrl() }>
									{ __(
										'Settings → Formlyx',
										'formlyx-forms'
									) }
								</a>
							</p>
						) }
				</div>
			) }
			{ validId && (
				<div className="formlyx-block-preview">
					<strong>
						{ selected
							? selected.name
							: __( 'Formlyx form', 'formlyx-forms' ) }
					</strong>
					{ selected && selected.description ? (
						<p>{ selected.description }</p>
					) : null }
					<p>{ formId }</p>
					<p>
						{ __(
							'The live form appears on the published page. This editor shows a placeholder because the Formlyx embed script does not provide a way to start again inside the editor.',
							'formlyx-forms'
						) }
					</p>
					{ previewLink ? (
						<p>
							<a href={ previewLink }>
								{ __( 'Preview page', 'formlyx-forms' ) }
							</a>
						</p>
					) : null }
				</div>
			) }
		</div>
	);
}
