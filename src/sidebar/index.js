/**
 * CiteWP — GEO Score sidebar for the Gutenberg block editor.
 *
 * Registers a plugin sidebar that fetches and displays the score
 * for the current post via the citewp/v1/score REST endpoint.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner, PanelBody } from '@wordpress/components';
import { chartLine } from '@wordpress/icons';

/**
 * Color tokens — kept in JS for now to avoid pulling in the full block editor styles.
 */
const GRADE_COLORS = {
	green:  '#16a34a',
	yellow: '#ca8a04',
	orange: '#ea580c',
	red:    '#dc2626',
};

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
			const data = await apiFetch( { path: `/citewp/v1/score/${ postId }` } );
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
				path: `/citewp/v1/score/${ postId }/recalculate`,
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
			<PluginSidebarMoreMenuItem target="citewp-geo-score" icon={ chartLine }>
				CiteWP GEO Score
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="citewp-geo-score"
				title="CiteWP GEO Score"
				icon={ chartLine }
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
							<div style={ { marginTop: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
								<Button
									variant="secondary"
									onClick={ recalculate }
									disabled={ loading }
									isBusy={ loading }
								>
									Recalculate
								</Button>
								<small style={ { color: '#6b7280' } }>
									Saves trigger auto-recalc
								</small>
							</div>
						</>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

function TotalScore( { score } ) {
	const color = GRADE_COLORS[ score.grade ] || GRADE_COLORS.red;
	return (
		<div style={ {
			textAlign: 'center',
			padding: '16px 0 20px',
			borderBottom: '1px solid #e5e7eb',
			marginBottom: 16,
		} }>
			<div style={ {
				fontSize: 48,
				fontWeight: 700,
				color,
				lineHeight: 1,
				fontVariantNumeric: 'tabular-nums',
			} }>
				{ score.total }
				<span style={ { fontSize: 20, color: '#9ca3af', fontWeight: 400 } }>/100</span>
			</div>
			<div style={ {
				marginTop: 4,
				color: '#6b7280',
				fontSize: 13,
				textTransform: 'uppercase',
				letterSpacing: '0.05em',
			} }>
				GEO Score
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
	const color = pct >= 80 ? GRADE_COLORS.green
		: pct >= 60 ? GRADE_COLORS.yellow
		: pct >= 40 ? GRADE_COLORS.orange
		: GRADE_COLORS.red;

	return (
		<div style={ { marginBottom: 10 } }>
			<button
				onClick={ onToggle }
				style={ {
					width: '100%',
					background: 'transparent',
					border: 'none',
					padding: '8px 0',
					cursor: 'pointer',
					textAlign: 'left',
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'space-between',
				} }
			>
				<span style={ { fontWeight: 600, fontSize: 14 } }>
					{ isOpen ? '▾' : '▸' } { category.label }
				</span>
				<span style={ { fontVariantNumeric: 'tabular-nums', color: '#374151' } }>
					{ category.score }/{ category.max }
				</span>
			</button>

			<div style={ {
				height: 4,
				background: '#e5e7eb',
				borderRadius: 2,
				overflow: 'hidden',
				marginBottom: 4,
			} }>
				<div style={ {
					height: '100%',
					width: `${ pct }%`,
					background: color,
					transition: 'width 0.3s ease',
				} } />
			</div>

			{ isOpen && (
				<div style={ { paddingLeft: 8, marginTop: 8 } }>
					{ signals.map( ( s ) => <SignalRow key={ s.id } signal={ s } /> ) }
				</div>
			) }
		</div>
	);
}

function SignalRow( { signal } ) {
	const color = signal.status === 'pass' ? GRADE_COLORS.green
		: signal.status === 'partial' ? GRADE_COLORS.yellow
		: GRADE_COLORS.red;
	return (
		<div style={ {
			padding: '8px 0',
			borderTop: '1px solid #f3f4f6',
			fontSize: 13,
		} }>
			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
				<span>
					<span style={ { color, fontWeight: 700, marginRight: 6 } }>
						{ STATUS_ICONS[ signal.status ] || '?' }
					</span>
					{ signal.label }
				</span>
				<span style={ { color: '#6b7280', fontVariantNumeric: 'tabular-nums' } }>
					{ signal.score }/{ signal.max }
				</span>
			</div>
			<div style={ { color: '#4b5563', marginTop: 2 } }>{ signal.message }</div>
			{ signal.recommendation && (
				<div style={ {
					marginTop: 4,
					padding: 6,
					background: '#f9fafb',
					borderLeft: `2px solid ${ color }`,
					color: '#374151',
					fontSize: 12,
				} }>
					💡 { signal.recommendation }
				</div>
			) }
		</div>
	);
}

registerPlugin( 'citewp-geo-score', {
	render: ScoreSidebar,
	icon: chartLine,
} );
