import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const { collectionId, limit } = attributes;
		const [ collections, setCollections ] = useState( null );

		useEffect( () => {
			apiFetch( { path: '/booqable-core/v1/collections' } )
				.then( ( result ) => setCollections( result ) )
				.catch( () => setCollections( [] ) );
		}, [] );

		const options = [ { label: __( 'All collections', 'booqable-rental-core' ), value: '' } ].concat(
			( collections || [] ).map( ( c ) => ( { label: c.name, value: c.id } ) )
		);

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Catalog Settings', 'booqable-rental-core' ) }>
						{ collections === null ? (
							<Spinner />
						) : (
							<SelectControl
								label={ __( 'Collection', 'booqable-rental-core' ) }
								value={ collectionId }
								options={ options }
								onChange={ ( value ) => setAttributes( { collectionId: value } ) }
							/>
						) }
						<RangeControl
							label={ __( 'Number of products', 'booqable-rental-core' ) }
							value={ limit }
							onChange={ ( value ) => setAttributes( { limit: value } ) }
							min={ 1 }
							max={ 48 }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save() {
		return null;
	},
} );
