/**
 * AI Search Optimizer — GEO Score sidebar for the Gutenberg block editor.
 *
 * Registers a plugin sidebar that fetches and displays the score
 * for the current post via the citewp/aiso/v1/score REST endpoint.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner, PanelBody } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { createBlock } from '@wordpress/blocks';

function CiteWPIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 512 512"
			width="24"
			height="24"
			aria-hidden="true"
			focusable="false"
		>
			<rect width="512" height="512" fill="#E8D400" />
			<text
				x="256"
				y="318"
				fontFamily="system-ui, -apple-system, sans-serif"
				fontWeight="800"
				fontSize="248"
				fill="#0C0C0D"
				textAnchor="middle"
			>
				[A]
			</text>
		</svg>
	);
}

const STATUS_ICONS = {
	pass:    '✓',
	partial: '~',
	fail:    '✗',
};

function ScoreSidebar() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const isSavingPost = useSelect( ( select ) => select( 'core/editor' ).isSavingPost(), [] );
	const isAutosavingPost = useSelect( ( select ) => select( 'core/editor' ).isAutosavingPost(), [] );

	const [ score, setScore ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ expandedCategory, setExpandedCategory ] = useState( null );

	const fetchScore = useCallback( async () => {
		if ( ! postId ) return;
		setLoading( true );
		setError( null );
		try {
			const data = await apiFetch( { path: `/citewp/aiso/v1/score/${ postId }` } );
			setScore( data );
		} catch ( e ) {
			setError( e.message || 'Failed to load score' );
		} finally {
			setLoading( false );
		}
	}, [ postId ] );

	const recalculate = useCallback( async () => {
		if ( ! postId ) return;
		setLoading( true );
		setError( null );
		try {
			const data = await apiFetch( {
				path: `/citewp/aiso/v1/score/${ postId }/recalculate`,
				method: 'POST',
			} );
			setScore( data );
		} catch ( e ) {
			setError( e.message || 'Failed to recalculate score' );
		} finally {
			setLoading( false );
		}
	}, [ postId ] );

	// Initial load.
	useEffect( () => { fetchScore(); }, [ fetchScore ] );

	// Re-fetch after save completes (server recalculates on save_post).
	const [ wasSaving, setWasSaving ] = useState( false );
	useEffect( () => {
		if ( isSavingPost && ! isAutosavingPost ) {
			setWasSaving( true );
		} else if ( wasSaving && ! isSavingPost ) {
			setWasSaving( false );
			fetchScore();
		}
	}, [ isSavingPost, isAutosavingPost, wasSaving, fetchScore ] );

	return (
		<>
			<PluginSidebarMoreMenuItem target="citewp-aiso-geo-score" icon={ <CiteWPIcon /> }>
				Cite Score
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="citewp-aiso-geo-score"
				title="Cite Score"
				icon={ <CiteWPIcon /> }
			>
				<PanelBody>
					{ loading && ! score && (
						<div style={ { textAlign: 'center', padding: '20px 0' } }>
							<Spinner />
						</div>
					) }

					{ error && (
						<div style={ { color: '#dc2626', padding: '8px 0' } }>
							{ error }
						</div>
					) }

					{ score && (
						<>
							<TotalScore score={ score } />
							<Categories
								score={ score }
								expanded={ expandedCategory }
								onToggle={ setExpandedCategory }
							/>
							<div style={ { marginTop: 16 } }>
								<Button
									variant="secondary"
									onClick={ recalculate }
									disabled={ loading }
									isBusy={ loading }
								>
									Recalculate
								</Button>
								<p style={ { margin: '6px 0 0', fontSize: 12, color: '#6b7280' } }>
									Saves trigger auto-recalculation.
								</p>
							</div>
						</>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

function TotalScore( { score } ) {
	return (
		<div className="citewp-aiso-sidebar-score">
			<div className={ `citewp-aiso-sidebar-score__value citewp-aiso-sidebar-score__value--${ score.grade }` }>
				{ score.total }
				<span className="citewp-aiso-sidebar-score__denom">/100</span>
			</div>
			<div className="citewp-aiso-sidebar-score__label">
				Cite Score
			</div>
		</div>
	);
}

function Categories( { score, expanded, onToggle } ) {
	return (
		<div>
			{ Object.entries( score.categories ).map( ( [ key, cat ] ) => (
				<CategoryRow
					key={ key }
					id={ key }
					category={ cat }
					signals={ score.signals.filter( ( s ) => s.category === key ) }
					isOpen={ expanded === key }
					onToggle={ () => onToggle( expanded === key ? null : key ) }
				/>
			) ) }
		</div>
	);
}

function CategoryRow( { id, category, signals, isOpen, onToggle } ) {
	const pct = category.max > 0 ? ( category.score / category.max ) * 100 : 0;
	// Category bars use the top-line score thresholds (80/60/40) by visual convention;
	// per-category thresholds are not formally defined in SCORING-RUBRIC.md.
	const grade = pct >= 80 ? 'green' : pct >= 60 ? 'yellow' : pct >= 40 ? 'orange' : 'red';

	return (
		<div className="citewp-aiso-sidebar-category">
			<button
				onClick={ onToggle }
				className="citewp-aiso-sidebar-category__toggle"
			>
				<span className="citewp-aiso-sidebar-category__label">
					{ isOpen ? '▾' : '▸' } { category.label }
				</span>
				<span className="citewp-aiso-sidebar-category__score">
					{ category.score }/{ category.max }
				</span>
			</button>

			<div className="citewp-aiso-sidebar-category__bar">
				<div
					className={ `citewp-aiso-sidebar-category__fill citewp-aiso-sidebar-category__fill--${ grade }` }
					style={ { width: `${ pct }%` } }
				/>
			</div>

			{ isOpen && (
				<div className="citewp-aiso-sidebar-category__signals">
					{ signals.map( ( s ) => <SignalRow key={ s.id } signal={ s } /> ) }
				</div>
			) }
		</div>
	);
}

function SignalRow( { signal } ) {
	return (
		<div className="citewp-aiso-sidebar-signal">
			<div className="citewp-aiso-sidebar-signal__header">
				<span className="citewp-aiso-sidebar-signal__label">
					<span className={ `citewp-aiso-sidebar-signal__icon citewp-aiso-sidebar-signal__icon--${ signal.status }` }>
						{ STATUS_ICONS[ signal.status ] || '?' }
					</span>
					{ signal.label }
				</span>
				<span className="citewp-aiso-sidebar-signal__score">
					{ signal.score }/{ signal.max }
				</span>
			</div>
			<div className="citewp-aiso-sidebar-signal__message">{ signal.message }</div>
			{ signal.recommendation && (
				<div className={ `citewp-aiso-sidebar-signal__rec citewp-aiso-sidebar-signal__rec--${ signal.status }` }>
					💡 { signal.recommendation }
				</div>
			) }
		</div>
	);
}

registerPlugin( 'citewp-aiso-geo-score', {
	render: ScoreSidebar,
	icon: CiteWPIcon,
} );

// === Schema Suggestions — Document Settings panel ===

function SchemaSuggestions() {
	const postId       = useSelect( ( s ) => s( 'core/editor' ).getCurrentPostId(), [] );
	const isSavingPost = useSelect( ( s ) => s( 'core/editor' ).isSavingPost(), [] );
	const isAutosaving = useSelect( ( s ) => s( 'core/editor' ).isAutosavingPost(), [] );
	const { insertBlock } = useDispatch( 'core/block-editor' );

	const [ schema,    setSchema    ] = useState( null );
	const [ loading,   setLoading   ] = useState( false );
	const [ error,     setError     ] = useState( null );
	const [ inserted,  setInserted  ] = useState( {} );
	const [ inserting, setInserting ] = useState( {} );

	const fetchSchema = useCallback( async () => {
		if ( ! postId ) return;
		setInserted( {} );
		setLoading( true );
		setError( null );
		try {
			const data = await apiFetch( { path: `/citewp/aiso/v1/schema/${ postId }` } );
			setSchema( data );
		} catch ( e ) {
			setError( e.message || 'Failed to load schema suggestions' );
		} finally {
			setLoading( false );
		}
	}, [ postId ] );

	useEffect( () => { fetchSchema(); }, [ fetchSchema ] );

	// Re-fetch after post save so "already detected" badges reflect saved state.
	const [ wasSaving, setWasSaving ] = useState( false );
	useEffect( () => {
		if ( isSavingPost && ! isAutosaving ) {
			setWasSaving( true );
		} else if ( wasSaving && ! isSavingPost ) {
			setWasSaving( false );
			fetchSchema();
		}
	}, [ isSavingPost, isAutosaving, wasSaving, fetchSchema ] );

	const insertSchemaBlock = useCallback( ( schemaKey ) => {
		if ( ! schema || ! schema[ schemaKey ] ) return;
		setInserting( ( prev ) => ( { ...prev, [ schemaKey ]: true } ) );

		const json    = JSON.stringify( schema[ schemaKey ], null, 2 );
		const content = `<script type="application/ld+json">\n${ json }\n</script>`;
		const block   = createBlock( 'core/html', { content } );
		// Append to end — omitting the index is the safe pattern since getBlockCount()
		// returns root-level blocks only and would misplace the block inside nested blocks.
		insertBlock( block );

		setInserting( ( prev ) => ( { ...prev, [ schemaKey ]: false } ) );
		setInserted( ( prev ) => ( { ...prev, [ schemaKey ]: true } ) );
	}, [ schema, insertBlock ] );

	if ( ! postId ) return null;

	if ( loading && ! schema ) {
		return (
			<div style={ { textAlign: 'center', padding: '12px 0' } }>
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return <div style={ { color: '#dc2626', fontSize: 13 } }>{ error }</div>;
	}

	if ( ! schema ) return null;

	const detected       = schema.detected || [];
	const articleDetected = detected.some( ( t ) => ARTICLE_VARIANTS.includes( t ) );
	const faqDetected     = detected.includes( 'FAQPage' );
	const otherDetected   = detected.filter(
		( t ) => ! [ ...ARTICLE_VARIANTS, 'FAQPage', 'Question' ].includes( t )
	);

	return (
		<div style={ { fontSize: 13 } }>
			<SchemaTypeRow
				label="Article"
				detected={ articleDetected }
				generated={ !! schema.article }
				inserted={ !! inserted.article }
				inserting={ !! inserting.article }
				onInsert={ () => insertSchemaBlock( 'article' ) }
			/>
			<SchemaTypeRow
				label="FAQPage"
				detected={ faqDetected }
				generated={ !! schema.faqpage }
				inserted={ !! inserted.faqpage }
				inserting={ !! inserting.faqpage }
				onInsert={ () => insertSchemaBlock( 'faqpage' ) }
				emptyMessage="No FAQ content detected (need ≥ 2 Q&A pairs)"
			/>
			{ otherDetected.map( ( type ) => (
				<div key={ type } style={ {
					padding: '6px 0',
					borderTop: '1px solid #f3f4f6',
					color: '#6b7280',
				} }>
					{ type } schema detected — more types coming soon
				</div>
			) ) }
		</div>
	);
}

function SchemaTypeRow( { label, detected, generated, inserted, inserting, onInsert, emptyMessage } ) {
	if ( ! generated && ! detected ) {
		return emptyMessage ? (
			<div style={ {
				padding: '6px 0',
				borderTop: '1px solid #f3f4f6',
				color: '#9ca3af',
				fontSize: 12,
			} }>
				{ emptyMessage }
			</div>
		) : null;
	}

	let action;
	if ( detected || inserted ) {
		const label2 = ( inserted && ! detected ) ? '✓ Added' : 'Already detected';
		action = (
			<span style={ {
				background: '#f0fdf4',
				color: '#16a34a',
				fontSize: 11,
				padding: '2px 8px',
				borderRadius: 9999,
				fontWeight: 600,
			} }>
				{ label2 }
			</span>
		);
	} else {
		action = (
			<Button
				variant="secondary"
				size="small"
				onClick={ onInsert }
				isBusy={ inserting }
				disabled={ inserting }
			>
				Insert
			</Button>
		);
	}

	return (
		<div style={ {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'space-between',
			padding: '8px 0',
			borderTop: '1px solid #f3f4f6',
		} }>
			<span style={ { fontWeight: 600 } }>{ label }</span>
			{ action }
		</div>
	);
}

registerPlugin( 'citewp-aiso-schema-suggestions', {
	render: () => (
		<PluginDocumentSettingPanel
			name="citewp-aiso-schema"
			title="Schema Suggestions"
			className="citewp-aiso-schema-panel"
		>
			<SchemaSuggestions />
		</PluginDocumentSettingPanel>
	),
} );
