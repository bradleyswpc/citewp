<?php
/**
 * Admin menu registration.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Admin\DashboardData;
use CiteWP\Aiso\Admin\IconLibrary;
use CiteWP\Aiso\Scoring\Repository;
use CiteWP\Aiso\Admin\RecommendationMapper;
use CiteWP\Aiso\Scoring\ScoreHistory;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG_PARENT = 'citewp';
	/** @deprecated No longer a WP submenu slug. Retained for back-compat. */
	public const SLUG_LOGS   = 'citewp-aiso-crawler-logs';

	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'CiteWP', 'citewp-ai-search-optimizer' ),
			__( 'CiteWP', 'citewp-ai-search-optimizer' ),
			'manage_options',
			self::SLUG_PARENT,
			[ $this, 'render_page' ],
			'dashicons-chart-line',
			81
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_citewp_screen = ( $hook === 'toplevel_page_' . self::SLUG_PARENT );

		if ( ! $is_citewp_screen ) {
			return;
		}

		wp_enqueue_style(
			'citewp-aiso-admin',
			CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-admin.css',
			[],
			CITEWP_AISO_VERSION
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$logs_module     = \CiteWP\Aiso\Plugin::instance()->module( 'admin_logs_page' );
		$settings_module = \CiteWP\Aiso\Plugin::instance()->module( 'settings_page' );

		$defaults = [
			'dashboard' => [
				'label'       => __( 'Dashboard', 'citewp-ai-search-optimizer' ),
				'description' => __( 'Cite Score, bot visits, what to fix', 'citewp-ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'dashboard', 18 ),
				'slug'        => 'dashboard',
				'render'      => [ $this, 'render_dashboard_panel' ],
			],
			'crawler-logs' => [
				'label'       => __( 'Crawler Logs', 'citewp-ai-search-optimizer' ),
				'description' => __( 'Who crawled, when, from where', 'citewp-ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'crawler-logs', 18 ),
				'slug'        => 'crawler-logs',
				'render'      => $logs_module ? [ $logs_module, 'render' ] : null,
			],
			'cite-score' => [
				'label'       => __( 'Cite Score', 'citewp-ai-search-optimizer' ),
				'description' => __( 'Per-post scores and improvements', 'citewp-ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'cite-score', 18 ),
				'slug'        => 'cite-score',
				'render'      => [ $this, 'render_cite_score_panel' ],
			],
			'settings' => [
				'label'       => __( 'Settings', 'citewp-ai-search-optimizer' ),
				'description' => __( 'Configure your preferences', 'citewp-ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'settings', 18 ),
				'slug'        => 'settings',
				'render'      => $settings_module ? [ $settings_module, 'render' ] : null,
			],
		];

		/**
		 * Filters the CiteWP admin navigation items.
		 *
		 * Each item is an associative array with:
		 *   label    (string)            Required. Nav label.
		 *   icon     (callable|string)   Preferred: a callable returning pre-escaped SVG (e.g. fn() => IconLibrary::icon( 'dashboard', 18 )).
		 *                                Back-compat: a dashicon class string (e.g. 'dashicons-chart-line'). Empty = no icon.
		 *   slug     (string)            Required. URL hash slug (e.g. 'dashboard', 'crawler-logs').
		 *   render   (callable)          For internal sections: callable that outputs the panel HTML.
		 *   external (bool)              True for external link-outs. Requires 'href'. No panel rendered.
		 *   href     (string)            URL for external items.
		 *
		 * Items with a 'render' callable register a panel in the main content area.
		 * Items with 'external => true' render as link-outs in the rail only.
		 * Extensions register through this filter by appending an item with their own render callable.
		 *
		 * @param array<string, array<string, mixed>> $items Navigation items keyed by identifier.
		 */
		$items = apply_filters( 'citewp_aiso/admin/nav', $defaults );

		// Collect internal nav slugs for the JS resolver (external items excluded).
		$nav_slugs = [];
		foreach ( $items as $item ) {
			if ( ! empty( $item['slug'] ) && empty( $item['external'] ) ) {
				$nav_slugs[] = sanitize_key( $item['slug'] );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display routing params; no data modification.
		$section_param  = sanitize_key( wp_unslash( $_GET['citewp_section']   ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; read-only.
		$settings_saved = sanitize_key( wp_unslash( $_GET['settings-updated'] ?? '' ) );
		?>
		<div class="citewp-aiso-page">

			<nav class="citewp-aiso-rail" aria-label="<?php esc_attr_e( 'CiteWP sections', 'citewp-ai-search-optimizer' ); ?>">

				<div class="citewp-aiso-rail__brand">
					<span class="citewp-aiso-rail__wordmark"><span class="citewp-aiso-rail__bracket">[</span>CiteWP<span class="citewp-aiso-rail__bracket">]</span></span>
					<span class="citewp-aiso-rail__plugin-name"><?php esc_html_e( 'AI Search Optimizer', 'citewp-ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-rail__tagline"><?php esc_html_e( 'SEO gets you ranked.', 'citewp-ai-search-optimizer' ); ?><br><?php esc_html_e( 'CiteWP gets you cited.', 'citewp-ai-search-optimizer' ); ?></span>
				</div>

				<div class="citewp-aiso-rail__nav">
					<?php foreach ( $items as $item ) :
						if ( ! isset( $item['label'], $item['slug'] ) ) {
							continue;
						}
						$external = ! empty( $item['external'] );
						$href     = $external
							? ( $item['href'] ?? '#' )
							: '#' . sanitize_key( $item['slug'] );
						$classes  = 'citewp-aiso-rail__item';
						if ( $external ) {
							$classes .= ' citewp-aiso-rail__item--external';
						}
					?>
					<a
						href="<?php echo esc_url( $href ); ?>"
						class="<?php echo esc_attr( $classes ); ?>"
						data-panel="<?php echo esc_attr( $item['slug'] ); ?>"
						<?php if ( $external ) : ?>
							target="_blank"
							rel="noopener noreferrer"
						<?php endif; ?>
					>
						<?php if ( ! empty( $item['icon'] ) ) : ?>
							<?php if ( is_callable( $item['icon'] ) ) : ?>
								<?php echo call_user_func( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
							<?php else : ?>
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
						<?php endif; ?>
						<span class="citewp-aiso-rail__item-text">
							<span class="citewp-aiso-rail__item-label"><?php echo esc_html( $item['label'] ); ?></span>
							<?php if ( ! empty( $item['description'] ) ) : ?>
								<span class="citewp-aiso-rail__item-desc"><?php echo esc_html( $item['description'] ); ?></span>
							<?php endif; ?>
						</span>
					</a>
					<?php endforeach; ?>
				</div>

				<a href="https://github.com/bradleyswpc/citewp/discussions/categories/ideas" target="_blank" rel="noopener noreferrer" class="citewp-aiso-rail__feature-link">
					<?php esc_html_e( 'Request a Feature →', 'citewp-ai-search-optimizer' ); ?>
				</a>

				<div class="citewp-aiso-rail__pro-card">
					<div class="citewp-aiso-pro__title-row">
						<span class="citewp-aiso-pro__icon"><?php echo IconLibrary::icon( 'sparkles', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></span>
						<p class="citewp-aiso-pro__heading"><?php esc_html_e( 'Upgrade to Pro', 'citewp-ai-search-optimizer' ); ?></p>
					</div>
					<p class="citewp-aiso-pro__copy"><?php esc_html_e( 'Citation tracking across AI engines, multi-site rollups, advanced insights.', 'citewp-ai-search-optimizer' ); ?></p>
					<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--citrine">
						<?php esc_html_e( 'View Pro Plans →', 'citewp-ai-search-optimizer' ); ?>
					</a>
				</div>

			</nav>

			<div class="citewp-aiso-main">
				<?php foreach ( $items as $item ) :
					if ( empty( $item['render'] ) || ! is_callable( $item['render'] ) ) {
						continue;
					}
				?>
				<div class="citewp-aiso-panel" data-panel="<?php echo esc_attr( $item['slug'] ); ?>">
					<?php call_user_func( $item['render'] ); ?>
				</div>
				<?php endforeach; ?>
			</div><!-- .citewp-aiso-main -->

		</div><!-- .citewp-aiso-page -->

		<script>
		(function () {
			var navItems = document.querySelectorAll( '.citewp-aiso-rail__item[data-panel]' );
			var panels   = document.querySelectorAll( '.citewp-aiso-panel[data-panel]' );
			var known    = <?php echo wp_json_encode( array_values( $nav_slugs ) ); ?>;
			var SS_KEY   = 'citewp_aiso_section';

			function activate( slug ) {
				navItems.forEach( function ( item ) {
					var active = item.dataset.panel === slug;
					item.classList.toggle( 'is-active', active );
					if ( active ) {
						item.setAttribute( 'aria-current', 'page' );
					} else {
						item.removeAttribute( 'aria-current' );
					}
				} );
				panels.forEach( function ( panel ) {
					var active = panel.dataset.panel === slug;
					panel.classList.toggle( 'is-active', active );
				} );
				try { sessionStorage.setItem( SS_KEY, slug ); } catch ( e ) {}
			}

			function resolveSlug() {
				// 1. URL hash (explicit nav click, bookmark).
				var hash = location.hash.replace( '#', '' );
				if ( hash && known.indexOf( hash ) !== -1 ) { return hash; }

				// 2. citewp_section query param (settings regenerate redirect).
				var section = <?php echo wp_json_encode( $section_param ); ?>;
				if ( section && known.indexOf( section ) !== -1 ) { return section; }

				// 3. settings-updated flag (WP options.php save redirect).
				if ( <?php echo wp_json_encode( $settings_saved ); ?> !== '' ) { return 'settings'; }

				// 4. sessionStorage (restores section after pagination / filter submits).
				try {
					var stored = sessionStorage.getItem( SS_KEY );
					if ( stored && known.indexOf( stored ) !== -1 ) { return stored; }
				} catch ( e ) {}

				// 5. Default to first internal nav item.
				return known[0] || 'dashboard';
			}

			var initial = resolveSlug();
			if ( location.hash !== '#' + initial ) {
				history.replaceState( null, '', '#' + initial );
			}
			activate( initial );

			window.addEventListener( 'hashchange', function () {
				var hash = location.hash.replace( '#', '' );
				if ( known.indexOf( hash ) !== -1 ) { activate( hash ); }
			} );

			navItems.forEach( function ( item ) {
				if ( item.classList.contains( 'citewp-aiso-rail__item--external' ) ) { return; }
				item.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					var slug = item.dataset.panel;
					history.pushState( null, '', '#' + slug );
					activate( slug );
				} );
			} );
		}() );
		</script>
		<?php
	}

	private function render_dashboard_panel(): void {
		$data           = new DashboardData();
		$avg_score      = $data->get_average_score();
		$trend          = $data->get_visit_trend();
		$lowest_posts   = $data->get_lowest_scoring_posts();
		$issue_count    = $data->get_issue_count();
		$top_crawlers   = $data->get_top_crawlers( 5 );
		$unique_bots    = $data->get_unique_bot_count();
		$scored_count   = $data->get_scored_count();
		$excluded_count = $data->get_excluded_count();

		$avg_grade = 'empty';
		if ( $avg_score !== null ) {
			if ( $avg_score >= 90 )      { $avg_grade = 'green'; }
			elseif ( $avg_score >= 70 )  { $avg_grade = 'yellow'; }
			elseif ( $avg_score >= 50 )  { $avg_grade = 'orange'; }
			else                         { $avg_grade = 'red'; }
		}

		$published_posts = (int) wp_count_posts( 'post' )->publish;
		$published_pages = (int) wp_count_posts( 'page' )->publish;
		$indexed_total   = $published_posts + $published_pages;

		$this_week  = (int) ( $trend['this_week'] ?? 0 );
		$last_week  = (int) ( $trend['last_week']  ?? 0 );
		$trend_diff = $this_week - $last_week;
		$trend_pct  = $last_week > 0 ? (int) round( ( $trend_diff / $last_week ) * 100 ) : 0;

		$llms_settings   = get_option( 'citewp_aiso_llms_settings', [] );
		$llms_enabled    = ! empty( $llms_settings['enabled'] );
		$severity        = $data->get_issue_severity_counts();
		$cat_scores      = $data->get_average_category_scores();
		$score_trend     = $data->get_avg_score_trend();
		$cat_breakdown   = $data->get_issue_counts_by_category();
		$cat_colors      = [ 'structure' => 'citrine', 'citability' => 'orange', 'authority' => 'red' ];
		$recent_activity = $data->get_recent_activity( 2 );

		/**
		 * Filters the Quick Actions grid items on the Dashboard.
		 *
		 * Each item: label (string), icon (string — IconLibrary name), href (string), desc (string, optional).
		 *
		 * @param array<int, array<string, string>> $actions
		 */
		$default_actions = [
			[ 'label' => __( 'Analyze Content',     'citewp-ai-search-optimizer' ), 'icon' => 'cite-score',   'href' => '#cite-score',   'desc' => __( 'Score all unscored posts',        'citewp-ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--green' ],
			[ 'label' => __( 'Regenerate llms.txt', 'citewp-ai-search-optimizer' ), 'icon' => 'llms-txt',     'href' => '#settings',     'desc' => __( 'Refresh your AI content index',   'citewp-ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--purple' ],
			[ 'label' => __( 'View Crawlers',        'citewp-ai-search-optimizer' ), 'icon' => 'crawler-logs', 'href' => '#crawler-logs', 'desc' => __( "See who's visiting your site",    'citewp-ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--blue' ],
			[ 'label' => __( 'Plugin Settings',      'citewp-ai-search-optimizer' ), 'icon' => 'settings',     'href' => '#settings',     'desc' => __( 'Configure your preferences',      'citewp-ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--orange' ],
		];
		$actions        = apply_filters( 'citewp_aiso/dashboard/quick_actions', $default_actions );
		$all_issues_url = admin_url( 'edit.php?orderby=citewp_aiso_geo_score&order=asc' );

		$current_user  = wp_get_current_user();
		$greeting_name   = ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;
		$dashboard_range = absint( $_GET['db_range'] ?? 7 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dashboard_range = in_array( $dashboard_range, [ 7, 30, 90 ], true ) ? $dashboard_range : 7;
		$dashboard_url   = menu_page_url( self::SLUG_PARENT, false );
		?>
		<!-- Dashboard page header -->
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Dashboard', 'citewp-ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php
				/* translators: %s: user's first name or display name */
				echo esc_html( sprintf( __( 'Welcome back, %s 👋', 'citewp-ai-search-optimizer' ), $greeting_name ) );
				?></p>
			</div>
			<div class="citewp-aiso-page-header__right">
				<div class="citewp-aiso-filter-pills">
					<a href="<?php echo esc_url( add_query_arg( 'db_range', 7, $dashboard_url ) ); ?>"
					   class="citewp-aiso-filter-pill <?php echo 7 === $dashboard_range ? 'citewp-aiso-filter-pill--active' : 'citewp-aiso-filter-pill--inactive'; ?>">
						<?php esc_html_e( 'Last 7 Days', 'citewp-ai-search-optimizer' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( 'db_range', 30, $dashboard_url ) ); ?>"
					   class="citewp-aiso-filter-pill <?php echo 30 === $dashboard_range ? 'citewp-aiso-filter-pill--active' : 'citewp-aiso-filter-pill--inactive'; ?>">
						<?php esc_html_e( 'Last 30 Days', 'citewp-ai-search-optimizer' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( 'db_range', 90, $dashboard_url ) ); ?>"
					   class="citewp-aiso-filter-pill <?php echo 90 === $dashboard_range ? 'citewp-aiso-filter-pill--active' : 'citewp-aiso-filter-pill--inactive'; ?>">
						<?php esc_html_e( 'Last 90 Days', 'citewp-ai-search-optimizer' ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- KPI card row — 4 cards, top-aligned -->
		<?php
		$kpi_score_grade = $avg_grade ?: 'empty';
		$grade_labels    = [ 'green' => 'EXCELLENT', 'yellow' => 'GOOD', 'orange' => 'FAIR', 'red' => 'POOR', 'empty' => 'N/A' ];
		$score_delta     = $score_trend['delta'];
		$indexed_pct     = $indexed_total > 0 ? (int) round( $scored_count / $indexed_total * 100 ) : 0;
		$issue_grade     = $severity['critical'] > 0 ? 'red' : ( $severity['minor'] > 0 ? 'orange' : 'green' );
		?>
		<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--dashboard">

			<!-- Card 1: Site Score Health -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Site Score Health', 'citewp-ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--<?php echo esc_attr( $kpi_score_grade ); ?>">
						<?php echo esc_html( $grade_labels[ $kpi_score_grade ] ?? 'N/A' ); ?>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $kpi_score_grade ); ?>">
						<?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?>
					</div>
					<div class="citewp-aiso-kpi-card__caption">
						<?php
						/* translators: %d: number of posts/pages with a Cite Score */
						echo esc_html( sprintf( __( 'out of 100 · %d scored', 'citewp-ai-search-optimizer' ), $scored_count ) ); ?>
					</div>
					<?php if ( $score_delta > 0 ) : ?>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--up">↑ +<?php echo esc_html( (string) $score_delta ); ?> pts this week</div>
					<?php elseif ( $score_delta < 0 ) : ?>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--down">↓ <?php echo esc_html( (string) $score_delta ); ?> pts this week</div>
					<?php else : ?>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <?php esc_html_e( 'no recent changes', 'citewp-ai-search-optimizer' ); ?></div>
					<?php endif; ?>
					<div class="citewp-aiso-kpi-card__category-bars">
						<?php
						$cat_bar_rows = [
							[ 'label' => __( 'Structure',  'citewp-ai-search-optimizer' ), 'pct' => $cat_scores['structure'] ],
							[ 'label' => __( 'Citability', 'citewp-ai-search-optimizer' ), 'pct' => $cat_scores['citability'] ],
							[ 'label' => __( 'Authority',  'citewp-ai-search-optimizer' ), 'pct' => $cat_scores['authority'] ],
						];
						foreach ( $cat_bar_rows as $cat_row ) :
							$bar_pct   = $cat_row['pct'];
							$bar_color = $bar_pct >= 80 ? 'green' : ( $bar_pct >= 60 ? 'citrine' : ( $bar_pct >= 40 ? 'orange' : 'red' ) );
						?>
						<div class="citewp-aiso-kpi-card__category-bar">
							<span class="citewp-aiso-kpi-card__category-bar-label"><?php echo esc_html( $cat_row['label'] ); ?></span>
							<div class="citewp-aiso-kpi-card__category-bar-track">
								<div class="citewp-aiso-kpi-card__category-bar-fill citewp-aiso-bar--<?php echo esc_attr( $bar_color ); ?>"
								     style="width:<?php echo esc_attr( (string) $bar_pct ); ?>%"></div>
							</div>
							<span class="citewp-aiso-kpi-card__category-bar-pct"><?php echo esc_html( "{$bar_pct}%" ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Card 2: Bot Visits -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Bot Visits (7d)', 'citewp-ai-search-optimizer' ); ?></span>
					<?php if ( $trend_pct > 5 ) : ?>
						<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--up">↑ <?php echo esc_html( (string) absint( $trend_pct ) ); ?>%</span>
					<?php elseif ( $trend_pct < -5 ) : ?>
						<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--down">↓ <?php echo esc_html( (string) absint( $trend_pct ) ); ?>%</span>
					<?php else : ?>
						<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--flat"><?php esc_html_e( 'Stable', 'citewp-ai-search-optimizer' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'visits this week', 'citewp-ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__sub"><?php echo esc_html( number_format_i18n( $unique_bots ) . ' ' . __( 'unique bot types', 'citewp-ai-search-optimizer' ) ); ?></div>
					<?php if ( ! empty( $top_crawlers ) ) : ?>
					<div class="citewp-aiso-kpi-card__bot-list">
						<?php foreach ( array_slice( $top_crawlers, 0, 3 ) as $bot_slot => $bot ) : ?>
						<div class="citewp-aiso-kpi-card__bot-row">
							<span class="citewp-aiso-kpi-card__bot-name">
								<span class="citewp-aiso-kpi-card__bot-dot citewp-aiso-kpi-card__bot-dot--<?php echo esc_attr( (string) ( $bot_slot + 1 ) ); ?>"></span>
								<?php echo esc_html( $bot['bot_name'] ); ?>
							</span>
							<span class="citewp-aiso-kpi-card__bot-count"><?php echo esc_html( number_format_i18n( $bot['visits'] ) ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Card 3: Indexed Pages -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Indexed Pages', 'citewp-ai-search-optimizer' ); ?></span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG_PARENT . '#settings' ) ); ?>" class="citewp-aiso-kpi-card__head-link">llms.txt</a>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value">
						<span class="citewp-aiso-kpi-card__value-main"><?php echo esc_html( number_format_i18n( $scored_count ) ); ?></span><span class="citewp-aiso-kpi-card__value-denom"><?php echo esc_html( ' / ' . number_format_i18n( $indexed_total ) ); ?></span>
					</div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'published posts &amp; pages', 'citewp-ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__progress">
						<div class="citewp-aiso-kpi-card__progress-fill" style="width:<?php echo esc_attr( (string) $indexed_pct ); ?>%"></div>
					</div>
					<div class="citewp-aiso-kpi-card__sub">
						<?php echo esc_html( "{$indexed_pct}% " . __( 'indexed', 'citewp-ai-search-optimizer' ) . ' · ' . $excluded_count . ' ' . __( 'excluded', 'citewp-ai-search-optimizer' ) ); ?>
					</div>
					<span class="citewp-aiso-kpi-card__status-pill citewp-aiso-kpi-card__status-pill--<?php echo $llms_enabled ? 'active' : 'inactive'; ?>">
						<?php echo $llms_enabled ? esc_html__( 'llms.txt ACTIVE', 'citewp-ai-search-optimizer' ) : esc_html__( 'llms.txt INACTIVE', 'citewp-ai-search-optimizer' ); ?>
					</span>
				</div>
			</div>

			<!-- Card 4: Needs Attention -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'alert-triangle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Needs Attention', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-kpi-card__head-link"><?php esc_html_e( 'View All →', 'citewp-ai-search-optimizer' ); ?></a>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $issue_grade ); ?>"><?php echo esc_html( number_format_i18n( $issue_count ) ); ?></div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'posts need work', 'citewp-ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__severity-tiles">
						<div class="citewp-aiso-kpi-card__severity-tile citewp-aiso-kpi-card__severity-tile--critical<?php echo $severity['critical'] === 0 ? ' is-zero' : ''; ?>">
							<span class="citewp-aiso-kpi-card__severity-count"><?php echo esc_html( (string) $severity['critical'] ); ?></span>
							<span class="citewp-aiso-kpi-card__severity-label"><?php esc_html_e( 'Critical', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__severity-tile citewp-aiso-kpi-card__severity-tile--minor<?php echo $severity['minor'] === 0 ? ' is-zero' : ''; ?>">
							<span class="citewp-aiso-kpi-card__severity-count"><?php echo esc_html( (string) $severity['minor'] ); ?></span>
							<span class="citewp-aiso-kpi-card__severity-label"><?php esc_html_e( 'Minor', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
					</div>
					<?php if ( array_sum( $cat_breakdown ) > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__cat-breakdown">
						<?php foreach ( $cat_breakdown as $cat => $count ) :
							if ( $count === 0 ) { continue; }
							$color = $cat_colors[ $cat ] ?? 'citrine';
						?>
						<span class="citewp-aiso-kpi-card__cat-breakdown-item">
							<span class="citewp-aiso-kpi-card__cat-breakdown-dot citewp-aiso-kpi-card__cat-breakdown-dot--<?php echo esc_attr( $color ); ?>"></span>
							<span class="citewp-aiso-kpi-card__cat-breakdown-count"><?php echo esc_html( (string) $count ); ?></span>
							<?php echo esc_html( ucfirst( $cat ) ); ?>
						</span>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .citewp-aiso-kpi-row -->

		<!-- Two-column lower section: col-a = AI Insights + Top Crawlers; col-b = Needs Attention + Quick Actions -->
		<div class="citewp-aiso-lower">

			<div class="citewp-aiso-col-a">

				<!-- AI Insights two-tone -->
				<div class="citewp-aiso-insights">
					<div class="citewp-aiso-insights__header">
						<?php echo IconLibrary::icon( 'sparkles', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-insights__title"><?php esc_html_e( 'AI Insights', 'citewp-ai-search-optimizer' ); ?></span>
						<span class="citewp-aiso-insights__badge">BETA</span>
					</div>
					<div class="citewp-aiso-insights__body">
						<div class="citewp-aiso-insights__nested">
							<div class="citewp-aiso-insights__nested-top">
								<div class="citewp-aiso-insights__orb">
									<?php echo IconLibrary::icon( 'bot', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
								</div>
								<div class="citewp-aiso-insights__headline-wrap">
									<p class="citewp-aiso-insights__headline"><?php esc_html_e( 'Your content is being discovered', 'citewp-ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-insights__sub"><?php esc_html_e( 'AI crawlers are visiting your site. Optimise to increase citation likelihood.', 'citewp-ai-search-optimizer' ); ?></p>
								</div>
							</div>
							<div class="citewp-aiso-insights__nested-bottom">
								<p class="citewp-aiso-insights__opp-label">
									<?php echo IconLibrary::icon( 'sparkles', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
									<?php esc_html_e( 'Top opportunity', 'citewp-ai-search-optimizer' ); ?>
								</p>
								<p class="citewp-aiso-insights__opp-body"><?php esc_html_e( 'Add structured schema markup to your highest-traffic posts to improve citation potential.', 'citewp-ai-search-optimizer' ); ?></p>
								<p class="citewp-aiso-insights__opp-muted"><?php esc_html_e( 'Posts with schema are 3× more likely to be cited in AI responses.', 'citewp-ai-search-optimizer' ); ?></p>
								<div class="citewp-aiso-insights__opp-actions">
									<a href="#cite-score" class="citewp-aiso-btn citewp-aiso-btn--primary-paper"><?php esc_html_e( 'View AI Recommendations →', 'citewp-ai-search-optimizer' ); ?></a>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Top Crawlers — 4-column table, top 3 rows -->
				<div class="citewp-aiso-crawlers">
					<div class="citewp-aiso-crawlers__head">
						<span class="citewp-aiso-section-head-group">
							<?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
							<h3 class="citewp-aiso-crawlers__heading"><?php esc_html_e( 'Top Crawlers (7 days)', 'citewp-ai-search-optimizer' ); ?></h3>
						</span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG_PARENT . '#crawler-logs' ) ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View Full Report →', 'citewp-ai-search-optimizer' ); ?></a>
					</div>
					<?php if ( ! empty( $top_crawlers ) ) : ?>
						<table class="citewp-aiso-crawlers__table">
							<thead>
								<tr>
									<th class="citewp-aiso-crawlers__th"><?php esc_html_e( 'Crawler', 'citewp-ai-search-optimizer' ); ?></th>
									<th class="citewp-aiso-crawlers__th"><?php esc_html_e( 'Bot Type', 'citewp-ai-search-optimizer' ); ?></th>
									<th class="citewp-aiso-crawlers__th citewp-aiso-crawlers__th--num"><?php esc_html_e( 'Visits', 'citewp-ai-search-optimizer' ); ?></th>
									<th class="citewp-aiso-crawlers__th citewp-aiso-crawlers__th--num"><?php esc_html_e( 'Trend', 'citewp-ai-search-optimizer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_crawlers as $crawler ) :
									$c_visits = (int) $crawler['visits'];
									$c_prior  = (int) ( $crawler['prior_visits'] ?? 0 );
									if ( $c_prior > 0 ) {
										$c_pct = (int) round( ( ( $c_visits - $c_prior ) / $c_prior ) * 100 );
									} else {
										$c_pct = 0;
									}
									if ( $c_pct >= 5 ) {
										$c_arrow = '↑';
										$c_cls   = 'up';
										$c_label = '+' . $c_pct . '%';
									} elseif ( $c_pct <= -5 ) {
										$c_arrow = '↓';
										$c_cls   = 'down';
										$c_label = $c_pct . '%';
									} else {
										$c_arrow = '→';
										$c_cls   = 'flat';
										$c_label = '—';
									}
								?>
								<tr class="citewp-aiso-crawlers__row">
									<td class="citewp-aiso-crawlers__cell">
										<div class="citewp-aiso-crawlers__bot">
											<span class="citewp-aiso-crawlers__avatar <?php echo esc_attr( $crawler['color_class'] ); ?>"><?php echo esc_html( $crawler['initial'] ); ?></span>
											<span class="citewp-aiso-crawlers__name"><?php echo esc_html( $crawler['display_name'] ); ?></span>
										</div>
									</td>
									<td class="citewp-aiso-crawlers__cell citewp-aiso-crawlers__type"><?php echo esc_html( $crawler['bot_type'] ?? 'AI Crawler' ); ?></td>
									<td class="citewp-aiso-crawlers__cell citewp-aiso-crawlers__count"><?php echo esc_html( number_format_i18n( $c_visits ) ); ?></td>
									<td class="citewp-aiso-crawlers__cell citewp-aiso-crawlers__trend citewp-aiso-crawlers__trend--<?php echo esc_attr( $c_cls ); ?>"><?php echo esc_html( $c_arrow . ' ' . $c_label ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<div class="citewp-aiso-empty">
							<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'calendar', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
							<p class="citewp-aiso-empty__title"><?php esc_html_e( 'No crawler visits recorded yet.', 'citewp-ai-search-optimizer' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

			</div><!-- .citewp-aiso-col-a -->

			<div class="citewp-aiso-col-b">

				<!-- Needs Attention -->
				<div class="citewp-aiso-needs">
					<div class="citewp-aiso-needs__head">
						<span class="citewp-aiso-section-head-group">
							<?php echo IconLibrary::icon( 'alert-triangle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
							<h3 class="citewp-aiso-needs__heading"><?php esc_html_e( 'Needs Attention', 'citewp-ai-search-optimizer' ); ?></h3>
						</span>
						<a href="<?php echo esc_url( $all_issues_url ); ?>" class="citewp-aiso-needs__view-all"><?php esc_html_e( 'View All Issues →', 'citewp-ai-search-optimizer' ); ?></a>
					</div>
					<?php if ( ! empty( $lowest_posts ) ) : ?>
						<?php foreach ( $lowest_posts as $post ) :
							$score     = (int) get_post_meta( $post->ID, Repository::META_KEY_TOTAL, true );
							$grade     = get_post_meta( $post->ID, Repository::META_KEY_GRADE, true );
							$grade     = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true ) ? $grade : 'red';
							$edit_url  = get_edit_post_link( $post->ID );
							$post_type = get_post_type_object( $post->post_type );
							$type_name = $post_type ? $post_type->labels->singular_name : $post->post_type;
							$time_ago  = human_time_diff( (int) get_post_modified_time( 'U', true, $post ), time() );
						?>
						<div class="citewp-aiso-needs__item">
							<div class="citewp-aiso-needs__score citewp-aiso-needs__score--<?php echo esc_attr( $grade ); ?>">
								<span class="citewp-aiso-needs__score-val"><?php echo esc_html( (string) $score ); ?></span>
								<span class="citewp-aiso-needs__score-lbl"><?php esc_html_e( 'score', 'citewp-ai-search-optimizer' ); ?></span>
							</div>
							<div class="citewp-aiso-needs__info">
								<div class="citewp-aiso-needs__title">
									<?php if ( $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
									<?php else : ?>
										<?php echo esc_html( get_the_title( $post ) ); ?>
									<?php endif; ?>
								</div>
								<div class="citewp-aiso-needs__meta">
									<span class="citewp-aiso-needs__type-pill"><?php echo esc_html( $type_name ); ?></span>
									<?php echo esc_html( $time_ago . ' ' . __( 'ago', 'citewp-ai-search-optimizer' ) ); ?>
								</div>
							</div>
							<?php if ( $edit_url ) : ?>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Improve', 'citewp-ai-search-optimizer' ); ?></a>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="citewp-aiso-empty">
							<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
							<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No score data yet.', 'citewp-ai-search-optimizer' ); ?></h3>
							<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Open any post to trigger scoring.', 'citewp-ai-search-optimizer' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Quick Actions — 4-wide single row -->
				<div class="citewp-aiso-actions">
					<div class="citewp-aiso-actions__head">
						<?php echo IconLibrary::icon( 'sparkles', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<h3 class="citewp-aiso-actions-heading"><?php esc_html_e( 'Quick Actions', 'citewp-ai-search-optimizer' ); ?></h3>
					</div>
					<div class="citewp-aiso-actions-grid">
						<?php foreach ( $actions as $action ) :
							if ( ! isset( $action['label'], $action['href'] ) ) { continue; }
							$icon_name = $action['icon'] ?? 'arrow-right';
						?>
						<a href="<?php echo esc_url( $action['href'] ); ?>" class="citewp-aiso-action-card">
							<div class="citewp-aiso-action-card__orb <?php echo esc_attr( $action['orb_class'] ?? '' ); ?>">
								<?php echo IconLibrary::icon( $icon_name, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
							</div>
							<span class="citewp-aiso-action-card__label"><?php echo esc_html( $action['label'] ); ?></span>
							<?php if ( ! empty( $action['desc'] ) ) : ?>
								<span class="citewp-aiso-action-card__desc"><?php echo esc_html( $action['desc'] ); ?></span>
							<?php endif; ?>
						</a>
						<?php endforeach; ?>
					</div>
					<div class="citewp-aiso-activity">
						<div class="citewp-aiso-activity__head">
							<span class="citewp-aiso-activity__heading"><?php esc_html_e( 'Recent Activity', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
						<?php if ( ! empty( $recent_activity ) ) : ?>
						<div class="citewp-aiso-activity__list">
							<?php foreach ( $recent_activity as $event ) : ?>
							<div class="citewp-aiso-activity__row">
								<span class="citewp-aiso-activity__icon"><?php echo IconLibrary::icon( $event['icon'], 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></span>
								<span class="citewp-aiso-activity__text"><?php echo esc_html( $event['text'] ); ?></span>
								<span class="citewp-aiso-activity__time"><?php echo esc_html( human_time_diff( $event['timestamp'] ) . ' ' . __( 'ago', 'citewp-ai-search-optimizer' ) ); ?></span>
							</div>
							<?php endforeach; ?>
						</div>
						<?php else : ?>
						<p class="citewp-aiso-activity__empty"><?php esc_html_e( 'No recent activity', 'citewp-ai-search-optimizer' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

		</div><!-- .citewp-aiso-col-b -->

		</div><!-- .citewp-aiso-lower -->

		<!-- Pro Tip — full-width bar below lower section -->
		<div class="citewp-aiso-protip">
			<div class="citewp-aiso-protip__left">
				<div class="citewp-aiso-protip__orb">
					<?php echo IconLibrary::icon( 'lightbulb', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
				</div>
				<div class="citewp-aiso-protip__content">
					<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'citewp-ai-search-optimizer' ); ?></p>
					<p class="citewp-aiso-protip__body"><strong><?php esc_html_e( 'Authority is your fastest win.', 'citewp-ai-search-optimizer' ); ?></strong> <?php esc_html_e( 'Adding author bios with credentials to your published posts is the single highest-impact change for AI citation frequency — posts with clear authorship signals are cited significantly more often by large language models.', 'citewp-ai-search-optimizer' ); ?></p>
				</div>
			</div>
		</div>

		<?php
	}

	private function render_cite_score_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ── All scored post IDs (excluding posts opted out of llms.txt) ─
		$scored_ids   = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required: filters to posts with a Cite Score while excluding llms.txt opt-outs; no alternative without a join on a custom table. Admin-only, called once per page load.
				'relation' => 'AND',
				[
					'key'     => Repository::META_KEY_TOTAL,
					'compare' => 'EXISTS',
				],
				[
					'relation' => 'OR',
					[ 'key' => '_citewp_aiso_exclude_from_llms', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_citewp_aiso_exclude_from_llms', 'value' => '1', 'compare' => '!=' ],
				],
			],
		] );
		$total_scored    = count( $scored_ids );
		$dashboard_data  = new DashboardData();
		$excluded_count  = $dashboard_data->get_excluded_count();
		$top_crawlers    = $dashboard_data->get_top_crawlers( 3 );
		$top_crawler     = ! empty( $top_crawlers ) ? $top_crawlers[0] : null;
		$top_page_rows   = $dashboard_data->get_top_crawled_pages( gmdate( 'Y-m-d H:i:s', (int) strtotime( '-7 days' ) ), 1 );
		$top_page_title  = ! empty( $top_page_rows ) ? ( $top_page_rows[0]['title'] ?? $top_page_rows[0]['request_uri'] ?? '' ) : '';
		$unique_bot_count = $dashboard_data->get_unique_bot_count();

		$schema_coverage = $dashboard_data->schema_coverage();

		// Denominator consistency check: schema_coverage() total should equal $total_scored.
		// A mismatch means query paths diverged — investigate rather than ignore.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $schema_coverage['total'] ) && $schema_coverage['total'] !== $total_scored ) {
			_doing_it_wrong(
				'render_cite_score_panel',
				sprintf(
					'schema_coverage() total (%d) does not match $total_scored (%d). A post with a stored score is missing from one query path.',
					absint( $schema_coverage['total'] ),
					absint( $total_scored )
				),
				esc_html( CITEWP_AISO_VERSION )
			);
		}

		// ── Site-wide stats (sample first 50 for signal analysis) ───────
		$score_sum      = 0;
		$issue_count    = 0;
		$critical_count = 0;
		$minor_count    = 0;
		$cat_sums       = [ 'structure' => 0, 'citability' => 0, 'authority' => 0 ];
		$signal_fails = [];
		$sample_cap   = 50;

		foreach ( $scored_ids as $i => $pid ) {
			$total      = (int) get_post_meta( (int) $pid, Repository::META_KEY_TOTAL, true );
			$score_sum += $total;
			if ( $total < 50 ) {
				++$issue_count;
				if ( $total < 40 ) {
					++$critical_count;
				} else {
					++$minor_count;
				}
			}
			if ( $i < $sample_cap ) {
				$data = ( new Repository() )->get( (int) $pid );
				if ( $data && isset( $data['categories'] ) ) {
					foreach ( array_keys( $cat_sums ) as $cat_key ) {
						if ( isset( $data['categories'][ $cat_key ]['score'] ) ) {
							$cat_sums[ $cat_key ] += (int) $data['categories'][ $cat_key ]['score'];
						}
					}
				}
				if ( $data && isset( $data['signals'] ) ) {
					foreach ( $data['signals'] as $sig ) {
						if ( in_array( $sig['status'], [ 'fail', 'partial' ], true ) ) {
							$signal_fails[ $sig['id'] ] = ( $signal_fails[ $sig['id'] ] ?? 0 ) + 1;
						}
					}
				}
			}
		}

		$posts_optimized = max( 0, $total_scored - $issue_count );
		$pct_optimized   = $total_scored > 0 ? (int) round( ( $posts_optimized / $total_scored ) * 100 ) : 0;
		$optimized_grade = match ( true ) {
			$pct_optimized >= 80 => 'green',
			$pct_optimized >= 60 => 'yellow',
			$pct_optimized >= 40 => 'orange',
			default              => 'red',
		};
		$published_total_all = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;

		$avg_score = $total_scored > 0 ? (int) round( $score_sum / $total_scored ) : null;
		$avg_grade = 'empty';
		if ( $avg_score !== null ) {
			$avg_grade = match ( true ) {
				$avg_score >= 90 => 'green',
				$avg_score >= 70 => 'yellow',
				$avg_score >= 50 => 'orange',
				default          => 'red',
			};
		}

		$avg_grade_label = match ( $avg_grade ) {
			'green'  => __( 'Excellent',         'citewp-ai-search-optimizer' ),
			'yellow' => __( 'Good',              'citewp-ai-search-optimizer' ),
			'orange' => __( 'Fair',              'citewp-ai-search-optimizer' ),
			'red'    => __( 'Needs Improvement', 'citewp-ai-search-optimizer' ),
			default  => '',
		};

		$cs_status_copy = [
			'red'    => __( 'Your site needs improvement. Fix the issues below to increase your AI citation potential.',       'citewp-ai-search-optimizer' ),
			'orange' => __( 'Your site has moderate AI citation potential. Fix the issues below to increase your score.',     'citewp-ai-search-optimizer' ),
			'yellow' => __( 'Your site is performing well. Continue improving to maximize AI citation.',                       'citewp-ai-search-optimizer' ),
			'green'  => __( 'Your site is excellently optimized for AI citation.',                                            'citewp-ai-search-optimizer' ),
			'empty'  => __( 'No posts have been scored yet. Score a post to see your site\'s AI citation potential.',         'citewp-ai-search-optimizer' ),
		];
		$cs_status_text = $cs_status_copy[ $avg_grade ] ?? $cs_status_copy['empty'];

		$sample_n = min( $total_scored, $sample_cap );
		$cat_avgs = [];
		foreach ( $cat_sums as $cat_key => $sum ) {
			$cat_avgs[ $cat_key ] = $sample_n > 0 ? (int) round( $sum / $sample_n ) : 0;
		}

		// ── Top 3 AI Recommendations — per-(signal × post-type) model ───────
		// $top_gap_label: first label from the sample-fail list (used in Needs Attention KPI card).
		arsort( $signal_fails );
		$top_gap_signal_ids = array_keys( $signal_fails );
		$top_gap_label      = null;
		{
			$_mapper = new RecommendationMapper();
			foreach ( $top_gap_signal_ids as $_sig_id ) {
				$_r = $_mapper->get( $_sig_id );
				if ( $_r && isset( $_r['label'] ) ) {
					$top_gap_label = $_r['label'];
					break;
				}
			}
			unset( $_mapper, $_sig_id, $_r );
		}

		// Canonical signal order for tie-break (rubric order — Engine.php signal sequence).
		$_signal_rubric_order = [
			'faq_schema_or_qa'   => 0,
			'heading_hierarchy'  => 1,
			'structured_blocks'  => 2,
			'answer_first'       => 3,
			'paragraph_chunks'   => 4,
			'word_count'         => 5,
			'statistics'         => 6,
			'external_citations' => 7,
			'entities'           => 8,
			'non_promotional'    => 9,
			'freshness'          => 10,
			'audience_use_case'  => 11,
			'author_byline'      => 12,
			'internal_links'     => 13,
			'schema'             => 14,
			'meta_description'   => 15,
			'featured_image'     => 16,
		];

		// Build all (signal × post-type) candidate groups across the FULL scored set.
		// get_affected_ids_for_type() queries all published posts (no sample cap).
		// Each group: signal_id, post_type, rec data, affected count, recoverable points, rubric order.
		$_rec_mapper   = new RecommendationMapper();
		$_all_groups   = [];
		foreach ( array_keys( $_signal_rubric_order ) as $_sig_id ) {
			$_rec = $_rec_mapper->get( $_sig_id );
			if ( ! $_rec ) {
				continue;
			}
			foreach ( [ 'post', 'page' ] as $_pt ) {
				$_ids = RecommendationFilter::get_affected_ids_for_type( $_sig_id, $_pt );
				$_cnt = count( $_ids );
				if ( $_cnt <= 0 ) {
					continue;
				}
				$_pts          = RecommendationFilter::get_recoverable_points_for_type( $_sig_id, $_pt );
				$_all_groups[] = [
					'signal_id'    => $_sig_id,
					'post_type'    => $_pt,
					'rec'          => $_rec,
					'count'        => $_cnt,
					'points'       => $_pts,
					'rubric_order' => $_signal_rubric_order[ $_sig_id ],
				];
			}
		}
		unset( $_rec_mapper, $_sig_id, $_rec, $_pt, $_ids, $_cnt, $_pts );

		// Sort: recoverable points DESC, then count DESC, then rubric order ASC.
		usort(
			$_all_groups,
			static function ( array $a, array $b ): int {
				if ( $b['points'] !== $a['points'] ) {
					return $b['points'] <=> $a['points'];
				}
				if ( $b['count'] !== $a['count'] ) {
					return $b['count'] <=> $a['count'];
				}
				return $a['rubric_order'] <=> $b['rubric_order'];
			}
		);
		$top_groups       = array_slice( $_all_groups, 0, 3 );
		$top_groups_count = count( $top_groups );
		unset( $_all_groups, $_signal_rubric_order );

		// ── Score History ────────────────────────────────────────────────
		$history_range     = absint( $_GET['cs_range'] ?? 30 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$history_range     = in_array( $history_range, [ 7, 30, 90 ], true ) ? $history_range : 30;
		$history           = ( new ScoreHistory() )->get_history( $history_range );
		$bot_visits_by_day = ( new DashboardData() )->get_visits_by_day( $history_range, null );
		$hist_avg          = ! empty( $history ) ? (int) round( array_sum( array_column( $history, 'avg' ) ) / count( $history ) ) : null;
		$hist_peak     = ! empty( $history ) ? (int) round( (float) max( array_column( $history, 'avg' ) ) ) : null;

		// Week-over-week delta for KPI card 1.
		$hist_delta = null;
		if ( ! empty( $history ) ) {
			$seven_ago  = gmdate( 'Y-m-d', (int) strtotime( '-7 days' ) );
			$this_slice = array_values( array_filter( $history, static fn( $e ) => $e['date'] >= $seven_ago ) );
			$prev_slice = array_values( array_filter( $history, static fn( $e ) => $e['date'] < $seven_ago ) );
			if ( ! empty( $this_slice ) && ! empty( $prev_slice ) ) {
				$this_avg   = array_sum( array_column( $this_slice, 'avg' ) ) / count( $this_slice );
				$prev_avg   = array_sum( array_column( $prev_slice, 'avg' ) ) / count( $prev_slice );
				$hist_delta = (int) round( $this_avg - $prev_avg );
			}
		}

		// ── Chart datasets — filter-based extensibility (X15). ────────────────
		$score_lookup     = array_column( $history, 'avg', 'date' );
		$default_datasets = [
			[
				'label'   => __( 'Avg Score', 'citewp-ai-search-optimizer' ),
				'axis'    => 'score',
				'color'   => '--citewp-citrine',
				'width'   => 2.0,
				'opacity' => 1.0,
				'data'    => array_map(
					fn( $bv ) => [ 'date' => $bv['date'], 'value' => isset( $score_lookup[ $bv['date'] ] ) ? (float) $score_lookup[ $bv['date'] ] : null ],
					$bot_visits_by_day
				),
			],
		];
		/**
		 * Filters the datasets rendered in the Cite Score Over Time chart.
		 *
		 * Each dataset must provide: label (string), axis ('score'|'count'), color (CSS custom property name),
		 * width (float stroke-width), opacity (float), data (array of {date: Y-m-d, value: float|int|null}).
		 * Score-axis leading nulls skip (line begins at first measurement); subsequent nulls carry the last
		 * known value forward (plateau). Default: one Avg Score dataset (axis='score'). Add datasets via filter.
		 *
		 * @param array<int, array{label: string, axis: string, color: string, width: float, opacity: float, data: array<int, array{date: string, value: float|int|null}>}> $datasets
		 * @param int $days Chart window in days.
		 */
		$chart_datasets = apply_filters( 'citewp_aiso/dashboard/score_chart_datasets', $default_datasets, $history_range );

		// ── Paginated post table ─────────────────────────────────────────
		$paged    = max( 1, isset( $_GET['csp'] ) ? absint( wp_unslash( $_GET['csp'] ) ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination param; absint() sanitizes.
		$cspp     = isset( $_GET['cspp'] ) ? absint( wp_unslash( $_GET['cspp'] ) ) : 5; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only per-page param; value validated against allowlist below.
		$per_page = in_array( $cspp, [ 5, 10, 25 ], true ) ? $cspp : 5;
		$search_q = sanitize_text_field( wp_unslash( $_GET['css'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tbl_args  = [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'meta_value_num',
			'meta_key'       => Repository::META_KEY_TOTAL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for score-sorted post list; META_KEY_TOTAL is a dedicated queryable integer meta, stored separately for this purpose. Admin-only.
			'order'          => 'ASC',
			'meta_query'     => [ [ 'key' => Repository::META_KEY_TOTAL, 'compare' => 'EXISTS' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to exclude unscored posts from the Cite Score table; same meta key as orderby above. Admin-only.
		];
		if ( $search_q !== '' ) {
			$tbl_args['s'] = $search_q;
		}
		$tbl_q       = new \WP_Query( $tbl_args );
		$total_pages = $tbl_q->max_num_pages;
		$first_item  = ( $paged - 1 ) * $per_page + 1;
		$last_item   = min( $paged * $per_page, $tbl_q->found_posts );

		// ── Category display metadata ────────────────────────────────────
		$cat_meta = [
			'structure'  => [ 'label' => __( 'Structure',  'citewp-ai-search-optimizer' ), 'max' => 35 ],
			'citability' => [ 'label' => __( 'Citability', 'citewp-ai-search-optimizer' ), 'max' => 40 ],
			'authority'  => [ 'label' => __( 'Authority',  'citewp-ai-search-optimizer' ), 'max' => 25 ],
		];
		$cat_colors = [
			'structure'  => 'var(--citewp-tint-purple)',
			'citability' => 'var(--citewp-tint-blue)',
			'authority'  => 'var(--citewp-tint-teal)',
		];
		$cat_icons = [
			'structure'  => 'layout',
			'citability' => 'quote',
			'authority'  => 'shield',
		];

		$base_url   = admin_url( 'admin.php' );
		$base_q     = [ 'page' => self::SLUG_PARENT ];
		$band_color = static function ( string $grade ): string {
			return match ( $grade ) {
				'green'  => 'var(--citewp-score-green)',
				'yellow' => 'var(--citewp-score-yellow)',
				'orange' => 'var(--citewp-score-orange)',
				default  => 'var(--citewp-score-red)',
			};
		};
		?>

		<!-- Page header strip — matches Crawler Logs / Settings pattern -->
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'citewp-ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'citewp-ai-search-optimizer' ); ?></p>
			</div>
			<div class="citewp-aiso-page-header__right"></div>
		</div>

		<!-- KPI card row — separate row below page header -->
		<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--4col citewp-aiso-cs-kpi-row">

			<!-- Card 1: Top Crawler -->
			<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--top-crawler">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Top Crawler', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
					<?php if ( $top_crawler !== null ) :
						$tc_current = (int) ( $top_crawler['visits'] ?? 0 );
						$tc_prior   = (int) ( $top_crawler['prior_visits'] ?? 0 );
						$tc_delta   = $tc_current - $tc_prior;
						if ( $tc_delta > 0 && $tc_prior > 0 ) : ?>
					<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--up">↑ <?php echo esc_html( (string) (int) round( $tc_delta / $tc_prior * 100 ) ); ?>%</span>
					<?php elseif ( $tc_delta < 0 && $tc_prior > 0 ) : ?>
					<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--down">↓ <?php echo esc_html( (string) (int) round( abs( $tc_delta ) / $tc_prior * 100 ) ); ?>%</span>
					<?php else : ?>
					<span class="citewp-aiso-kpi-card__head-pill citewp-aiso-kpi-card__head-pill--flat"><?php esc_html_e( 'Stable', 'citewp-ai-search-optimizer' ); ?></span>
					<?php endif; endif; ?>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<?php if ( $top_crawler !== null ) : ?>
					<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( $top_crawler['display_name'] ); ?></div>
					<div class="citewp-aiso-kpi-card__sub">
						<?php
						echo esc_html( sprintf(
							/* translators: %d: number of bot visits in last 7 days */
							__( '%d visits in last 7 days', 'citewp-ai-search-optimizer' ),
							(int) ( $top_crawler['visits'] ?? 0 )
						) );
						?>
					</div>
					<?php if ( ! empty( $top_page_title ) ) : ?>
					<div class="citewp-aiso-kpi-card__sub citewp-aiso-kpi-card__sub--top-page">
						<?php
						echo esc_html( sprintf(
							/* translators: %s: resolved page title of the most-crawled page */
							__( 'Top page: %s', 'citewp-ai-search-optimizer' ),
							$top_page_title
						) );
						?>
					</div>
					<?php endif; ?>
					<?php if ( $unique_bot_count > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__sub">
						<?php
						echo esc_html( sprintf(
							/* translators: %d: number of distinct AI bot types detected in last 7 days */
							__( '%d AI bots detected this week.', 'citewp-ai-search-optimizer' ),
							$unique_bot_count
						) );
						?>
					</div>
					<?php endif; ?>
					<?php if ( ! empty( $top_crawlers ) ) : ?>
					<div class="citewp-aiso-kpi-card__bot-list">
						<?php foreach ( array_slice( $top_crawlers, 0, 3 ) as $bot_slot => $bot ) : ?>
						<div class="citewp-aiso-kpi-card__bot-row">
							<span class="citewp-aiso-kpi-card__bot-name">
								<span class="citewp-aiso-kpi-card__bot-dot citewp-aiso-kpi-card__bot-dot--<?php echo esc_attr( (string) ( $bot_slot + 1 ) ); ?>"></span>
								<?php echo esc_html( $bot['display_name'] ); ?>
							</span>
							<span class="citewp-aiso-kpi-card__bot-count"><?php echo esc_html( (string) (int) $bot['visits'] ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<?php else : ?>
					<div class="citewp-aiso-kpi-card__value">—</div>
					<div class="citewp-aiso-kpi-card__sub"><?php esc_html_e( 'No AI crawler visits yet', 'citewp-ai-search-optimizer' ); ?></div>
					<?php endif; ?>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=citewp#crawler-logs' ) ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Crawler Logs →', 'citewp-ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 2: Posts/Pages Optimized -->
			<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--optimized">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'check-circle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Posts/Pages Optimized', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value">
						<span class="citewp-aiso-kpi-card__value-main"><?php echo esc_html( (string) $posts_optimized ); ?></span><span class="citewp-aiso-kpi-card__value-denom"> / <?php echo esc_html( (string) $total_scored ); ?></span>
					</div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'posts & pages with Cite Score ≥ 50', 'citewp-ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__sub"><?php
					/* translators: %d: percentage of scored posts with Cite Score ≥ 50 */
					echo esc_html( sprintf( __( '%d%% of your scored content', 'citewp-ai-search-optimizer' ), absint( $pct_optimized ) ) );
					?></div>
					<div class="citewp-aiso-kpi-progress citewp-aiso-kpi-progress--<?php echo esc_attr( $optimized_grade ); ?>">
						<div class="citewp-aiso-kpi-progress__fill" style="width: <?php echo absint( $pct_optimized ); ?>%"></div>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View All →', 'citewp-ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 3: Needs Attention -->
			<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--needs-attention">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'alert-triangle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Needs Attention', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-kpi-card__head-link"><?php esc_html_e( 'View All →', 'citewp-ai-search-optimizer' ); ?></a>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $issue_count > 0 ? ( $critical_count > 0 ? 'red' : 'orange' ) : 'green' ); ?>"><?php echo esc_html( number_format_i18n( $issue_count ) ); ?></div>
					<div class="citewp-aiso-kpi-card__caption"><?php $issue_count > 0 ? esc_html_e( 'posts need work', 'citewp-ai-search-optimizer' ) : esc_html_e( 'All posts are looking good', 'citewp-ai-search-optimizer' ); ?></div>
					<?php if ( $issue_count > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__severity-tiles">
						<div class="citewp-aiso-kpi-card__severity-tile citewp-aiso-kpi-card__severity-tile--critical<?php echo $critical_count === 0 ? ' is-zero' : ''; ?>">
							<span class="citewp-aiso-kpi-card__severity-count"><?php echo esc_html( (string) $critical_count ); ?></span>
							<span class="citewp-aiso-kpi-card__severity-label"><?php esc_html_e( 'Critical', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__severity-tile citewp-aiso-kpi-card__severity-tile--minor<?php echo $minor_count === 0 ? ' is-zero' : ''; ?>">
							<span class="citewp-aiso-kpi-card__severity-count"><?php echo esc_html( (string) $minor_count ); ?></span>
							<span class="citewp-aiso-kpi-card__severity-label"><?php esc_html_e( 'Minor', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
					</div>
					<?php endif; ?>
					<?php if ( $issue_count > 0 && $top_gap_label !== null ) : ?>
					<div class="citewp-aiso-kpi-card__sub">
						<?php echo esc_html( sprintf(
							/* translators: %s: display name of the most common failing signal */
							__( 'Most common gap: %s', 'citewp-ai-search-optimizer' ),
							$top_gap_label
						) ); ?>
					</div>
					<?php endif; ?>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="<?php echo esc_url( admin_url( 'edit.php?orderby=citewp_aiso_geo_score&order=asc' ) ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Lowest Scores →', 'citewp-ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 4: Schema Coverage -->
			<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--schema-coverage">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'layers', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Schema Coverage', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<?php if ( $schema_coverage['total'] > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $schema_coverage['pct_confirmed'] ); ?><span class="citewp-aiso-kpi-card__value-denom">%</span></div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'posts with confirmed inline schema', 'citewp-ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__schema-tiles">
						<div class="citewp-aiso-kpi-card__schema-tile citewp-aiso-kpi-card__schema-tile--confirmed">
							<span class="citewp-aiso-kpi-card__schema-tile-count"><?php echo esc_html( (string) $schema_coverage['confirmed'] ); ?></span>
							<span class="citewp-aiso-kpi-card__schema-tile-label"><?php esc_html_e( 'Confirmed', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__schema-tile citewp-aiso-kpi-card__schema-tile--seo-plugin">
							<span class="citewp-aiso-kpi-card__schema-tile-count"><?php echo esc_html( (string) $schema_coverage['partial'] ); ?></span>
							<span class="citewp-aiso-kpi-card__schema-tile-label"><?php esc_html_e( 'SEO Plugin', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__schema-tile citewp-aiso-kpi-card__schema-tile--none">
							<span class="citewp-aiso-kpi-card__schema-tile-count"><?php echo esc_html( (string) $schema_coverage['none'] ); ?></span>
							<span class="citewp-aiso-kpi-card__schema-tile-label"><?php esc_html_e( 'None', 'citewp-ai-search-optimizer' ); ?></span>
						</div>
					</div>
					<?php else : ?>
					<div class="citewp-aiso-kpi-card__value">—</div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Score your posts to see schema coverage', 'citewp-ai-search-optimizer' ); ?></div>
					<?php endif; ?>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Schema Gaps →', 'citewp-ai-search-optimizer' ); ?></a>
				</div>
			</div>

		</div><!-- /.citewp-aiso-kpi-row -->

		<?php if ( 0 === $total_scored ) : ?>
		<!-- Empty state -->
		<div class="citewp-aiso-cs-empty">
			<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored content yet.', 'citewp-ai-search-optimizer' ); ?></h3>
			<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Open and save any post or page to generate your first Cite Score.', 'citewp-ai-search-optimizer' ); ?></p>
		</div>
		<?php else : ?>

		<!-- Body: Two-column independent stack -->
		<div class="citewp-aiso-cite-score-page__body">

			<!-- Left column: 2fr — Cite Score Health + Score Breakdown sub-row, then Post table -->
			<div class="citewp-aiso-cite-score-page__left">

				<!-- Left sub-row: Cite Score Health + Score Breakdown (equal height via stretch) -->
				<div class="citewp-aiso-cite-score-page__left-row">

			<!-- Panel 1: Cite Score Health (gauge) -->
			<div class="citewp-aiso-cs-panel">
				<h3 class="citewp-aiso-cs-panel__title">
					<?php esc_html_e( 'Cite Score Health', 'citewp-ai-search-optimizer' ); ?>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is the average across all scored posts. Higher scores mean better AI citation potential.', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
				</h3>
				<div class="citewp-aiso-cs-score-wrap">
					<div>
						<?php ScoreDial::render( $avg_score ?? 0, $avg_grade ); ?>
						<p class="citewp-cite-score-gauge__meta">
							<?php
							printf(
								/* translators: %1$d: scored post count, %2$d: total published post count */
								esc_html__( 'Based on %1$d of %2$d published posts', 'citewp-ai-search-optimizer' ),
								(int) $total_scored,
								(int) $published_total_all
							);
							?>
						</p>
					</div><!-- /gauge col -->
					<div class="citewp-aiso-cs-score-right">
						<p class="citewp-aiso-cs-score-copy"><?php echo esc_html( $cs_status_text ); ?></p>
						<a href="https://citewp.com/cite-score-guide" target="_blank" rel="noopener noreferrer"
						   class="citewp-aiso-btn citewp-aiso-btn--outline citewp-aiso-cs-score-guide-btn">
							<?php esc_html_e( 'View Score Guide →', 'citewp-ai-search-optimizer' ); ?>
						</a>
					</div><!-- /copy col -->
				</div><!-- /.citewp-aiso-cs-score-wrap -->
				</div>

			<!-- Panel 2: Score Breakdown -->
			<div class="citewp-aiso-breakdown">
				<div class="citewp-aiso-breakdown__head">
					<?php esc_html_e( 'Score Breakdown', 'citewp-ai-search-optimizer' ); ?>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Your Cite Score breaks down across 3 categories. Each category contains multiple signals that AI engines consider when citing content.', 'citewp-ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<?php foreach ( $cat_meta as $cat_key => $cat_info ) :
					$avg_cat   = $cat_avgs[ $cat_key ] ?? 0;
					$cat_max   = $cat_info['max'];
					$pct       = $cat_max > 0 ? ( $avg_cat / $cat_max ) * 100 : 0;
					$bar_color = $cat_colors[ $cat_key ] ?? 'var(--citewp-text-muted)';
					$cat_icon  = $cat_icons[ $cat_key ] ?? 'gauge';
				?>
				<div class="citewp-aiso-breakdown__row">
					<div class="citewp-aiso-breakdown__label-row">
						<span class="citewp-aiso-breakdown__label">
							<span style="color:<?php echo esc_attr( $bar_color ); ?>;display:inline-flex;align-items:center;margin-right:4px">
								<?php echo IconLibrary::icon( $cat_icon, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<?php echo esc_html( $cat_info['label'] ); ?>
						</span>
						<span class="citewp-aiso-breakdown__score" style="color:var(--citewp-obsidian)">
							<?php echo esc_html( $avg_cat . ' / ' . $cat_max ); ?>
						</span>
					</div>
					<div class="citewp-aiso-breakdown__bar">
						<div class="citewp-aiso-breakdown__fill" style="width:<?php echo esc_attr( round( $pct, 1 ) . '%' ); ?>;background:<?php echo esc_attr( $bar_color ); ?>"></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			</div><!-- /.citewp-aiso-cite-score-page__left-row -->

			<!-- Cite Score Over Time — inside left column, above Post & Page table -->
			<div class="citewp-aiso-cs-panel citewp-aiso-cite-score-page__left-chart">
				<div class="citewp-aiso-cs-history-head">
					<h3 class="citewp-aiso-cs-panel__title">
						<?php esc_html_e( 'Cite Score Over Time', 'citewp-ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is recorded daily. The chart shows your average across the selected timeframe.', 'citewp-ai-search-optimizer' ); ?></span>
						</span>
					</h3>
					<form method="get" action="<?php echo esc_url( $base_url ); ?>" style="margin:0">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
						<input type="hidden" name="csp"  value="<?php echo esc_attr( (string) $paged ); ?>">
						<input type="hidden" name="cspp" value="<?php echo esc_attr( (string) $per_page ); ?>">
						<input type="hidden" name="css"  value="<?php echo esc_attr( $search_q ); ?>">
						<select name="cs_range" class="citewp-aiso-cs-perpage" onchange="this.form.submit()">
							<?php foreach ( [ 7 => __( 'Last 7 Days', 'citewp-ai-search-optimizer' ), 30 => __( 'Last 30 Days', 'citewp-ai-search-optimizer' ), 90 => __( 'Last 90 Days', 'citewp-ai-search-optimizer' ) ] as $days => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $days ); ?>"<?php selected( $days, $history_range ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</form>
				</div>
				<?php $this->render_history_svg( $chart_datasets, $history_range ); ?>
				<?php if ( $hist_avg !== null ) : ?>
				<div class="citewp-aiso-history-panel__stats">
					<div>
						<div class="citewp-aiso-history-panel__stat-label"><?php esc_html_e( 'Avg Score', 'citewp-ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-history-panel__stat-value"><?php echo esc_html( (string) $hist_avg ); ?></div>
					</div>
					<div>
						<div class="citewp-aiso-history-panel__stat-label"><?php esc_html_e( 'Peak', 'citewp-ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-history-panel__stat-value"><?php echo esc_html( (string) $hist_peak ); ?></div>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<!-- Post-Level Cite Scores table — full width of left column -->
			<div class="citewp-aiso-cs-table-wrap">

				<!-- Panel head: title + tooltip + search -->
				<div class="citewp-aiso-cs-table-head">
					<span class="citewp-aiso-cs-table-head__title">
						<?php esc_html_e( 'Post & Page Cite Scores', 'citewp-ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'All scored posts on your site, sorted by lowest Cite Score first. Click Optimize to open the post and improve its score.', 'citewp-ai-search-optimizer' ); ?></span>
						</span>
					</span>
					<form method="get" action="<?php echo esc_url( $base_url ); ?>">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
						<input type="hidden" name="cspp" value="<?php echo esc_attr( (string) $per_page ); ?>">
						<input
							type="search"
							name="css"
							class="citewp-aiso-cs-search"
							value="<?php echo esc_attr( $search_q ); ?>"
							placeholder="<?php esc_attr_e( 'Search posts…', 'citewp-ai-search-optimizer' ); ?>"
						>
					</form>
				</div>

				<?php if ( $tbl_q->have_posts() ) : ?>
				<table class="citewp-aiso-cs-table" id="citewp-aiso-cs-post-table">
					<thead>
						<tr>
							<th style="width:36%"><?php esc_html_e( 'Title',        'citewp-ai-search-optimizer' ); ?></th>
							<th style="width:10%"><?php esc_html_e( 'Cite Score',   'citewp-ai-search-optimizer' ); ?></th>
							<th style="width:8%"><?php esc_html_e( 'Trend',        'citewp-ai-search-optimizer' ); ?></th>
							<th style="width:14%"><?php esc_html_e( 'Last Updated', 'citewp-ai-search-optimizer' ); ?></th>
							<th style="width:12%"><?php esc_html_e( 'Issues',       'citewp-ai-search-optimizer' ); ?></th>
							<th style="width:20%"><?php esc_html_e( 'Actions',      'citewp-ai-search-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php while ( $tbl_q->have_posts() ) :
							$tbl_q->the_post();
							$t_id       = (int) get_the_ID();
							$t_score    = (int) get_post_meta( $t_id, Repository::META_KEY_TOTAL, true );
							$t_grade    = get_post_meta( $t_id, Repository::META_KEY_GRADE, true );
							$t_grade    = is_string( $t_grade ) && in_array( $t_grade, [ 'red', 'orange', 'yellow', 'green' ], true ) ? $t_grade : 'red';
							$t_time_raw = get_post_meta( $t_id, Repository::META_KEY_TIME, true );
							$t_time_ts  = is_string( $t_time_raw ) && $t_time_raw !== '' ? (int) strtotime( $t_time_raw ) : 0;
							$t_time_ago = $t_time_ts > 0 ? human_time_diff( $t_time_ts, time() ) . ' ' . __( 'ago', 'citewp-ai-search-optimizer' ) : '—';
							$t_post_type = (string) get_post_type();
							$t_type_icon = $t_post_type === 'page' ? 'file-text' : 'file';
							$t_edit_url  = get_edit_post_link( $t_id );
							// Issues: count failed signals from full score result
							$t_full_raw = get_post_meta( $t_id, Repository::META_KEY_FULL, true );
							$t_issues   = 0;
							if ( $t_full_raw !== '' ) {
								$t_full = maybe_unserialize( $t_full_raw );
								if ( is_array( $t_full ) && ! empty( $t_full['signals'] ) && is_array( $t_full['signals'] ) ) {
									foreach ( $t_full['signals'] as $sig ) {
										if ( is_array( $sig ) && ( $sig['status'] ?? '' ) === 'fail' ) {
											$t_issues++;
										}
									}
								}
							}
							// Trend: no per-post history available yet → show "—"
							$t_trend_html = '<span class="citewp-aiso-cs-table__trend--flat">—</span>';
							$t_excluded   = get_post_meta( $t_id, '_citewp_aiso_exclude_from_llms', true ) === '1';
						?>
						<tr>
							<td class="citewp-aiso-cs-post-cell">
								<span style="color:var(--citewp-text-muted);display:inline-flex;flex-shrink:0;margin-top:2px"><?php echo IconLibrary::icon( $t_type_icon, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<div class="citewp-aiso-cs-post-info">
									<?php if ( $t_edit_url ) : ?>
									<a class="citewp-aiso-cs-post-info__title" href="<?php echo esc_url( $t_edit_url ); ?>" title="<?php echo esc_attr( get_the_title() ?: __( '(no title)', 'citewp-ai-search-optimizer' ) ); ?>"><?php echo esc_html( get_the_title() ?: __( '(no title)', 'citewp-ai-search-optimizer' ) ); ?></a>
									<?php else : ?>
									<span class="citewp-aiso-cs-post-info__title"><?php echo esc_html( get_the_title() ?: __( '(no title)', 'citewp-ai-search-optimizer' ) ); ?></span>
									<?php endif; ?>
									<div class="citewp-aiso-cs-post-info__pills">
										<span class="citewp-aiso-cs-post-type-pill citewp-aiso-cs-post-type-pill--<?php echo esc_attr( $t_post_type ); ?>">
											<?php echo esc_html( $t_post_type === 'page' ? __( 'Page', 'citewp-ai-search-optimizer' ) : __( 'Post', 'citewp-ai-search-optimizer' ) ); ?>
										</span>
										<?php if ( $t_excluded ) : ?>
										<span class="citewp-aiso-cs-excluded-pill" title="<?php esc_attr_e( 'Excluded from llms.txt', 'citewp-ai-search-optimizer' ); ?>"><?php esc_html_e( 'Excluded', 'citewp-ai-search-optimizer' ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</td>
							<td><span class="citewp-aiso-score-pill citewp-aiso-score-pill--<?php echo esc_attr( $t_grade ); ?>"><?php echo esc_html( (string) $t_score ); ?></span></td>
							<td><?php echo $t_trend_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="citewp-aiso-cs-table__time"><?php echo esc_html( $t_time_ago ); ?></td>
							<td>
								<?php if ( $t_issues > 0 ) : ?>
								<span class="citewp-aiso-cs-table__issues--active"><?php echo esc_html( $t_issues . ' ' . _n( 'issue', 'issues', $t_issues, 'citewp-ai-search-optimizer' ) ); ?></span>
								<?php else : ?>
								<span class="citewp-aiso-cs-table__issues--none"><?php esc_html_e( 'No issues', 'citewp-ai-search-optimizer' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $t_edit_url ) : ?>
								<a href="<?php echo esc_url( $t_edit_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Optimize →', 'citewp-ai-search-optimizer' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					</tbody>
				</table>

				<!-- Footer: View All Posts (left) | numbered pagination (center) | per-page select (right) -->
				<div class="citewp-aiso-cs-pagination">
					<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View All Posts →', 'citewp-ai-search-optimizer' ); ?></a>

					<?php if ( $total_pages > 1 ) : ?>
					<div class="citewp-aiso-cs-pagination__pages">
						<?php for ( $pg = 1; $pg <= $total_pages; $pg++ ) :
							$pg_url = esc_url( add_query_arg( array_merge( $base_q, [ 'csp' => $pg, 'cspp' => $per_page, 'css' => $search_q ] ), $base_url ) . '#cite-score' );
						?>
						<?php if ( $pg === $paged ) : ?>
						<span class="citewp-aiso-cs-pagination__page is-active"><?php echo esc_html( (string) $pg ); ?></span>
						<?php else : ?>
						<a href="<?php echo $pg_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="citewp-aiso-cs-pagination__page"><?php echo esc_html( (string) $pg ); ?></a>
						<?php endif; ?>
						<?php endfor; ?>
					</div>
					<?php else : ?>
					<span></span>
					<?php endif; ?>

					<form method="get" action="<?php echo esc_url( $base_url ); ?>">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
						<input type="hidden" name="css"  value="<?php echo esc_attr( $search_q ); ?>">
						<select name="cspp" class="citewp-aiso-cs-perpage" onchange="this.form.submit()">
							<?php foreach ( [ 5, 10, 25 ] as $pp ) : ?>
							<option value="<?php echo esc_attr( (string) $pp ); ?>"<?php selected( $pp, $per_page ); ?>><?php echo esc_html( $pp . ' ' . __( 'per page', 'citewp-ai-search-optimizer' ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</form>
				</div>

				<?php else : ?>
				<div class="citewp-aiso-cs-empty">
					<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<h3 class="citewp-aiso-empty__title">
						<?php echo $search_q !== '' ? esc_html__( 'No posts match your search.', 'citewp-ai-search-optimizer' ) : esc_html__( 'No scored posts found.', 'citewp-ai-search-optimizer' ); ?>
					</h3>
				</div>
				<?php endif; ?>

			</div><!-- /.citewp-aiso-cs-table-wrap -->

		</div><!-- /.citewp-aiso-cite-score-page__left -->

		<!-- Right column: 1fr — AI Recommendations only -->
		<div class="citewp-aiso-cite-score-page__right">

			<?php
			$cite_score_rubric_url = 'https://citewp.com/cite-score';
			// Category → orb tint + icon mapping for rec rows
			$rec_cat_meta = [
				'structure'  => [ 'bg' => 'rgba(124,58,237,0.12)', 'color' => 'var(--citewp-tint-purple)', 'icon' => 'layout'   ],
				'citability' => [ 'bg' => 'rgba(37,99,235,0.12)',   'color' => 'var(--citewp-tint-blue)',   'icon' => 'quote'    ],
				'authority'  => [ 'bg' => 'rgba(20,184,166,0.12)',  'color' => 'var(--citewp-tint-teal)',   'icon' => 'shield'   ],
				''           => [ 'bg' => 'rgba(100,116,139,0.12)', 'color' => 'var(--citewp-text-muted)',  'icon' => 'sparkles' ],
			];
			// $top_groups is already computed above — top 3 (signal × type) groups
			// ranked by recoverable points. $top_groups_count is count($top_groups).
			$cs_recs_url = admin_url( 'edit.php' );
			?>
			<!-- Panel 3: AI Recommendations (uses citewp-aiso-insights class — same as Dashboard AI Insights) -->
			<div class="citewp-aiso-insights">
				<div class="citewp-aiso-insights__header">
					<span class="citewp-aiso-insights__title"><?php esc_html_e( 'AI Recommendations', 'citewp-ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-insights__badge"><?php esc_html_e( 'BETA', 'citewp-ai-search-optimizer' ); ?></span>
				</div>
				<div class="citewp-aiso-insights__body">
					<div class="citewp-aiso-insights__nested">
						<div class="citewp-aiso-insights__nested-top">
							<div class="citewp-aiso-insights__orb"
								 style="width:64px;height:64px;border-radius:14px;background:rgba(20,184,166,0.08);color:var(--citewp-tint-teal);flex-shrink:0">
								<?php echo IconLibrary::icon( 'bot', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="citewp-aiso-insights__headline-wrap">
								<p class="citewp-aiso-insights__headline"><?php esc_html_e( 'Your content can rank higher in AI search results.', 'citewp-ai-search-optimizer' ); ?></p>
								<?php if ( $top_groups_count > 0 ) : ?>
								<p class="citewp-aiso-insights__sub">
									<?php
									printf(
										/* translators: %d: number of high-impact opportunities */
										esc_html__( 'We found %d high-impact opportunities to improve.', 'citewp-ai-search-optimizer' ),
										(int) $top_groups_count
									);
									?>
								</p>
								<?php else : ?>
								<p class="citewp-aiso-insights__sub"><?php esc_html_e( 'Your content is performing well. Keep publishing to improve further.', 'citewp-ai-search-optimizer' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="citewp-aiso-insights__nested-bottom">
							<?php if ( empty( $top_groups ) ) : ?>
							<p class="citewp-aiso-cs-rec-empty"><?php esc_html_e( 'Every post and page is optimized. No recommendations right now — your content is hitting all the Cite Score signals. Nice work.', 'citewp-ai-search-optimizer' ); ?></p>
							<?php else : ?>
							<?php foreach ( $top_groups as $group ) :
								$rec_signal_id = $group['signal_id'];
								$group_type    = $group['post_type'];
								$affected_cnt  = $group['count'];
								$rec           = $group['rec'];
								$cat_key       = $rec['category'] ?? '';
								$cat_orb       = $rec_cat_meta[ $cat_key ] ?? $rec_cat_meta[''];
								$view_url      = add_query_arg(
									[
										'post_type'           => $group_type,
										'aiso_recommendation' => $rec_signal_id,
									],
									admin_url( 'edit.php' )
								);
								// Title and button noun must match the group's post type exactly.
								if ( 'page' === $group_type ) {
									$type_noun = 1 === $affected_cnt ? __( 'Page', 'citewp-ai-search-optimizer' ) : __( 'Pages', 'citewp-ai-search-optimizer' );
								} else {
									$type_noun = 1 === $affected_cnt ? __( 'Post', 'citewp-ai-search-optimizer' ) : __( 'Posts', 'citewp-ai-search-optimizer' );
								}
								/* translators: 1: count, 2: type noun (Posts or Pages) */
								$btn_label = sprintf( __( 'View %1$d %2$s', 'citewp-ai-search-optimizer' ), $affected_cnt, $type_noun );
							?>
							<div class="citewp-aiso-cs-rec-row">
								<div class="citewp-aiso-cs-rec-row__orb" style="background:<?php echo esc_attr( $cat_orb['bg'] ); ?>;color:<?php echo esc_attr( $cat_orb['color'] ); ?>">
									<?php echo IconLibrary::icon( $cat_orb['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="citewp-aiso-cs-rec-row__text">
									<div class="citewp-aiso-cs-rec-row__title">
										<?php echo esc_html( $rec['label'] ) . ' · ' . (int) $affected_cnt . ' ' . esc_html( $type_noun ); ?>
									</div>
									<div class="citewp-aiso-cs-rec-row__sub">
										<?php echo esc_html( $rec['copy'] ); ?>
										<?php if ( ! empty( $rec['anchor'] ) ) : ?>
											<a href="<?php echo esc_url( $cite_score_rubric_url . '#' . $rec['anchor'] ); ?>"
											   class="citewp-aiso-cs-rec-row__learn-more"
											   target="_blank"
											   rel="noopener">
												<?php esc_html_e( 'Learn More →', 'citewp-ai-search-optimizer' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</div>
								<a href="<?php echo esc_url( $view_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php echo esc_html( $btn_label ); ?></a>
							</div>
							<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
					<a href="<?php echo esc_url( $cs_recs_url ); ?>"
					   class="citewp-aiso-btn citewp-aiso-btn--outline citewp-aiso-cs-recs-btn">
					    <?php esc_html_e( 'View All Recommendations →', 'citewp-ai-search-optimizer' ); ?>
					</a>
				</div>
			</div>


		</div><!-- /.citewp-aiso-cite-score-page__right -->

	</div><!-- /.citewp-aiso-cite-score-page__body -->

	<!-- Pro Tip — full width, outside two-column body -->
	<?php
	$cs_protip = apply_filters(
		'citewp_aiso/protip',
		__( 'Posts scoring 80+ are significantly more likely to be cited by AI engines. Use the AI Recommendations panel to find the highest-impact fixes for your lowest-scoring content.', 'citewp-ai-search-optimizer' ),
		'cite-score'
	);
	?>
	<div class="citewp-aiso-protip">
		<div class="citewp-aiso-protip__left">
			<div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'lightbulb', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="citewp-aiso-protip__content">
				<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'citewp-ai-search-optimizer' ); ?></p>
				<p class="citewp-aiso-protip__body"><?php echo esc_html( $cs_protip ); ?></p>
			</div>
		</div>
	</div>

	<?php endif; // total_scored === 0
	}

	/**
	 * Renders the Cite Score Over Time SVG chart from a filtered dataset array.
	 *
	 * Datasets are produced by `citewp_aiso/dashboard/score_chart_datasets`. Each entry must have:
	 *   label (string), axis ('score'|'count'), color (CSS custom property name, e.g. '--citewp-citrine'),
	 *   width (float), opacity (float), data (array of {date: Y-m-d, value: float|int|null}).
	 * Score-axis nulls → line gaps. Count-axis nulls → coerced to 0. Right Y-axis max = max across all count datasets.
	 * Legend follows dataset array order (decoupled from paint order).
	 *
	 * @param array<int, array{label: string, axis: string, color: string, width: float, opacity: float, data: array<int, array{date: string, value: float|int|null}>}> $datasets
	 * @param int $days Chart window in days.
	 */
	private function render_history_svg( array $datasets, int $days ): void {
		// Validate dataset color fields against CSS custom property name format (third-party filter safety).
		$datasets = array_values(
			array_filter(
				$datasets,
				static fn( array $d ): bool => isset( $d['color'] ) && (bool) preg_match( '/^--[\w-]+$/', $d['color'] )
			)
		);

		// ── 1. Build UTC spine (same algorithm as get_visits_by_day — both must stay in sync). ──
		$now       = time();
		$cutoff_ts = strtotime( "-{$days} days", $now );
		$today     = gmdate( 'Y-m-d', $now );
		$spine     = [];
		$cursor    = gmdate( 'Y-m-d', $cutoff_ts );
		while ( $cursor <= $today ) {
			$spine[] = $cursor;
			$cursor  = gmdate( 'Y-m-d', strtotime( $cursor ) + DAY_IN_SECONDS );
		}
		$n = count( $spine );

		// ── 2. Score-axis datasets + per-dataset date→value lookup maps. ───────────────
		$score_datasets = array_values( array_filter( $datasets, fn( $d ) => ( $d['axis'] ?? '' ) === 'score' ) );
		$score_maps     = [];
		foreach ( $score_datasets as $i => $ds ) {
			$score_maps[ $i ] = array_column( $ds['data'], 'value', 'date' );
		}

		// ── 3. Empty-state: fires when no score dataset has any non-null point. ─────────
		$has_score_data = false;
		foreach ( $score_datasets as $i => $ds ) {
			foreach ( $spine as $date ) {
				if ( ( $score_maps[ $i ][ $date ] ?? null ) !== null ) {
					$has_score_data = true;
					break 2;
				}
			}
		}
		if ( ! $has_score_data ) {
			?>
			<div class="citewp-aiso-history-panel__empty">
				<svg viewBox="0 0 340 60" width="100%" height="60" aria-hidden="true">
					<line x1="0" y1="30" x2="340" y2="30" stroke="var(--citewp-border)" stroke-width="2" stroke-dasharray="6 4"/>
				</svg>
				<p class="citewp-aiso-history-panel__empty-text">
					<?php esc_html_e( 'Not enough history yet. Site Cite Score is recorded daily — check back tomorrow for your first data point.', 'citewp-ai-search-optimizer' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		// ── 3b. Sparse-data state: fewer than 3 non-null points in the selected window. ─
		$sparse_point_count = 0;
		foreach ( $score_datasets as $i => $ds ) {
			foreach ( $spine as $date ) {
				if ( ( $score_maps[ $i ][ $date ] ?? null ) !== null ) {
					$sparse_point_count++;
				}
			}
		}
		if ( $sparse_point_count < 3 ) {
			?>
			<div class="citewp-aiso-history-panel__empty">
				<svg viewBox="0 0 340 60" width="100%" height="60" aria-hidden="true">
					<line x1="0" y1="30" x2="340" y2="30" stroke="var(--citewp-border)" stroke-width="2" stroke-dasharray="6 4"/>
				</svg>
				<p class="citewp-aiso-history-panel__empty-text">
					<?php esc_html_e( 'Not enough history yet — daily scores accumulate over time.', 'citewp-ai-search-optimizer' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$w = 340;
		$h = 80;

		// ── X-axis label step. ──────────────────────────────────────────────────────────
		if ( $n <= 7 ) {
			$label_step = 1;
		} elseif ( $n <= 30 ) {
			$label_step = 5;
		} else {
			$label_step = 15;
		}
		?>
		<div class="citewp-aiso-cs-history-wrap">
			<div class="citewp-aiso-cs-history-yaxis" aria-hidden="true">
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:10%">100</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:30%">75</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:50%">50</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:70%">25</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:90%">0</span>
			</div>
			<svg viewBox="0 0 <?php echo esc_attr( (string) $w ); ?> <?php echo esc_attr( (string) $h ); ?>"
				width="100%" height="<?php echo esc_attr( (string) $h ); ?>" preserveAspectRatio="none" aria-hidden="true">
				<line x1="0" y1="8"  x2="340" y2="8"  stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="24" x2="340" y2="24" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="40" x2="340" y2="40" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="56" x2="340" y2="56" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="72" x2="340" y2="72" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<?php
				// All-null dataset → skip element entirely (no <path d="">).
				foreach ( $score_datasets as $idx => $ds ) :
					$path         = '';
					$any_point    = false;
					$path_started = false;
					$last_known   = null;
					foreach ( $spine as $si => $date ) {
						$v = $score_maps[ $idx ][ $date ] ?? null;
						if ( $v !== null ) {
							$last_known = (float) $v;
						}
						// Leading nulls: no prior value to carry — line begins at first measurement.
						if ( $last_known === null ) {
							continue;
						}
						$any_point    = true;
						$x            = $n > 1 ? (int) round( ( $si / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
						$y            = (int) round( $h - ( $last_known / 100.0 ) * ( $h * 0.8 ) - $h * 0.1 );
						$path        .= ( $path_started ? " L {$x} {$y}" : "M {$x} {$y}" );
						$path_started = true;
					}
					if ( $any_point ) :
				?>
				<path d="<?php echo esc_attr( $path ); ?>" fill="none"
					stroke="var(<?php echo esc_attr( $ds['color'] ); ?>)"
					stroke-width="<?php echo esc_attr( (string) $ds['width'] ); ?>"
					stroke-opacity="<?php echo esc_attr( (string) $ds['opacity'] ); ?>"
					stroke-linejoin="round" stroke-linecap="round"/>
				<?php endif; endforeach; ?>
			</svg>
		</div>
		<div class="citewp-aiso-chart-xlabels">
			<?php
			foreach ( $spine as $si => $date ) :
				if ( $n > 1 && $si % $label_step !== 0 && $si !== $n - 1 ) {
					continue;
				}
				$left_pct  = $n > 1 ? round( $si / ( $n - 1 ) * 100, 2 ) : 50.0;
				$parts     = explode( '-', $date );
				$label_str = ltrim( $parts[1] ?? '', '0' ) . '/' . ltrim( $parts[2] ?? '', '0' );
			?>
				<span class="citewp-aiso-chart-xlabels__label" style="left:<?php echo esc_attr( $left_pct . '%' ); ?>">
					<?php echo esc_html( $label_str ); ?>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
