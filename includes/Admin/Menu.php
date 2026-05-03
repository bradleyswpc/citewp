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

		// Resolve which post to show.
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display param; no data modification.

		if ( ! $post_id ) {
			// Default: most recently scored post.
			$recent = get_posts( [
				'meta_key'    => Repository::META_KEY_TIME,
				'orderby'     => 'meta_value',
				'order'       => 'DESC',
				'numberposts' => 1,
				'post_type'   => [ 'post', 'page' ],
				'post_status' => [ 'publish', 'draft' ],
			] );
			$post_id = ! empty( $recent ) ? (int) $recent[0]->ID : 0;
		}

		// All scored posts for the picker dropdown.
		$scored_posts = get_posts( [
			'meta_key'     => Repository::META_KEY_TOTAL,
			'meta_compare' => 'EXISTS',
			'orderby'      => 'meta_value_num',
			'order'        => 'ASC',
			'numberposts'  => 100,
			'post_type'    => [ 'post', 'page' ],
			'post_status'  => [ 'publish', 'draft' ],
		] );

		$score_data = $post_id ? ( new Repository() )->get( $post_id ) : null;
		$post       = $post_id ? get_post( $post_id ) : null;

		?>
		<!-- Page header strip -->
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Optimize your content for AI citation.', 'ai-search-optimizer' ); ?></p>
			</div>
			<?php if ( ! empty( $scored_posts ) ) : ?>
			<div class="citewp-aiso-page-header__actions">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
					<select
						name="post_id"
						class="citewp-aiso-cs-picker"
						onchange="this.form.submit()"
						aria-label="<?php esc_attr_e( 'Select post', 'ai-search-optimizer' ); ?>"
					>
						<?php foreach ( $scored_posts as $sp ) : ?>
							<option value="<?php echo esc_attr( (string) $sp->ID ); ?>"<?php selected( $sp->ID, $post_id ); ?>>
								<?php echo esc_html( get_the_title( $sp ) ?: __( '(no title)', 'ai-search-optimizer' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( ! $score_data || ! $post ) : ?>
		<!-- Empty state: no scored posts yet -->
		<div class="citewp-aiso-cs-empty">
			<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
			<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored content yet.', 'ai-search-optimizer' ); ?></h3>
			<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Publish or save a post to generate your first Cite Score.', 'ai-search-optimizer' ); ?></p>
		</div>
		<?php return; endif;

		$total      = (int) $score_data['total'];
		$grade      = sanitize_key( $score_data['grade'] );
		$categories = $score_data['categories'];
		$signals    = $score_data['signals'];
		$edit_url   = get_edit_post_link( $post->ID );

		$grade_colors = [
			'green'  => 'var(--citewp-score-green)',
			'yellow' => 'var(--citewp-score-yellow)',
			'orange' => 'var(--citewp-score-orange)',
			'red'    => 'var(--citewp-score-red)',
		];
		$score_color = $grade_colors[ $grade ] ?? 'var(--citewp-score-red)';

		$cat_color = static function( int $score, int $max ): string {
			$pct = $max > 0 ? ( $score / $max ) * 100 : 0;
			if ( $pct >= 80 ) { return 'var(--citewp-score-green)'; }
			if ( $pct >= 60 ) { return 'var(--citewp-score-yellow)'; }
			if ( $pct >= 40 ) { return 'var(--citewp-score-orange)'; }
			return 'var(--citewp-score-red)';
		};
		?>

		<!-- Two-column body -->
		<div class="citewp-aiso-cs-body">

			<!-- Left column: donut + history -->
			<div class="citewp-aiso-cs-left">

				<!-- Donut Chart Panel -->
				<div class="citewp-aiso-donut-panel">
					<div class="citewp-aiso-donut-panel__chart">
						<?php $this->render_donut_svg( $total, $grade, $categories, $score_color, $cat_color ); ?>
					</div>
					<div class="citewp-aiso-donut-panel__cats">
						<?php foreach ( $categories as $cat ) :
							$c_score = (int) $cat['score'];
							$c_max   = (int) $cat['max'];
							$c_color = $cat_color( $c_score, $c_max );
						?>
						<div class="citewp-aiso-donut-panel__cat">
							<span class="citewp-aiso-donut-panel__cat-dot" style="background:<?php echo esc_attr( $c_color ); ?>"></span>
							<span class="citewp-aiso-donut-panel__cat-name"><?php echo esc_html( $cat['label'] ); ?></span>
							<span class="citewp-aiso-donut-panel__cat-score" style="color:<?php echo esc_attr( $c_color ); ?>"><?php echo esc_html( (string) $c_score ); ?></span>
							<span class="citewp-aiso-donut-panel__cat-max">/ <?php echo esc_html( (string) $c_max ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php if ( $edit_url ) : ?>
					<div class="citewp-aiso-donut-panel__footer">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-donut-panel__link">
							<?php esc_html_e( 'Improve this post', 'ai-search-optimizer' ); ?>
						</a>
					</div>
					<?php endif; ?>
				</div>

				<!-- Score History Panel (empty state — history collection not yet implemented) -->
				<div class="citewp-aiso-history-panel">
					<div class="citewp-aiso-history-panel__head">
						<span class="citewp-aiso-history-panel__title"><?php esc_html_e( 'Score History', 'ai-search-optimizer' ); ?></span>
						<div class="citewp-aiso-history-panel__pills">
							<button class="citewp-aiso-history-pill is-active" disabled>7D</button>
							<button class="citewp-aiso-history-pill" disabled>30D</button>
							<button class="citewp-aiso-history-pill" disabled>90D</button>
						</div>
					</div>
					<div class="citewp-aiso-history-panel__empty">
						<div class="citewp-aiso-history-panel__empty-line"></div>
						<p class="citewp-aiso-history-panel__empty-text">
							<?php esc_html_e( 'Not enough history yet. Scores appear after multiple saves.', 'ai-search-optimizer' ); ?>
						</p>
					</div>
				</div>

			</div><!-- /.citewp-aiso-cs-left -->

			<!-- Right column: signal breakdown -->
			<div class="citewp-aiso-signals">
				<?php
				$groups = [
					'structure'  => __( 'Structure',  'ai-search-optimizer' ),
					'citability' => __( 'Citability', 'ai-search-optimizer' ),
					'authority'  => __( 'Authority',  'ai-search-optimizer' ),
				];
				foreach ( $groups as $cat_key => $cat_label ) :
					$cat_signals = array_filter( $signals, static fn( $s ) => $s['category'] === $cat_key );
					if ( empty( $cat_signals ) ) { continue; }
					$cat_score = (int) $categories[ $cat_key ]['score'];
					$cat_max   = (int) $categories[ $cat_key ]['max'];
				?>
				<div class="citewp-aiso-signals__cat-head">
					<span class="citewp-aiso-signals__cat-label"><?php echo esc_html( $cat_label ); ?></span>
					<span class="citewp-aiso-signals__cat-score"><?php echo esc_html( $cat_score . ' / ' . $cat_max ); ?></span>
				</div>
				<?php foreach ( $cat_signals as $sig ) :
					$status = sanitize_key( $sig['status'] );
					$badge_labels = [ 'pass' => 'Pass', 'partial' => 'Partial', 'fail' => 'Fail' ];
					$badge_label  = $badge_labels[ $status ] ?? $status;
				?>
				<div class="citewp-aiso-signal-row">
					<div class="citewp-aiso-signal-row__top">
						<span class="citewp-aiso-signal-row__label"><?php echo esc_html( $sig['label'] ); ?></span>
						<span class="citewp-aiso-signal-row__pts"><?php echo esc_html( $sig['score'] . ' / ' . $sig['max'] ); ?></span>
						<span class="citewp-aiso-signal-badge citewp-aiso-signal-badge--<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( $badge_label ); ?>
						</span>
					</div>
					<?php if ( ! empty( $sig['message'] ) ) : ?>
					<p class="citewp-aiso-signal-row__msg"><?php echo esc_html( $sig['message'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $sig['recommendation'] ) && in_array( $status, [ 'partial', 'fail' ], true ) ) : ?>
					<p class="citewp-aiso-signal-row__rec"><?php echo esc_html( $sig['recommendation'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
				<?php endforeach; ?>
			</div><!-- /.citewp-aiso-signals -->

		</div><!-- /.citewp-aiso-cs-body -->

		<!-- Pro Tip footer -->
		<div class="citewp-aiso-protip">
			<div class="citewp-aiso-protip__left">
				<div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
				<div class="citewp-aiso-protip__content">
					<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
					<p class="citewp-aiso-protip__body"><?php esc_html_e( 'Adding a FAQ section to your post can boost your Structure score by up to 10 points.', 'ai-search-optimizer' ); ?></p>
				</div>
			</div>
			<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
				<?php esc_html_e( 'Connect Now →', 'ai-search-optimizer' ); ?>
			</a>
		</div>
		<?php
	}

	private function render_donut_svg(
		int $total,
		string $grade,
		array $categories,
		string $score_color,
		callable $cat_color
	): void {
		$cx       = 110;
		$cy       = 110;
		$r_inner  = 52;
		$r_outer  = 76;
		$sw_inner = 14;
		$sw_outer = 10;

		$circ_inner = 2 * M_PI * $r_inner;
		$circ_outer = 2 * M_PI * $r_outer;

		$inner_fill = round( ( $total / 100 ) * $circ_inner, 2 );
		$inner_gap  = round( $circ_inner - $inner_fill, 2 );

		$cat_order  = [ 'structure', 'citability', 'authority' ];
		$gap_px     = 4.0;
		$outer_segs = [];
		$offset     = 0.0;
		foreach ( $cat_order as $key ) {
			if ( ! isset( $categories[ $key ] ) ) { continue; }
			$cat = $categories[ $key ];
			$len = round( ( (int) $cat['score'] / 100 ) * $circ_outer, 2 );
			$outer_segs[] = [
				'len'    => $len,
				'color'  => $cat_color( (int) $cat['score'], (int) $cat['max'] ),
				'offset' => round( $circ_outer - $offset, 2 ),
			];
			$offset += $len + $gap_px;
		}

		$rotate = 'rotate(-90 ' . $cx . ' ' . $cy . ')';
		?>
		<svg viewBox="0 0 220 220" width="200" height="200" aria-hidden="true" focusable="false">
			<circle cx="<?php echo esc_attr( (string) $cx ); ?>" cy="<?php echo esc_attr( (string) $cy ); ?>" r="<?php echo esc_attr( (string) $r_inner ); ?>" fill="none" stroke="var(--citewp-paper-tinted)" stroke-width="<?php echo esc_attr( (string) $sw_inner ); ?>"/>
			<circle cx="<?php echo esc_attr( (string) $cx ); ?>" cy="<?php echo esc_attr( (string) $cy ); ?>" r="<?php echo esc_attr( (string) $r_inner ); ?>" fill="none" stroke="var(--citewp-citrine)" stroke-width="<?php echo esc_attr( (string) $sw_inner ); ?>" stroke-linecap="round" stroke-dasharray="<?php echo esc_attr( $inner_fill . ' ' . $inner_gap ); ?>" transform="<?php echo esc_attr( $rotate ); ?>"/>
			<circle cx="<?php echo esc_attr( (string) $cx ); ?>" cy="<?php echo esc_attr( (string) $cy ); ?>" r="<?php echo esc_attr( (string) $r_outer ); ?>" fill="none" stroke="var(--citewp-paper-tinted)" stroke-width="<?php echo esc_attr( (string) $sw_outer ); ?>"/>
			<?php foreach ( $outer_segs as $seg ) : ?>
			<circle cx="<?php echo esc_attr( (string) $cx ); ?>" cy="<?php echo esc_attr( (string) $cy ); ?>" r="<?php echo esc_attr( (string) $r_outer ); ?>" fill="none" stroke="<?php echo esc_attr( $seg['color'] ); ?>" stroke-width="<?php echo esc_attr( (string) $sw_outer ); ?>" stroke-linecap="round" stroke-dasharray="<?php echo esc_attr( $seg['len'] . ' ' . $circ_outer ); ?>" stroke-dashoffset="<?php echo esc_attr( (string) $seg['offset'] ); ?>" transform="<?php echo esc_attr( $rotate ); ?>"/>
			<?php endforeach; ?>
			<text x="<?php echo esc_attr( (string) $cx ); ?>" y="<?php echo esc_attr( (string) ( $cy - 6 ) ); ?>" text-anchor="middle" dominant-baseline="middle" font-family="'JetBrains Mono', monospace" font-size="32" font-weight="800" fill="<?php echo esc_attr( $score_color ); ?>"><?php echo esc_html( (string) $total ); ?></text>
			<text x="<?php echo esc_attr( (string) $cx ); ?>" y="<?php echo esc_attr( (string) ( $cy + 18 ) ); ?>" text-anchor="middle" dominant-baseline="middle" font-family="'Inter', sans-serif" font-size="10" font-weight="700" fill="var(--citewp-text-muted)" letter-spacing="0.06em">CITE SCORE</text>
		</svg>
		<span class="screen-reader-text">
			<?php
			/* translators: %1$d: score total, %2$s: grade band */
			printf(
				esc_html__( 'Cite Score: %1$d out of 100, %2$s band', 'ai-search-optimizer' ),
				$total,
				$grade
			);
			?>
		</span>
		<?php
	}
}
