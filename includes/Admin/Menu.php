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
			__( 'CiteWP', 'ai-search-optimizer' ),
			__( 'CiteWP', 'ai-search-optimizer' ),
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
				'label'       => __( 'Dashboard', 'ai-search-optimizer' ),
				'description' => __( 'Cite Score, bot visits, what to fix', 'ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'dashboard', 18 ),
				'slug'        => 'dashboard',
				'render'      => [ $this, 'render_dashboard_panel' ],
			],
			'crawler-logs' => [
				'label'       => __( 'Crawler Logs', 'ai-search-optimizer' ),
				'description' => __( 'Who crawled, when, from where', 'ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'crawler-logs', 18 ),
				'slug'        => 'crawler-logs',
				'render'      => $logs_module ? [ $logs_module, 'render' ] : null,
			],
			'cite-score' => [
				'label'       => __( 'Cite Score', 'ai-search-optimizer' ),
				'description' => __( 'Per-post scores and improvements', 'ai-search-optimizer' ),
				'icon'        => fn() => IconLibrary::icon( 'cite-score', 18 ),
				'slug'        => 'cite-score',
				'render'      => [ $this, 'render_cite_score_panel' ],
			],
			'settings' => [
				'label'       => __( 'Settings', 'ai-search-optimizer' ),
				'description' => __( 'Configure your preferences', 'ai-search-optimizer' ),
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

			<nav class="citewp-aiso-rail" aria-label="<?php esc_attr_e( 'CiteWP sections', 'ai-search-optimizer' ); ?>">

				<div class="citewp-aiso-rail__brand">
					<span class="citewp-aiso-rail__wordmark"><span class="citewp-aiso-rail__bracket">[</span>CiteWP<span class="citewp-aiso-rail__bracket">]</span></span>
					<span class="citewp-aiso-rail__plugin-name"><?php esc_html_e( 'AI Search Optimizer', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-rail__tagline"><?php esc_html_e( 'SEO gets you ranked.', 'ai-search-optimizer' ); ?><br><?php esc_html_e( 'CiteWP gets you cited.', 'ai-search-optimizer' ); ?></span>
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

				<div class="citewp-aiso-rail__pro-card">
					<div class="citewp-aiso-pro__title-row">
						<span class="citewp-aiso-pro__icon"><?php echo IconLibrary::icon( 'sparkles', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></span>
						<p class="citewp-aiso-pro__heading"><?php esc_html_e( 'Upgrade to Pro', 'ai-search-optimizer' ); ?></p>
					</div>
					<p class="citewp-aiso-pro__copy"><?php esc_html_e( 'Citation tracking across AI engines, multi-site rollups, advanced insights.', 'ai-search-optimizer' ); ?></p>
					<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--citrine">
						<?php esc_html_e( 'View Pro Plans →', 'ai-search-optimizer' ); ?>
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

		$sparkline_data = $data->get_visits_by_day( 30, null );
		$sparkline_svg  = $data->render_sparkline_svg( $sparkline_data, 'bot-visits' );


		/**
		 * Filters the Quick Actions grid items on the Dashboard.
		 *
		 * Each item: label (string), icon (string — IconLibrary name), href (string), desc (string, optional).
		 *
		 * @param array<int, array<string, string>> $actions
		 */
		$default_actions = [
			[ 'label' => __( 'Analyze Content',     'ai-search-optimizer' ), 'icon' => 'cite-score',   'href' => '#cite-score',   'desc' => __( 'Score all unscored posts',        'ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--green' ],
			[ 'label' => __( 'Regenerate llms.txt', 'ai-search-optimizer' ), 'icon' => 'llms-txt',     'href' => '#settings',     'desc' => __( 'Refresh your AI content index',   'ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--purple' ],
			[ 'label' => __( 'View Crawlers',        'ai-search-optimizer' ), 'icon' => 'crawler-logs', 'href' => '#crawler-logs', 'desc' => __( "See who's visiting your site",    'ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--blue' ],
			[ 'label' => __( 'Plugin Settings',      'ai-search-optimizer' ), 'icon' => 'settings',     'href' => '#settings',     'desc' => __( 'Configure your preferences',      'ai-search-optimizer' ), 'orb_class' => 'citewp-aiso-action-card__orb--orange' ],
		];
		$actions        = apply_filters( 'citewp_aiso/dashboard/quick_actions', $default_actions );
		$all_issues_url = admin_url( 'edit.php?orderby=citewp_aiso_geo_score&order=asc' );

		$current_user  = wp_get_current_user();
		$greeting_name = ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;
		?>
		<!-- Hero card -->
		<div class="citewp-aiso-hero">
			<div class="citewp-aiso-hero__left">
				<h2 class="citewp-aiso-hero__title"><?php
				/* translators: %s: user's first name or display name */
				echo esc_html( sprintf( __( 'Welcome back, %s 👋', 'ai-search-optimizer' ), $greeting_name ) );
				?></h2>
				<p class="citewp-aiso-hero__sub"><?php esc_html_e( "Here's how your site is performing in AI search.", 'ai-search-optimizer' ); ?></p>
				<div class="citewp-aiso-hero__filters">
					<span class="citewp-aiso-hero__filter is-active">
						<?php echo IconLibrary::icon( 'calendar', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Last 7 Days', 'ai-search-optimizer' ); ?>
					</span>
					<span class="citewp-aiso-hero__filter">
						<?php echo IconLibrary::icon( 'bot', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( number_format_i18n( $unique_bots ) . ' ' . __( 'Bots', 'ai-search-optimizer' ) ); ?>
					</span>
					<span class="citewp-aiso-hero__filter">
						<?php echo IconLibrary::icon( 'eye', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( number_format_i18n( $this_week ) . ' ' . __( 'Visits', 'ai-search-optimizer' ) ); ?>
					</span>
				</div>
			</div>
			<div class="citewp-aiso-hero__stats">
				<div class="citewp-aiso-hero__stat">
					<div class="citewp-aiso-hero__stat-head">
						<span style="color:var(--citewp-tint-orange)"><?php echo IconLibrary::icon( 'alert-triangle', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></span>
						<span class="citewp-aiso-hero__stat-label"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></span>
					</div>
					<span class="citewp-aiso-hero__stat-value"><?php echo esc_html( number_format_i18n( $issue_count ) ); ?></span>
					<span class="citewp-aiso-hero__stat-sub"><?php esc_html_e( 'red or orange score', 'ai-search-optimizer' ); ?></span>
				</div>
			</div>
		</div>

		<!-- KPI card row -->
		<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">

			<!-- Card 1: Site Score Health -->
			<?php $kpi_score_grade = $avg_grade ?: 'empty'; ?>
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Site Score Health', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Average score across all scored posts and pages.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $kpi_score_grade ); ?>">
							<?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?>
						</div>
						<div class="citewp-aiso-kpi-card__caption citewp-aiso-kpi-score--<?php echo esc_attr( $kpi_score_grade ); ?>">
							<?php echo esc_html( ScoreDial::grade_label( $kpi_score_grade ) ); ?>
						</div>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <?php esc_html_e( 'no recent changes', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-kpi-card__sub">
							<span class="citewp-aiso-kpi-card__pages-scored">
								<?php echo esc_html( "{$scored_count} of {$indexed_total} pages included" ); ?>
							</span>
							<?php if ( $excluded_count > 0 ) : ?>
							<span
								class="citewp-aiso-kpi-card__exclusion-note"
								title="<?php esc_attr_e( 'Posts toggled off from llms.txt are excluded from this average. They still appear in the post-level table below.', 'ai-search-optimizer' ); ?>"
							>
								<?php echo esc_html( "({$excluded_count} excluded from llms.txt)" ); ?>
							</span>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#cite-score" class="citewp-aiso-btn"><?php esc_html_e( 'View Scores →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 2: Bot Visits -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'AI crawler visits to your site over the last 7 days.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__data">
						<?php echo $sparkline_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_sparkline_svg() returns safe SVG; all dynamic path data is escaped via esc_attr() internally. ?>
						<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></div>
						<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'AI crawler visits', 'ai-search-optimizer' ); ?></div>
						<?php if ( $trend_pct > 5 ) : ?>
							<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--up">↑ <?php echo esc_html( (string) absint( $trend_pct ) ); ?>%</div>
						<?php elseif ( $trend_pct < -5 ) : ?>
							<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--down">↓ <?php echo esc_html( (string) absint( $trend_pct ) ); ?>%</div>
						<?php else : ?>
							<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <?php esc_html_e( 'no recent changes', 'ai-search-optimizer' ); ?></div>
						<?php endif; ?>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#crawler-logs" class="citewp-aiso-btn"><?php esc_html_e( 'View Logs →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 3: Indexed Pages -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Indexed Pages', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages currently published and scoreable.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $indexed_total ) ); ?></div>
						<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Published posts &amp; pages', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <?php esc_html_e( 'no recent changes', 'ai-search-optimizer' ); ?></div>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="citewp-aiso-btn"><?php esc_html_e( 'View All →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>

		</div><!-- .citewp-aiso-kpi-row -->

		<!-- Two-column lower section: col-a = AI Insights + Top Crawlers; col-b = Needs Attention + Quick Actions -->
		<div class="citewp-aiso-lower">

			<div class="citewp-aiso-col-a">

				<!-- Top Crawlers — 4-column table, top 3 rows -->
				<div class="citewp-aiso-crawlers">
					<div class="citewp-aiso-crawlers__head">
						<span class="citewp-aiso-section-head-group">
							<?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
							<h3 class="citewp-aiso-crawlers__heading"><?php esc_html_e( 'Top Crawlers (7 days)', 'ai-search-optimizer' ); ?></h3>
						</span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG_PARENT . '#crawler-logs' ) ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View Full Report →', 'ai-search-optimizer' ); ?></a>
					</div>
					<?php if ( ! empty( $top_crawlers ) ) : ?>
						<table class="citewp-aiso-crawlers__table">
							<thead>
								<tr>
									<th class="citewp-aiso-crawlers__th"><?php esc_html_e( 'Crawler', 'ai-search-optimizer' ); ?></th>
									<th class="citewp-aiso-crawlers__th"><?php esc_html_e( 'Bot Type', 'ai-search-optimizer' ); ?></th>
									<th class="citewp-aiso-crawlers__th citewp-aiso-crawlers__th--num"><?php esc_html_e( 'Visits', 'ai-search-optimizer' ); ?></th>
									<th class="citewp-aiso-crawlers__th citewp-aiso-crawlers__th--num"><?php esc_html_e( 'Trend', 'ai-search-optimizer' ); ?></th>
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
							<p class="citewp-aiso-empty__title"><?php esc_html_e( 'No crawler visits recorded yet.', 'ai-search-optimizer' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<!-- AI Insights two-tone -->
				<div class="citewp-aiso-insights">
					<div class="citewp-aiso-insights__header">
						<?php echo IconLibrary::icon( 'sparkles', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-insights__title"><?php esc_html_e( 'AI Insights', 'ai-search-optimizer' ); ?></span>
						<span class="citewp-aiso-insights__badge">BETA</span>
					</div>
					<div class="citewp-aiso-insights__body">
						<div class="citewp-aiso-insights__nested">
							<div class="citewp-aiso-insights__nested-top">
								<div class="citewp-aiso-insights__orb">
									<?php echo IconLibrary::icon( 'bot', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
								</div>
								<div class="citewp-aiso-insights__headline-wrap">
									<p class="citewp-aiso-insights__headline"><?php esc_html_e( 'Your content is being discovered', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-insights__sub"><?php esc_html_e( 'AI crawlers are visiting your site. Optimise to increase citation likelihood.', 'ai-search-optimizer' ); ?></p>
								</div>
							</div>
							<div class="citewp-aiso-insights__nested-bottom">
								<p class="citewp-aiso-insights__opp-label">
									<?php echo IconLibrary::icon( 'sparkles', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
									<?php esc_html_e( 'Top opportunity', 'ai-search-optimizer' ); ?>
								</p>
								<p class="citewp-aiso-insights__opp-body"><?php esc_html_e( 'Add structured schema markup to your highest-traffic posts to improve citation potential.', 'ai-search-optimizer' ); ?></p>
								<p class="citewp-aiso-insights__opp-muted"><?php esc_html_e( 'Posts with schema are 3× more likely to be cited in AI responses.', 'ai-search-optimizer' ); ?></p>
								<div class="citewp-aiso-insights__opp-actions">
									<a href="#cite-score" class="citewp-aiso-btn citewp-aiso-btn--primary-paper"><?php esc_html_e( 'View AI Recommendations →', 'ai-search-optimizer' ); ?></a>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div><!-- .citewp-aiso-col-a -->

			<div class="citewp-aiso-col-b">

				<!-- Needs Attention -->
				<div class="citewp-aiso-needs">
					<div class="citewp-aiso-needs__head">
						<span class="citewp-aiso-section-head-group">
							<?php echo IconLibrary::icon( 'alert-triangle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
							<h3 class="citewp-aiso-needs__heading"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></h3>
						</span>
						<a href="<?php echo esc_url( $all_issues_url ); ?>" class="citewp-aiso-needs__view-all"><?php esc_html_e( 'View All Issues →', 'ai-search-optimizer' ); ?></a>
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
								<span class="citewp-aiso-needs__score-lbl"><?php esc_html_e( 'score', 'ai-search-optimizer' ); ?></span>
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
									<?php echo esc_html( $time_ago . ' ' . __( 'ago', 'ai-search-optimizer' ) ); ?>
								</div>
							</div>
							<?php if ( $edit_url ) : ?>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Improve', 'ai-search-optimizer' ); ?></a>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="citewp-aiso-empty">
							<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
							<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No score data yet.', 'ai-search-optimizer' ); ?></h3>
							<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Open any post to trigger scoring.', 'ai-search-optimizer' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Quick Actions — 4-wide single row -->
				<div class="citewp-aiso-actions">
					<div class="citewp-aiso-actions__head">
						<?php echo IconLibrary::icon( 'sparkles', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<h3 class="citewp-aiso-actions-heading"><?php esc_html_e( 'Quick Actions', 'ai-search-optimizer' ); ?></h3>
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
				</div>

			<!-- Pro Tip — inside col-b, below Quick Actions -->
			<div class="citewp-aiso-protip">
				<div class="citewp-aiso-protip__left">
					<div class="citewp-aiso-protip__orb">
						<?php echo IconLibrary::icon( 'lightbulb', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<div class="citewp-aiso-protip__content">
						<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-protip__body"><?php esc_html_e( 'Connect Google Search Console to see which pages get discovered before being crawled.', 'ai-search-optimizer' ); ?></p>
					</div>
				</div>
				<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper"><?php esc_html_e( 'Connect Now →', 'ai-search-optimizer' ); ?></a>
			</div>

		</div><!-- .citewp-aiso-col-b -->

		</div><!-- .citewp-aiso-lower -->

		<script>
		(function () {
			var filters = document.querySelectorAll( '.citewp-aiso-hero__filter' );
			filters.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					filters.forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
					btn.classList.add( 'is-active' );
				} );
			} );
		}() );
		</script>
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
		$top_crawlers    = $dashboard_data->get_top_crawlers( 1 );
		$top_crawler     = ! empty( $top_crawlers ) ? $top_crawlers[0] : null;

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
			'green'  => __( 'Excellent',         'ai-search-optimizer' ),
			'yellow' => __( 'Good',              'ai-search-optimizer' ),
			'orange' => __( 'Fair',              'ai-search-optimizer' ),
			'red'    => __( 'Needs Improvement', 'ai-search-optimizer' ),
			default  => '',
		};

		$cs_status_copy = [
			'red'    => __( 'Your site needs improvement. Fix the issues below to increase your AI citation potential.',       'ai-search-optimizer' ),
			'orange' => __( 'Your site has moderate AI citation potential. Fix the issues below to increase your score.',     'ai-search-optimizer' ),
			'yellow' => __( 'Your site is performing well. Continue improving to maximize AI citation.',                       'ai-search-optimizer' ),
			'green'  => __( 'Your site is excellently optimized for AI citation.',                                            'ai-search-optimizer' ),
			'empty'  => __( 'No posts have been scored yet. Score a post to see your site\'s AI citation potential.',         'ai-search-optimizer' ),
		];
		$cs_status_text = $cs_status_copy[ $avg_grade ] ?? $cs_status_copy['empty'];

		$sample_n = min( $total_scored, $sample_cap );
		$cat_avgs = [];
		foreach ( $cat_sums as $cat_key => $sum ) {
			$cat_avgs[ $cat_key ] = $sample_n > 0 ? (int) round( $sum / $sample_n ) : 0;
		}

		// ── Top 3 failing signals → AI Recommendations ──────────────────
		arsort( $signal_fails );
		$top_signal_ids = array_slice( array_keys( $signal_fails ), 0, 3 );
		$mapper         = new RecommendationMapper();
		$top_recs       = $mapper->get_many( $top_signal_ids );
		$top_rec_ids    = array_keys( $top_recs );

		// Pad to exactly 3 rows.
		$recs_display = array_values( $top_recs );
		while ( count( $recs_display ) < 3 ) {
			$recs_display[] = [
				'label'    => __( 'Keep publishing', 'ai-search-optimizer' ),
				'copy'     => __( 'Your content is performing well. Keep publishing high-quality posts to maintain your score.', 'ai-search-optimizer' ),
				'category' => '',
			];
		}

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
			'structure'  => [ 'label' => __( 'Structure',  'ai-search-optimizer' ), 'max' => 35 ],
			'citability' => [ 'label' => __( 'Citability', 'ai-search-optimizer' ), 'max' => 40 ],
			'authority'  => [ 'label' => __( 'Authority',  'ai-search-optimizer' ), 'max' => 25 ],
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
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'ai-search-optimizer' ); ?></p>
			</div>
			<div class="citewp-aiso-page-header__right"></div>
		</div>

		<!-- KPI card row — separate row below page header -->
		<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col citewp-aiso-cs-kpi-row">

			<!-- Card 1: Top Crawler -->
			<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--top-crawler">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Top Crawler', 'ai-search-optimizer' ); ?></span>
					<span
						class="citewp-aiso-kpi-card__info"
						data-tooltip="<?php esc_attr_e( 'The AI bot that\'s visited your site most often in the last 7 days. A signal that your optimization work is being noticed.', 'ai-search-optimizer' ); ?>"
					>
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
						<?php echo IconLibrary::icon( 'bot', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="citewp-aiso-kpi-card__data">
						<?php if ( $top_crawler !== null ) : ?>
						<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( $top_crawler['display_name'] ); ?></div>
						<div class="citewp-aiso-kpi-card__sub">
							<?php
							echo esc_html( sprintf(
								/* translators: %d: number of bot visits in last 7 days */
								__( '%d visits in last 7 days', 'ai-search-optimizer' ),
								(int) ( $top_crawler['visits'] ?? 0 )
							) );
							?>
						</div>
						<?php
						$tc_current = (int) ( $top_crawler['visits'] ?? 0 );
						$tc_prior   = (int) ( $top_crawler['prior_visits'] ?? 0 );
						$tc_delta   = $tc_current - $tc_prior;
						$tc_trend_class = 'citewp-aiso-kpi-card__trend--flat';
						if ( $tc_current > 0 || $tc_prior > 0 ) :
							$tc_trend_class = $tc_delta > 0 ? 'citewp-aiso-kpi-card__trend--up' : ( $tc_delta < 0 ? 'citewp-aiso-kpi-card__trend--down' : 'citewp-aiso-kpi-card__trend--flat' );
						?>
						<div class="citewp-aiso-kpi-card__trend <?php echo esc_attr( $tc_trend_class ); ?>">
							<?php if ( $tc_delta > 0 ) : ?>
								<?php echo esc_html( '↑' ); ?> +<?php echo esc_html( (string) $tc_delta ); ?> <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?></span>
							<?php elseif ( $tc_delta < 0 ) : ?>
								<?php echo esc_html( '↓' ); ?> <?php echo esc_html( (string) $tc_delta ); ?> <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?></span>
							<?php else : ?>
								<?php echo esc_html( '→' ); ?> <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'no change vs. prior 7 days', 'ai-search-optimizer' ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<?php else : ?>
						<div class="citewp-aiso-kpi-card__value">—</div>
						<div class="citewp-aiso-kpi-card__sub"><?php esc_html_e( 'No AI crawler visits yet', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Visits typically begin within 24–72 hours of publishing.', 'ai-search-optimizer' ); ?></div>
						<?php endif; ?>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=citewp#crawler-logs' ) ); ?>"><?php esc_html_e( 'View Crawler Logs →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 2: Posts Optimized -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Posts/Pages Optimized', 'ai-search-optimizer' ); ?></span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
						<?php echo IconLibrary::icon( 'check-circle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value-row">
							<span class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $posts_optimized ); ?></span>
							<span class="citewp-aiso-kpi-card__pct"><?php echo esc_html( $pct_optimized . '%' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-progress">
							<div class="citewp-aiso-kpi-progress__fill" style="width:<?php echo esc_attr( (string) $pct_optimized ); ?>%"></div>
						</div>
						<div class="citewp-aiso-kpi-card__sub"><?php
						/* translators: %d: percentage of posts and pages that have a Cite Score */
						echo esc_html( sprintf( __( '%d%% of your content is optimized', 'ai-search-optimizer' ), absint( $pct_optimized ) ) );
						?></div>
					</div>
				</div>
			</div>

			<!-- Card 3: Issues Detected -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Issues Detected', 'ai-search-optimizer' ); ?></span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:rgba(249,115,22,0.12);color:var(--citewp-tint-orange)">
						<?php echo IconLibrary::icon( 'alert-triangle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $issue_count ); ?></div>
						<div class="citewp-aiso-kpi-card__sub"><?php
						/* translators: %d: number of posts or pages with unresolved Cite Score issues */
						echo esc_html( sprintf( __( 'across %d items needing attention', 'ai-search-optimizer' ), absint( $issue_count ) ) );
						?></div>
						<div class="citewp-aiso-kpi-card__split">
							<span class="citewp-aiso-kpi-card__split--critical"><?php echo esc_html( (string) $critical_count ); ?> <?php esc_html_e( 'critical', 'ai-search-optimizer' ); ?></span>
							<span class="citewp-aiso-kpi-card__split--sep">·</span>
							<span class="citewp-aiso-kpi-card__split--minor"><?php echo esc_html( (string) $minor_count ); ?> <?php esc_html_e( 'minor', 'ai-search-optimizer' ); ?></span>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /.citewp-aiso-kpi-row -->

		<?php if ( 0 === $total_scored ) : ?>
		<!-- Empty state -->
		<div class="citewp-aiso-cs-empty">
			<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored content yet.', 'ai-search-optimizer' ); ?></h3>
			<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Open and save any post or page to generate your first Cite Score.', 'ai-search-optimizer' ); ?></p>
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
					<?php esc_html_e( 'Cite Score Health', 'ai-search-optimizer' ); ?>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is the average across all scored posts. Higher scores mean better AI citation potential.', 'ai-search-optimizer' ); ?></span>
					</span>
				</h3>
				<div class="citewp-aiso-cs-score-wrap">
					<div>
						<?php ScoreDial::render( $avg_score ?? 0, $avg_grade ); ?>
						<p class="citewp-cite-score-gauge__meta">
							<?php
							printf(
								/* translators: %1$d: scored post count, %2$d: total published post count */
								esc_html__( 'Based on %1$d of %2$d published posts', 'ai-search-optimizer' ),
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
							<?php esc_html_e( 'View Score Guide →', 'ai-search-optimizer' ); ?>
						</a>
					</div><!-- /copy col -->
				</div><!-- /.citewp-aiso-cs-score-wrap -->
				</div>

			<!-- Panel 2: Score Breakdown -->
			<div class="citewp-aiso-breakdown">
				<div class="citewp-aiso-breakdown__head">
					<?php esc_html_e( 'Score Breakdown', 'ai-search-optimizer' ); ?>
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Your Cite Score breaks down across 3 categories. Each category contains multiple signals that AI engines consider when citing content.', 'ai-search-optimizer' ); ?></span>
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

			<!-- Post-Level Cite Scores table — full width of left column -->
			<div class="citewp-aiso-cs-table-wrap">

				<!-- Panel head: title + tooltip + search -->
				<div class="citewp-aiso-cs-table-head">
					<span class="citewp-aiso-cs-table-head__title">
						<?php esc_html_e( 'Post & Page Cite Scores', 'ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'All scored posts on your site, sorted by lowest Cite Score first. Click Optimize to open the post and improve its score.', 'ai-search-optimizer' ); ?></span>
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
							placeholder="<?php esc_attr_e( 'Search posts…', 'ai-search-optimizer' ); ?>"
						>
					</form>
				</div>

				<?php if ( $tbl_q->have_posts() ) : ?>
				<table class="citewp-aiso-cs-table">
					<thead>
						<tr>
							<th style="width:36%"><?php esc_html_e( 'Title',        'ai-search-optimizer' ); ?></th>
							<th style="width:10%"><?php esc_html_e( 'Cite Score',   'ai-search-optimizer' ); ?></th>
							<th style="width:8%"><?php esc_html_e( 'Trend',        'ai-search-optimizer' ); ?></th>
							<th style="width:14%"><?php esc_html_e( 'Last Updated', 'ai-search-optimizer' ); ?></th>
							<th style="width:12%"><?php esc_html_e( 'Issues',       'ai-search-optimizer' ); ?></th>
							<th style="width:20%"><?php esc_html_e( 'Actions',      'ai-search-optimizer' ); ?></th>
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
							$t_time_ago = $t_time_ts > 0 ? human_time_diff( $t_time_ts, time() ) . ' ' . __( 'ago', 'ai-search-optimizer' ) : '—';
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
						?>
						<tr>
							<td class="citewp-aiso-cs-post-cell">
								<span style="color:var(--citewp-text-muted);display:inline-flex;flex-shrink:0;margin-top:2px"><?php echo IconLibrary::icon( $t_type_icon, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php if ( $t_edit_url ) : ?>
								<a href="<?php echo esc_url( $t_edit_url ); ?>" title="<?php echo esc_attr( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?>"><?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?></a>
								<?php else : ?>
								<span><?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?></span>
								<?php endif; ?>
								<span class="citewp-aiso-cs-post-type-pill citewp-aiso-cs-post-type-pill--<?php echo esc_attr( $t_post_type ); ?>">
									<?php echo esc_html( $t_post_type === 'page' ? __( 'Page', 'ai-search-optimizer' ) : __( 'Post', 'ai-search-optimizer' ) ); ?>
								</span>
							</td>
							<td><span class="citewp-aiso-score-pill citewp-aiso-score-pill--<?php echo esc_attr( $t_grade ); ?>"><?php echo esc_html( (string) $t_score ); ?></span></td>
							<td><?php echo $t_trend_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="citewp-aiso-cs-table__time"><?php echo esc_html( $t_time_ago ); ?></td>
							<td>
								<?php if ( $t_issues > 0 ) : ?>
								<span class="citewp-aiso-cs-table__issues--active"><?php echo esc_html( $t_issues . ' ' . _n( 'issue', 'issues', $t_issues, 'ai-search-optimizer' ) ); ?></span>
								<?php else : ?>
								<span class="citewp-aiso-cs-table__issues--none"><?php esc_html_e( 'No issues', 'ai-search-optimizer' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $t_edit_url ) : ?>
								<a href="<?php echo esc_url( $t_edit_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Optimize →', 'ai-search-optimizer' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					</tbody>
				</table>

				<!-- Footer: View All Posts (left) | numbered pagination (center) | per-page select (right) -->
				<div class="citewp-aiso-cs-pagination">
					<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View All Posts →', 'ai-search-optimizer' ); ?></a>

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
							<option value="<?php echo esc_attr( (string) $pp ); ?>"<?php selected( $pp, $per_page ); ?>><?php echo esc_html( $pp . ' ' . __( 'per page', 'ai-search-optimizer' ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</form>
				</div>

				<?php else : ?>
				<div class="citewp-aiso-cs-empty">
					<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<h3 class="citewp-aiso-empty__title">
						<?php echo $search_q !== '' ? esc_html__( 'No posts match your search.', 'ai-search-optimizer' ) : esc_html__( 'No scored posts found.', 'ai-search-optimizer' ); ?>
					</h3>
				</div>
				<?php endif; ?>

			</div><!-- /.citewp-aiso-cs-table-wrap -->

			<!-- Cite Score Over Time — inside left column, below Post & Page table -->
			<div class="citewp-aiso-cs-panel citewp-aiso-cite-score-page__left-chart">
				<div class="citewp-aiso-cs-history-head">
					<h3 class="citewp-aiso-cs-panel__title">
						<?php esc_html_e( 'Cite Score Over Time', 'ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is recorded daily. The chart shows your average across the selected timeframe.', 'ai-search-optimizer' ); ?></span>
						</span>
					</h3>
					<form method="get" action="<?php echo esc_url( $base_url ); ?>" style="margin:0">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
						<input type="hidden" name="csp"  value="<?php echo esc_attr( (string) $paged ); ?>">
						<input type="hidden" name="cspp" value="<?php echo esc_attr( (string) $per_page ); ?>">
						<input type="hidden" name="css"  value="<?php echo esc_attr( $search_q ); ?>">
						<select name="cs_range" class="citewp-aiso-cs-perpage" onchange="this.form.submit()">
							<?php foreach ( [ 7 => __( 'Last 7 Days', 'ai-search-optimizer' ), 30 => __( 'Last 30 Days', 'ai-search-optimizer' ), 90 => __( 'Last 90 Days', 'ai-search-optimizer' ) ] as $days => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $days ); ?>"<?php selected( $days, $history_range ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</form>
				</div>
				<?php $this->render_history_svg( $history, $history_range, $bot_visits_by_day ); ?>
				<?php if ( $hist_avg !== null ) : ?>
				<div class="citewp-aiso-history-panel__stats">
					<div>
						<div class="citewp-aiso-history-panel__stat-label"><?php esc_html_e( 'Avg Score', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-history-panel__stat-value"><?php echo esc_html( (string) $hist_avg ); ?></div>
					</div>
					<div>
						<div class="citewp-aiso-history-panel__stat-label"><?php esc_html_e( 'Peak', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-history-panel__stat-value"><?php echo esc_html( (string) $hist_peak ); ?></div>
					</div>
				</div>
				<?php endif; ?>
			</div>

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
			// Pre-compute exact affected-post counts so button labels and card
			// visibility are accurate (uses full meta scan, not the 50-post sample).
			$displayable_recs = [];
			foreach ( $recs_display as $idx => $rec ) {
				$sig_id = $top_rec_ids[ $idx ] ?? '';
				if ( '' === $sig_id ) {
					continue;
				}
				$cnt = count( RecommendationFilter::get_affected_ids( $sig_id ) );
				if ( $cnt > 0 ) {
					$displayable_recs[] = [
						'rec'       => $rec,
						'signal_id' => $sig_id,
						'count'     => $cnt,
					];
				}
			}

			$recs_count  = count( array_filter( $recs_display, static fn( $r ) => isset( $r['label'] ) && $r['label'] !== __( 'Keep publishing', 'ai-search-optimizer' ) ) );
			$cs_recs_url = admin_url( 'edit.php' );
			?>
			<!-- Panel 3: AI Recommendations (uses citewp-aiso-insights class — same as Dashboard AI Insights) -->
			<div class="citewp-aiso-insights">
				<div class="citewp-aiso-insights__header">
					<span class="citewp-aiso-insights__title"><?php esc_html_e( 'AI Recommendations', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-insights__badge"><?php esc_html_e( 'BETA', 'ai-search-optimizer' ); ?></span>
				</div>
				<div class="citewp-aiso-insights__body">
					<div class="citewp-aiso-insights__nested">
						<div class="citewp-aiso-insights__nested-top">
							<div class="citewp-aiso-insights__orb"
								 style="width:64px;height:64px;border-radius:14px;background:rgba(20,184,166,0.08);color:var(--citewp-tint-teal);flex-shrink:0">
								<?php echo IconLibrary::icon( 'bot', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="citewp-aiso-insights__headline-wrap">
								<p class="citewp-aiso-insights__headline"><?php esc_html_e( 'Your content can rank higher in AI search results.', 'ai-search-optimizer' ); ?></p>
								<?php if ( $recs_count > 0 ) : ?>
								<p class="citewp-aiso-insights__sub">
									<?php
									printf(
										/* translators: %d: number of high-impact opportunities */
										esc_html__( 'We found %d high-impact opportunities to improve.', 'ai-search-optimizer' ),
										(int) $recs_count
									);
									?>
								</p>
								<?php else : ?>
								<p class="citewp-aiso-insights__sub"><?php esc_html_e( 'Your content is performing well. Keep publishing to improve further.', 'ai-search-optimizer' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="citewp-aiso-insights__nested-bottom">
							<?php if ( empty( $displayable_recs ) ) : ?>
							<p class="citewp-aiso-cs-rec-empty"><?php esc_html_e( 'No recommendations right now. Your content is well-optimized for AI citation.', 'ai-search-optimizer' ); ?></p>
							<?php else : ?>
							<?php foreach ( $displayable_recs as $item ) :
								$rec           = $item['rec'];
								$rec_signal_id = $item['signal_id'];
								$affected_cnt  = $item['count'];
								$cat_key       = $rec['category'] ?? '';
								$cat_orb       = $rec_cat_meta[ $cat_key ] ?? $rec_cat_meta[''];
								$dominant_type = RecommendationFilter::dominant_post_type( $rec_signal_id );
								$view_url      = add_query_arg(
									[
										'post_type'           => $dominant_type,
										'aiso_recommendation' => $rec_signal_id,
									],
									admin_url( 'edit.php' )
								);
								$btn_label = sprintf(
									/* translators: %d: number of affected posts */
									_n( 'View %d post', 'View %d posts', $affected_cnt, 'ai-search-optimizer' ),
									$affected_cnt
								);
							?>
							<div class="citewp-aiso-cs-rec-row">
								<div class="citewp-aiso-cs-rec-row__orb" style="background:<?php echo esc_attr( $cat_orb['bg'] ); ?>;color:<?php echo esc_attr( $cat_orb['color'] ); ?>">
									<?php echo IconLibrary::icon( $cat_orb['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="citewp-aiso-cs-rec-row__text">
									<div class="citewp-aiso-cs-rec-row__title">
										<?php echo esc_html( $rec['label'] . ' (' . $affected_cnt . ' ' . _n( 'page', 'pages', $affected_cnt, 'ai-search-optimizer' ) . ')' ); ?>
									</div>
									<div class="citewp-aiso-cs-rec-row__sub">
										<?php echo esc_html( $rec['copy'] ); ?>
										<?php if ( ! empty( $rec['anchor'] ) ) : ?>
											<a href="<?php echo esc_url( $cite_score_rubric_url . '#' . $rec['anchor'] ); ?>"
											   class="citewp-aiso-cs-rec-row__learn-more"
											   target="_blank"
											   rel="noopener">
												<?php esc_html_e( 'Learn More →', 'ai-search-optimizer' ); ?>
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
					    <?php esc_html_e( 'View All Recommendations →', 'ai-search-optimizer' ); ?>
					</a>
				</div>
			</div>


		</div><!-- /.citewp-aiso-cite-score-page__right -->

	</div><!-- /.citewp-aiso-cite-score-page__body -->

	<!-- Pro Tip — full width, outside two-column body -->
	<?php
	$cs_protip = apply_filters(
		'citewp_aiso/protip',
		__( 'Connect Google Search Console to get more insights and improve your Cite Score faster.', 'ai-search-optimizer' ),
		'cite-score'
	);
	?>
	<div class="citewp-aiso-protip">
		<div class="citewp-aiso-protip__left">
			<div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="citewp-aiso-protip__content">
				<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
				<p class="citewp-aiso-protip__body"><?php echo esc_html( $cs_protip ); ?></p>
			</div>
		</div>
		<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
			<?php esc_html_e( 'Connect Now →', 'ai-search-optimizer' ); ?>
		</a>
	</div>

	<?php endif; // total_scored === 0
	}

	/**
	 * @param array<int, array{date: string, avg: float}> $history
	 */
	/**
	 * @param array<int, array{date: string, avg: float}> $history     Sparse score history from ScoreHistory::get_history().
	 * @param int                                         $days        Chart window in days.
	 * @param array<int, array{date: string, sum: int}>   $bot_visits  Zero-filled per-day visit counts from DashboardData::get_visits_by_day().
	 */
	private function render_history_svg( array $history, int $days, array $bot_visits ): void {
		// ── Bot visits lookup and right Y-axis scale.
		$bv_lookup = [];
		foreach ( $bot_visits as $bv ) {
			$bv_lookup[ $bv['date'] ] = (int) $bv['sum'];
		}
		$bv_values = array_values( $bv_lookup );
		$max_bv    = count( $bv_values ) > 0 ? max( 1, max( $bv_values ) ) : 1;

		// ── Overlay score data onto the zero-filled bot-visits date series.
		$score_lookup = array_column( $history, 'avg', 'date' );
		$score_series = array_map(
			static fn( $bv ) => [
				'date' => $bv['date'],
				'avg'  => isset( $score_lookup[ $bv['date'] ] ) ? (float) $score_lookup[ $bv['date'] ] : null,
			],
			$bot_visits
		);

		$has_score_data = ! empty( $history );
		$has_bv_data    = count( $bv_values ) > 0 && max( $bv_values ) > 0;

		if ( ! $has_score_data && ! $has_bv_data ) {
			?>
			<div class="citewp-aiso-history-panel__empty">
				<svg viewBox="0 0 340 60" width="100%" height="60" aria-hidden="true">
					<line x1="0" y1="30" x2="340" y2="30" stroke="var(--citewp-border)" stroke-width="2" stroke-dasharray="6 4"/>
				</svg>
				<p class="citewp-aiso-history-panel__empty-text">
					<?php esc_html_e( 'Not enough history yet. Site Cite Score is recorded daily — check back tomorrow for your first data point.', 'ai-search-optimizer' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$w = 340;
		$h = 80;
		$n = count( $score_series );

		// ── Score path: M/L segments skip null gaps (no line through missing days).
		$score_path = '';
		$prev_null  = true;
		foreach ( $score_series as $i => $entry ) {
			if ( $entry['avg'] === null ) {
				$prev_null = true;
				continue;
			}
			$x           = $n > 1 ? (int) round( ( $i / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
			$y           = (int) round( $h - ( $entry['avg'] / 100.0 ) * ( $h * 0.8 ) - $h * 0.1 );
			$score_path .= ( $prev_null ? "M {$x} {$y}" : " L {$x} {$y}" );
			$prev_null   = false;
		}

		// ── Bot visits polyline (zero-filled, no gaps).
		$bv_pts = [];
		foreach ( $score_series as $i => $entry ) {
			$x        = $n > 1 ? (int) round( ( $i / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
			$visits   = $bv_lookup[ $entry['date'] ] ?? 0;
			$y        = (int) round( $h - ( (float) $visits / (float) $max_bv ) * ( $h * 0.8 ) - $h * 0.1 );
			$bv_pts[] = "{$x},{$y}";
		}
		$bv_poly = implode( ' ', $bv_pts );

		// ── Right Y-axis labels: compact K suffix for ≥ 1000.
		$fmt = static function ( int $v ): string {
			return $v >= 1000 ? round( $v / 1000, 1 ) . 'k' : (string) $v;
		};
		$bv_mid_val = (int) round( $max_bv / 2 );
		$bv_max_lbl = $fmt( $max_bv );
		$bv_mid_lbl = $fmt( $bv_mid_val );

		// ── X-axis step: based on zero-filled series length (= $days), not sparse data count.
		if ( $n <= 7 ) {
			$label_step = 1;
		} elseif ( $n <= 30 ) {
			$label_step = 5;
		} else {
			$label_step = 15;
		}
		?>
		<div class="citewp-aiso-chart-legend" aria-hidden="true">
			<span class="citewp-aiso-chart-legend__item citewp-aiso-chart-legend__item--score"><?php esc_html_e( 'Avg Score', 'ai-search-optimizer' ); ?></span>
			<span class="citewp-aiso-chart-legend__item citewp-aiso-chart-legend__item--bv"><?php esc_html_e( 'Bot Visits', 'ai-search-optimizer' ); ?></span>
		</div>
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
				<?php if ( ! empty( $bv_poly ) ) : ?>
				<polyline points="<?php echo esc_attr( $bv_poly ); ?>" fill="none"
					stroke="var(--citewp-tint-blue)" stroke-width="1.5"
					stroke-linejoin="round" stroke-linecap="round" opacity="0.7"/>
				<?php endif; ?>
				<?php if ( ! empty( $score_path ) ) : ?>
				<path d="<?php echo esc_attr( $score_path ); ?>" fill="none"
					stroke="var(--citewp-citrine)" stroke-width="2"
					stroke-linejoin="round" stroke-linecap="round"/>
				<?php endif; ?>
			</svg>
			<div class="citewp-aiso-cs-history-yaxis citewp-aiso-cs-history-yaxis--right" aria-hidden="true">
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:10%"><?php echo esc_html( $bv_max_lbl ); ?></span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:50%"><?php echo esc_html( $bv_mid_lbl ); ?></span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:90%">0</span>
			</div>
		</div>
		<div class="citewp-aiso-chart-xlabels">
			<?php
			foreach ( $score_series as $i => $entry ) :
				if ( $n > 1 && $i % $label_step !== 0 && $i !== $n - 1 ) {
					continue;
				}
				$left_pct  = $n > 1 ? round( $i / ( $n - 1 ) * 100, 2 ) : 50.0;
				$parts     = explode( '-', $entry['date'] );
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
