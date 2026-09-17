import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const { limit, linkBase } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Category Grid Settings', 'booqable-rental-core' ) }>
						<RangeControl
							label={ __( 'Number of categories', 'booqable-rental-core' ) }
							value={ limit }
							onChange={ ( value ) => setAttributes( { limit: value } ) }
							min={ 1 }
							max={ 12 }
						/>
						<TextControl
							label={ __( 'Inventory page link base', 'booqable-rental-core' ) }
							help={ __( 'Category cards link to this page with a ?collection= query arg.', 'booqable-rental-core' ) }
							value={ linkBase }
							onChange={ ( value ) => setAttributes( { linkBase: value } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save() {
		// Fully dynamic — PHP (render.php) owns both editor preview
		// (via ServerSideRender above) and the front end.
		return null;
	},
} );
