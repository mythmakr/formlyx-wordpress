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
	embedNoticeStatus,
	embedShowsApproval,
	filterForms,
	isFormId,
	messageKind,
	normalizeTheme,
} from './picker-state';

const THEME_OPTIONS = [
	{ label: __( 'Auto', 'formlyx' ), value: 'auto' },
	{ label: __( 'Light', 'formlyx' ), value: 'light' },
	{ label: __( 'Dark', 'formlyx' ), value: 'dark' },
	{ label: __( 'Inherit', 'formlyx' ), value: 'inherit' },
	{ label: __( 'Unstyled', 'formlyx' ), value: 'unstyled' },
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
									'formlyx'
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
						'formlyx'
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
									'formlyx'
								),
								authorized: false,
							}
				);
			} )
			.catch( () => {
				setEmbed( {
					state: 'unavailable',
					message: __(
						'Formlyx could not allow this site.',
						'formlyx'
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
				<PanelBody title={ __( 'Formlyx form', 'formlyx' ) }>
					<SelectControl
						label={ __( 'Theme', 'formlyx' ) }
						value={ normalizeTheme( theme ) }
						options={ THEME_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { theme: normalizeTheme( value ) } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ status === 'loading' && <Spinner /> }
			{ status === 'disconnected' && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Connect Formlyx to select a form.', 'formlyx' ) }{ ' ' }
					{ settingsUrl() && (
						<a href={ settingsUrl() }>
							{ __( 'Settings → Formlyx', 'formlyx' ) }
						</a>
					) }
				</Notice>
			) }
			{ status === 'expired' && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The Formlyx connection has expired. Reconnect from Settings → Formlyx.',
						'formlyx'
					) }{ ' ' }
					{ settingsUrl() && (
						<a href={ settingsUrl() }>
							{ __( 'Settings → Formlyx', 'formlyx' ) }
						</a>
					) }
				</Notice>
			) }
			{ status === 'error' && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'The form list could not be loaded.', 'formlyx' ) }
				</Notice>
			) }
			{ status === 'ready' && forms.length === 0 && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'No published forms are available for embedding.',
						'formlyx'
					) }
				</Notice>
			) }
			{ status === 'ready' && forms.length > 0 && (
				<ComboboxControl
					label={ __( 'Published form', 'formlyx' ) }
					value={ selected ? formId : '' }
					options={ options }
					onChange={ ( value ) =>
						setAttributes( { formId: value || '' } )
					}
					onFilterValueChange={ setQuery }
					help={ __(
						'Search uses the forms already loaded.',
						'formlyx'
					) }
				/>
			) }
			{ status !== 'loading' && (
				<TextControl
					label={ __( 'Form ID', 'formlyx' ) }
					value={ formId }
					onChange={ ( value ) => setAttributes( { formId: value } ) }
					help={ __(
						'Enter a Formlyx form ID when it is not in the list. A public form does not require a connection.',
						'formlyx'
					) }
				/>
			) }
			{ formId !== '' && ! validId && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Use the form ID from Formlyx. It can contain letters, numbers, hyphens, and underscores.',
						'formlyx'
					) }
				</Notice>
			) }
			{ status !== 'loading' && (
				<p>
					<Button
						variant="secondary"
						onClick={ () => loadForms( true ) }
					>
						{ __( 'Refresh forms', 'formlyx' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ formId === '' }
						onClick={ () => setAttributes( { formId: '' } ) }
					>
						{ __( 'Clear selection', 'formlyx' ) }
					</Button>
				</p>
			) }
			{ validId && embed && embed.state === 'loading' && (
				<p className="formlyx-embed-auth">
					<Spinner />
					{ __(
						'Checking whether this site can embed the form.',
						'formlyx'
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
					{ embedShowsApproval( embed.state ) && (
						<p>
							<Button
								variant="secondary"
								isBusy={ embedBusy }
								disabled={ embedBusy }
								onClick={ approveEmbed }
							>
								{ __(
									'Allow embedding on this site',
									'formlyx'
								) }
							</Button>
						</p>
					) }
					{ ( embed.state === 'reconnect' ||
						embed.state === 'mismatch' ) &&
						settingsUrl() && (
							<p>
								<a href={ settingsUrl() }>
									{ __( 'Settings → Formlyx', 'formlyx' ) }
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
							: __( 'Formlyx form', 'formlyx' ) }
					</strong>
					{ selected && selected.description ? (
						<p>{ selected.description }</p>
					) : null }
					<p>{ formId }</p>
					<p>
						{ __(
							'The live form appears on the published page. This editor shows a placeholder because the Formlyx embed script does not provide a way to start again inside the editor.',
							'formlyx'
						) }
					</p>
					{ previewLink ? (
						<p>
							<a href={ previewLink }>
								{ __( 'Preview page', 'formlyx' ) }
							</a>
						</p>
					) : null }
				</div>
			) }
		</div>
	);
}
