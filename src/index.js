/**
 * Register the Formlyx Form block in the editor.
 *
 * The published page is rendered in PHP. This file does not print the embed.
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './editor.css';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
