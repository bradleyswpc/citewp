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
		$data         = new DashboardData();
		$avg_score    = $data->get_average_score();
		$trend        = $data->get_visit_trend();
		$lowest_posts = $data->get_lowest_scoring_posts();
		$issue_count  = $data->get_issue_count();
		$top_crawlers = $data->get_top_crawlers( 5 );

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

		$llms_settings = get_option( 'citewp_aiso_llms_settings', [] );
		$llms_enabled  = ! empty( $llms_settings['enabled'] );

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
				<h2 class="citewp-aiso-hero__title"><?php echo esc_html( sprintf( __( 'Welcome back, %s 👋', 'ai-search-optimizer' ), $greeting_name ) ); ?></h2>
				<p class="citewp-aiso-hero__sub"><?php esc_html_e( "Here's how your site is performing in AI search.", 'ai-search-optimizer' ); ?></p>
				<div class="citewp-aiso-hero__filters">
					<button class="citewp-aiso-hero__filter is-active"><?php esc_html_e( '7 Days', 'ai-search-optimizer' ); ?></button>
					<button class="citewp-aiso-hero__filter"><?php esc_html_e( '30 Days', 'ai-search-optimizer' ); ?></button>
					<button class="citewp-aiso-hero__filter"><?php esc_html_e( 'All Time', 'ai-search-optimizer' ); ?></button>
				</div>
			</div>
			<div class="citewp-aiso-hero__stats">
				<div class="citewp-aiso-hero__stat">
					<div class="citewp-aiso-hero__stat-head">
						<span style="color:var(--citewp-tint-purple)"><?php echo IconLibrary::icon( 'cite-score', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></span>
						<span class="citewp-aiso-hero__stat-label"><?php esc_html_e( 'Avg Cite Score', 'ai-search-optimizer' ); ?></span>
					</div>
					<span class="citewp-aiso-hero__stat-value"><?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?></span>
					<span class="citewp-aiso-hero__stat-sub"><?php esc_html_e( 'across scored posts', 'ai-search-optimizer' ); ?></span>
				</div>
				<div class="citewp-aiso-hero__stat">
					<div class="citewp-aiso-hero__stat-head">
						<span style="color:var(--citewp-tint-teal)"><?php echo IconLibrary::icon( 'bot', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></span>
						<span class="citewp-aiso-hero__stat-label"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></span>
					</div>
					<span class="citewp-aiso-hero__stat-value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></span>
					<span class="citewp-aiso-hero__stat-sub">
						<?php
						if ( $trend_pct > 5 ) {
							echo '<span class="citewp-aiso-hero__stat-trend citewp-aiso-hero__stat-trend--up">↑ ' . esc_html( (string) absint( $trend_pct ) ) . '%</span> ';
						} elseif ( $trend_pct < -5 ) {
							echo '<span class="citewp-aiso-hero__stat-trend citewp-aiso-hero__stat-trend--down">↓ ' . esc_html( (string) absint( $trend_pct ) ) . '%</span> ';
						}
						esc_html_e( 'vs last week', 'ai-search-optimizer' );
						?>
					</span>
				</div>
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
		<div class="citewp-aiso-kpi-row">

			<!-- Card 1: Avg Cite Score -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
						<?php echo IconLibrary::icon( 'cite-score', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Avg Cite Score', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Average score across all scored posts and pages.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
						<?php echo IconLibrary::icon( 'cite-score', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $avg_grade ); ?>"><?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?></div>
						<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Across all scored posts', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <?php esc_html_e( 'no recent changes', 'ai-search-optimizer' ); ?></div>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#cite-score" class="citewp-aiso-btn"><?php esc_html_e( 'View Scores →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>

			<!-- Card 2: Bot Visits -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-teal-tint);color:var(--citewp-tint-teal)">
						<?php echo IconLibrary::icon( 'bot', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'AI crawler visits to your site over the last 7 days.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-teal-tint);color:var(--citewp-tint-teal)">
						<?php echo IconLibrary::icon( 'bot', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></div>
						<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'AI crawler visits this week', 'ai-search-optimizer' ); ?></div>
						<?php if ( $trend_pct > 5 ) : ?>
							<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--up">↑ <?php echo esc_html( (string) absint( $trend_pct ) ); ?>% <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'vs last week', 'ai-search-optimizer' ); ?></span></div>
						<?php elseif ( $trend_pct < -5 ) : ?>
							<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--down">↓ <?php echo esc_html( (string) absint( $trend_pct ) ); ?>% <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'vs last week', 'ai-search-optimizer' ); ?></span></div>
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
					<div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
						<?php echo IconLibrary::icon( 'check-circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Indexed Pages', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages currently published and scoreable.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
						<?php echo IconLibrary::icon( 'check-circle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
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

			<!-- Card 4: llms.txt Status -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-blue-tint);color:var(--citewp-tint-blue)">
						<?php echo IconLibrary::icon( 'llms-txt', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'llms.txt', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Status of the AI-readable content index served at /llms.txt.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-blue-tint);color:var(--citewp-tint-blue)">
						<?php echo IconLibrary::icon( 'llms-txt', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?>
					</div>
					<div class="citewp-aiso-kpi-card__data">
						<div class="citewp-aiso-kpi-card__value" style="color:<?php echo $llms_enabled ? 'var(--citewp-tint-green)' : 'var(--citewp-text-muted)'; ?>">
							<?php echo $llms_enabled ? esc_html__( 'Active', 'ai-search-optimizer' ) : esc_html__( 'Off', 'ai-search-optimizer' ); ?>
						</div>
						<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'AI-readable content index', 'ai-search-optimizer' ); ?></div>
						<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <?php esc_html_e( 'no recent changes', 'ai-search-optimizer' ); ?></div>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#settings" class="citewp-aiso-btn"><?php esc_html_e( 'Configure →', 'ai-search-optimizer' ); ?></a>
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
									<a href="#cite-score" class="citewp-aiso-btn citewp-aiso-btn--primary-paper"><?php esc_html_e( 'View Recommendations →', 'ai-search-optimizer' ); ?></a>
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

		// ── All scored post IDs ─────────────────────────────────────────
		$scored_ids   = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1000,
			'meta_key'       => Repository::META_KEY_TOTAL,
			'meta_compare'   => 'EXISTS',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );
		$total_scored = count( $scored_ids );

		// ── Site-wide stats (sample first 50 for signal analysis) ───────
		$score_sum    = 0;
		$issue_count  = 0;
		$cat_sums     = [ 'structure' => 0, 'citability' => 0, 'authority' => 0 ];
		$signal_fails = [];
		$sample_cap   = 50;

		foreach ( $scored_ids as $i => $pid ) {
			$total      = (int) get_post_meta( (int) $pid, Repository::META_KEY_TOTAL, true );
			$score_sum += $total;
			if ( $total < 50 ) {
				++$issue_count;
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

		$avg_score = $total_scored > 0 ? (int) round( $score_sum / $total_scored ) : null;
		$avg_grade = 'empty';
		if ( $avg_score !== null ) {
			$avg_grade = match ( true ) {
				$avg_score >= 80 => 'green',
				$avg_score >= 60 => 'yellow',
				$avg_score >= 40 => 'orange',
				default          => 'red',
			};
		}

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
		$history_range = absint( $_GET['cs_range'] ?? 30 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$history_range = in_array( $history_range, [ 7, 30, 90 ], true ) ? $history_range : 30;
		$history       = ( new ScoreHistory() )->get_history( $history_range );
		$hist_avg      = ! empty( $history ) ? (int) round( array_sum( array_column( $history, 'avg' ) ) / count( $history ) ) : null;
		$hist_peak     = ! empty( $history ) ? (int) round( (float) max( array_column( $history, 'avg' ) ) ) : null;

		// ── Paginated post table ─────────────────────────────────────────
		$paged     = max( 1, absint( $_GET['csp'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page  = in_array( (int) ( $_GET['cspp'] ?? 10 ), [ 5, 10, 25 ], true ) ? (int) ( $_GET['cspp'] ?? 10 ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_q  = sanitize_text_field( wp_unslash( $_GET['css'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tbl_args  = [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'meta_value_num',
			'meta_key'       => Repository::META_KEY_TOTAL,
			'order'          => 'ASC',
			'meta_query'     => [ [ 'key' => Repository::META_KEY_TOTAL, 'compare' => 'EXISTS' ] ],
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

		<!-- Row 1: page title + KPI cards in single horizontal band -->
		<div class="citewp-aiso-cs-header-band">
			<div class="citewp-aiso-cs-header-band__left">
				<h2><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'ai-search-optimizer' ); ?></p>
			</div>

			<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">

				<!-- Card 1: Average Cite Score -->
				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
							<?php echo IconLibrary::icon( 'gauge', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Average Cite Score', 'ai-search-optimizer' ); ?></span>
						<span class="citewp-aiso-kpi-tooltip">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Average Cite Score across all scored posts and pages on this site.', 'ai-search-optimizer' ); ?></span>
						</span>
					</div>
					<div class="citewp-aiso-kpi-card__body">
						<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
							<?php echo IconLibrary::icon( 'gauge', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="citewp-aiso-kpi-card__data">
							<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $avg_grade ); ?>"><?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?></div>
							<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'site-wide average', 'ai-search-optimizer' ); ?></div>
						</div>
					</div>
				</div>

				<!-- Card 2: Posts Optimized -->
				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
							<?php echo IconLibrary::icon( 'check-circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Posts Optimized', 'ai-search-optimizer' ); ?></span>
						<span class="citewp-aiso-kpi-tooltip">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages with a Cite Score of 50 or above.', 'ai-search-optimizer' ); ?></span>
						</span>
					</div>
					<div class="citewp-aiso-kpi-card__body">
						<div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
							<?php echo IconLibrary::icon( 'check-circle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="citewp-aiso-kpi-card__data">
							<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $posts_optimized ); ?></div>
							<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'score ≥ 50', 'ai-search-optimizer' ); ?></div>
						</div>
					</div>
				</div>

				<!-- Card 3: Issues Detected -->
				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb" style="background:rgba(249,115,22,0.12);color:var(--citewp-tint-orange)">
							<?php echo IconLibrary::icon( 'alert-triangle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Issues Detected', 'ai-search-optimizer' ); ?></span>
						<span class="citewp-aiso-kpi-tooltip">
							<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages with a Cite Score below 50 — they have the most room for improvement.', 'ai-search-optimizer' ); ?></span>
						</span>
					</div>
					<div class="citewp-aiso-kpi-card__body">
						<div class="citewp-aiso-kpi-card__visual" style="background:rgba(249,115,22,0.12);color:var(--citewp-tint-orange)">
							<?php echo IconLibrary::icon( 'alert-triangle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="citewp-aiso-kpi-card__data">
							<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $issue_count ); ?></div>
							<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'score < 50', 'ai-search-optimizer' ); ?></div>
						</div>
					</div>
				</div>

			</div><!-- /.citewp-aiso-kpi-row -->
		</div><!-- /.citewp-aiso-cs-header-band -->

		<?php if ( 0 === $total_scored ) : ?>
		<!-- Empty state -->
		<div class="citewp-aiso-cs-empty">
			<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored content yet.', 'ai-search-optimizer' ); ?></h3>
			<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Open and save any post or page to generate your first Cite Score.', 'ai-search-optimizer' ); ?></p>
		</div>
		<?php else : ?>

		<!-- Row 2: 3-column top grid -->
		<div class="citewp-aiso-cs-top-grid">

			<!-- Panel 1: Cite Score Health (gauge) -->
			<div class="citewp-aiso-cs-panel">
				<h3 class="citewp-aiso-cs-panel__title">
					<?php esc_html_e( 'Cite Score Health', 'ai-search-optimizer' ); ?>
					<span class="citewp-aiso-kpi-tooltip">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is the average across all scored posts. Higher scores mean better AI citation potential.', 'ai-search-optimizer' ); ?></span>
					</span>
				</h3>
				<?php $this->render_gauge_svg( $avg_score ?? 0, $avg_grade ); ?>
				<p class="citewp-cite-score-gauge__meta">
					<?php
					$published_total = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;
					printf(
						/* translators: %1$d: scored posts, %2$d: total published */
						esc_html__( '%1$d of %2$d posts scored', 'ai-search-optimizer' ),
						(int) $total_scored,
						(int) $published_total
					);
					?>
				</p>
			</div>

			<!-- Panel 2: Score Breakdown -->
			<div class="citewp-aiso-breakdown">
				<div class="citewp-aiso-breakdown__head">
					<?php esc_html_e( 'Score Breakdown', 'ai-search-optimizer' ); ?>
					<span class="citewp-aiso-kpi-tooltip">
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

			<?php
			// Category → orb tint + icon mapping for rec rows
			$rec_cat_meta = [
				'structure'  => [ 'bg' => 'rgba(124,58,237,0.12)', 'color' => 'var(--citewp-tint-purple)', 'icon' => 'layout'   ],
				'citability' => [ 'bg' => 'rgba(37,99,235,0.12)',   'color' => 'var(--citewp-tint-blue)',   'icon' => 'quote'    ],
				'authority'  => [ 'bg' => 'rgba(20,184,166,0.12)',  'color' => 'var(--citewp-tint-teal)',   'icon' => 'shield'   ],
				''           => [ 'bg' => 'rgba(100,116,139,0.12)', 'color' => 'var(--citewp-text-muted)',  'icon' => 'sparkles' ],
			];
			$recs_count = count( array_filter( $recs_display, static fn( $r ) => isset( $r['label'] ) && $r['label'] !== __( 'Keep publishing', 'ai-search-optimizer' ) ) );
			$cs_recs_url = admin_url( 'edit.php' );
			?>
			<!-- Panel 3: AI Recommendations (uses citewp-aiso-insights class — same as Dashboard AI Insights) -->
			<div class="citewp-aiso-insights">
				<div class="citewp-aiso-insights__header">
					<span class="citewp-aiso-insights__title"><?php esc_html_e( 'AI Recommendations', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-insights__badge"><?php esc_html_e( 'BETA', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-kpi-tooltip">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Recommendations are derived from the most common failed signals across your scored posts. Fixing these has the highest impact on your overall Cite Score.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-insights__body">
					<div class="citewp-aiso-insights__nested">
						<div class="citewp-aiso-insights__nested-top">
							<div class="citewp-aiso-insights__orb">
								<?php echo IconLibrary::icon( 'sparkles', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
							<?php foreach ( $recs_display as $idx => $rec ) :
								$rec_signal_id = $top_rec_ids[ $idx ] ?? '';
								$fail_count    = $signal_fails[ $rec_signal_id ] ?? 0;
								$cat_key       = $rec['category'] ?? '';
								$cat_orb       = $rec_cat_meta[ $cat_key ] ?? $rec_cat_meta[''];
								$view_url      = admin_url( 'edit.php' );
							?>
							<div class="citewp-aiso-cs-rec-row">
								<div class="citewp-aiso-cs-rec-row__orb" style="background:<?php echo esc_attr( $cat_orb['bg'] ); ?>;color:<?php echo esc_attr( $cat_orb['color'] ); ?>">
									<?php echo IconLibrary::icon( $cat_orb['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="citewp-aiso-cs-rec-row__text">
									<div class="citewp-aiso-cs-rec-row__title">
										<?php echo esc_html( $rec['label'] );
										if ( $fail_count > 0 ) {
											echo esc_html( ' (' . $fail_count . ' ' . _n( 'page', 'pages', $fail_count, 'ai-search-optimizer' ) . ')' );
										} ?>
									</div>
									<div class="citewp-aiso-cs-rec-row__sub"><?php echo esc_html( $rec['copy'] ); ?></div>
								</div>
								<a href="<?php echo esc_url( $view_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Pages', 'ai-search-optimizer' ); ?></a>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
					<a href="<?php echo esc_url( $cs_recs_url ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View All Recommendations →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>

		</div><!-- /.citewp-aiso-cs-top-grid -->

		<!-- Row 3: 2-column lower grid -->
		<div class="citewp-aiso-cs-lower-grid">

			<!-- Lower-left: Post-level score table -->
			<div class="citewp-aiso-cs-table-wrap">

				<!-- Panel head: title + tooltip + search -->
				<div class="citewp-aiso-cs-table-head">
					<span class="citewp-aiso-cs-table-head__title">
						<?php esc_html_e( 'Post-Level Cite Scores', 'ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip">
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
							<th><?php esc_html_e( 'Post',         'ai-search-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Cite Score',   'ai-search-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Trend',        'ai-search-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Last Updated', 'ai-search-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Issues',       'ai-search-optimizer' ); ?></th>
							<th></th>
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
							$t_full_raw  = get_post_meta( $t_id, '_citewp_aiso_geo_score', true );
							$t_issues    = 0;
							if ( $t_full_raw !== '' ) {
								$t_full = maybe_unserialize( $t_full_raw );
								if ( is_object( $t_full ) && isset( $t_full->signals ) && is_array( $t_full->signals ) ) {
									foreach ( $t_full->signals as $sig ) {
										if ( isset( $sig->status ) && $sig->status === 'fail' ) {
											$t_issues++;
										}
									}
								}
							}
							// Trend: no per-post history available yet → show "—"
							$t_trend_html = '<span class="citewp-aiso-cs-table__trend--flat">—</span>';
						?>
						<tr>
							<td>
								<span style="display:inline-flex;align-items:center;gap:4px">
									<span style="color:var(--citewp-text-muted);display:inline-flex"><?php echo IconLibrary::icon( $t_type_icon, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php if ( $t_edit_url ) : ?>
									<a href="<?php echo esc_url( $t_edit_url ); ?>"><?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?></a>
									<?php else : ?>
									<?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?>
									<?php endif; ?>
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

			<!-- Lower-right: Cite Score Over Time -->
			<div class="citewp-aiso-cs-panel">
				<div class="citewp-aiso-cs-history-head">
					<h3 class="citewp-aiso-cs-panel__title">
						<?php esc_html_e( 'Cite Score Over Time', 'ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip">
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
				<?php $this->render_history_svg( $history ); ?>
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

		</div><!-- /.citewp-aiso-cs-lower-grid -->

		<!-- Row 4: Pro Tip footer -->
		<div class="citewp-aiso-protip">
			<div class="citewp-aiso-protip__left">
				<div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="citewp-aiso-protip__content">
					<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
					<p class="citewp-aiso-protip__body"><?php esc_html_e( 'Adding FAQ schema to your top posts is the fastest way to raise your site-wide Cite Score.', 'ai-search-optimizer' ); ?></p>
				</div>
			</div>
			<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
				<?php esc_html_e( 'Learn More →', 'ai-search-optimizer' ); ?>
			</a>
		</div>

		<?php endif; // total_scored === 0
	}

	private function render_gauge_svg( int $score, string $grade ): void {
		$score        = max( 0, min( 100, $score ) );
		$grade_labels = [
			'green'  => __( 'Excellent',         'ai-search-optimizer' ),
			'yellow' => __( 'Good',              'ai-search-optimizer' ),
			'orange' => __( 'Fair',              'ai-search-optimizer' ),
			'red'    => __( 'Needs Improvement', 'ai-search-optimizer' ),
			'empty'  => __( 'No data',           'ai-search-optimizer' ),
		];
		$grade_label = $grade_labels[ $grade ] ?? '';
		?>
		<div class="citewp-cite-score-gauge citewp-cite-score-gauge--<?php echo esc_attr( $grade ); ?>"
		     style="--score:<?php echo esc_attr( (string) $score ); ?>">
			<svg viewBox="0 0 240 140" role="img"
			     aria-label="<?php echo esc_attr( sprintf( /* translators: 1: score integer 0-100, 2: grade label e.g. "Excellent" */ __( 'Cite Score %1$d out of 100, %2$s', 'ai-search-optimizer' ), $score, $grade_label ) ); ?>">
				<defs>
					<linearGradient id="citewp-gauge-gradient" x1="30" y1="120" x2="210" y2="120" gradientUnits="userSpaceOnUse">
						<stop offset="0%"   stop-color="#ef4444" />
						<stop offset="45%"  stop-color="#f7d84a" />
						<stop offset="100%" stop-color="#16a34a" />
					</linearGradient>
				</defs>
				<path class="gauge-bg" d="M 30 120 A 90 90 0 0 1 210 120" pathLength="100" />
				<path class="gauge-score" d="M 30 120 A 90 90 0 0 1 210 120" pathLength="100" />
				<text x="120" y="88" text-anchor="middle" class="gauge-number">
					<?php echo esc_html( $score > 0 ? (string) $score : '—' ); ?>
				</text>
				<text x="120" y="112" text-anchor="middle" class="gauge-total">/100</text>
				<text x="120" y="132" text-anchor="middle" class="gauge-label">
					<?php echo esc_html( $grade_label ); ?>
				</text>
			</svg>
		</div>
		<?php
	}

	/**
	 * @param array<int, array{date: string, avg: float}> $history
	 */
	private function render_history_svg( array $history ): void {
		if ( empty( $history ) ) {
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

		$w      = 340;
		$h      = 80;
		$n      = count( $history );
		$scores = array_column( $history, 'avg' );
		$min_s  = (float) min( $scores );
		$max_s  = (float) max( $scores );
		$rng    = max( 1.0, $max_s - $min_s );

		$pts = [];
		foreach ( $history as $i => $entry ) {
			$x     = $n > 1 ? (int) round( ( $i / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
			$y     = (int) round( $h - ( ( (float) $entry['avg'] - $min_s ) / $rng ) * ( $h * 0.8 ) - $h * 0.1 );
			$pts[] = [ 'x' => $x, 'y' => $y ];
		}

		$poly = implode( ' ', array_map( static fn( $p ) => "{$p['x']},{$p['y']}", $pts ) );
		$last = end( $pts );
		$frst = reset( $pts );
		$area = 'M ' . implode( ' L ', array_map( static fn( $p ) => "{$p['x']} {$p['y']}", $pts ) )
		        . " L {$last['x']} {$h} L {$frst['x']} {$h} Z";
		?>
		<svg viewBox="0 0 <?php echo esc_attr( (string) $w ); ?> <?php echo esc_attr( (string) $h ); ?>"
			width="100%" height="<?php echo esc_attr( (string) $h ); ?>" aria-hidden="true">
			<path d="<?php echo esc_attr( $area ); ?>" fill="rgba(232,212,0,0.08)"/>
			<polyline points="<?php echo esc_attr( $poly ); ?>" fill="none"
				stroke="var(--citewp-citrine)" stroke-width="2"
				stroke-linejoin="round" stroke-linecap="round"/>
			<?php foreach ( $pts as $pt ) : ?>
			<circle cx="<?php echo esc_attr( (string) $pt['x'] ); ?>" cy="<?php echo esc_attr( (string) $pt['y'] ); ?>"
				r="3" fill="var(--citewp-citrine)"/>
			<?php endforeach; ?>
		</svg>
		<?php
	}
}
