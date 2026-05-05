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
						<div className="citewp-aiso-sidebar-loading">
							<Spinner />
						</div>
					) }

					{ error && (
						<div className="citewp-aiso-sidebar-error">
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
							<div className="citewp-aiso-sidebar-recalc">
								<Button
									variant="secondary"
									onClick={ recalculate }
									disabled={ loading }
									isBusy={ loading }
								>
									Recalculate
								</Button>
								<p className="citewp-aiso-sidebar-recalc-hint">
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

const SCHEMA_TYPES = [
	{
		key: 'article',
		label: 'Article',
		variants: [ 'Article', 'NewsArticle', 'BlogPosting' ],
		emptyMessage: null,
	},
	{
		key: 'faqpage',
		label: 'FAQPage',
		variants: [ 'FAQPage' ],
		emptyMessage: 'No FAQ content detected (need ≥ 2 Q&A pairs)',
	},
];

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
			<div className="citewp-aiso-sidebar-loading">
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return <div className="citewp-aiso-sidebar-error">{ error }</div>;
	}

	if ( ! schema ) return null;

	const detected         = schema.detected || [];
	const allKnownVariants = SCHEMA_TYPES.flatMap( ( t ) => t.variants );
	// 'Question' is a child node type of FAQPage schema (in mainEntity), not a
	// standalone @type from the generator — excluded to avoid double-counting alongside FAQPage.
	// A manually inserted root-level @type="Question" block would be detected, but
	// the generator does not produce this (confirmed: collect_root_types() does not
	// recurse into mainEntity, only @graph).
	const otherDetected = detected.filter(
		( t ) => ! allKnownVariants.includes( t ) && t !== 'Question'
	);

	return (
		<div className="citewp-aiso-sidebar-schema">
			{ SCHEMA_TYPES.map( ( type ) => {
				const isDetected = detected.some( ( t ) => type.variants.includes( t ) );
				return (
					<SchemaTypeRow
						key={ type.key }
						label={ type.label }
						detected={ isDetected }
						generated={ !! schema[ type.key ] }
						inserted={ !! inserted[ type.key ] }
						inserting={ !! inserting[ type.key ] }
						onInsert={ () => insertSchemaBlock( type.key ) }
						emptyMessage={ type.emptyMessage }
					/>
				);
			} ) }
			{ otherDetected.length > 0 && (
				<div className="citewp-aiso-sidebar-schema-other">
					{ `Other detected types: ${ otherDetected.join( ', ' ) } — more types coming soon` }
				</div>
			) }
		</div>
	);
}

function SchemaTypeRow( { label, detected, generated, inserted, inserting, onInsert, emptyMessage } ) {
	if ( ! generated && ! detected ) {
		return emptyMessage ? (
			<div className="citewp-aiso-sidebar-schema-row__empty">
				{ emptyMessage }
			</div>
		) : null;
	}

	let action;
	if ( detected || inserted ) {
		const statusLabel = ( inserted && ! detected ) ? '✓ Added' : 'Already detected';
		action = (
			<span className="citewp-aiso-sidebar-schema-row__pill">
				{ statusLabel }
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
		<div className="citewp-aiso-sidebar-schema-row">
			<span className="citewp-aiso-sidebar-schema-row__label">{ label }</span>
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
