<?php
/**
 * Admin menu registration.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

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
				'label'  => __( 'Dashboard', 'ai-search-optimizer' ),
				'desc'   => __( 'Overview and metrics', 'ai-search-optimizer' ),
				'icon'   => 'dashicons-chart-line',
				'slug'   => 'dashboard',
				'render' => [ $this, 'render_dashboard_panel' ],
			],
			'crawler-logs' => [
				'label'  => __( 'Crawler Logs', 'ai-search-optimizer' ),
				'desc'   => __( 'AI bot visit history', 'ai-search-optimizer' ),
				'icon'   => 'dashicons-list-view',
				'slug'   => 'crawler-logs',
				'render' => $logs_module ? [ $logs_module, 'render' ] : null,
			],
			'settings' => [
				'label'  => __( 'Settings', 'ai-search-optimizer' ),
				'desc'   => __( 'Configure detection and llms.txt', 'ai-search-optimizer' ),
				'icon'   => 'dashicons-admin-settings',
				'slug'   => 'settings',
				'render' => $settings_module ? [ $settings_module, 'render' ] : null,
			],
			'pro' => [
				'label'    => __( 'Pro', 'ai-search-optimizer' ),
				'desc'     => __( 'Citation tracking and analytics', 'ai-search-optimizer' ),
				'icon'     => 'dashicons-external',
				'slug'     => 'pro',
				'external' => true,
				'href'     => 'https://citewp.com',
			],
		];

		/**
		 * Filters the CiteWP admin navigation items.
		 *
		 * Each item is an associative array with:
		 *   label    (string)   Required. Nav label.
		 *   icon     (string)   Required. Dashicon class (e.g. 'dashicons-chart-line'). Empty string = no icon.
		 *   slug     (string)   Required. URL hash slug (e.g. 'dashboard', 'crawler-logs').
		 *   render   (callable) For internal sections: callable that outputs the panel HTML.
		 *   external (bool)     True for external link-outs. Requires 'href'. No panel rendered.
		 *   href     (string)   URL for external items.
		 *
		 * Items with a 'render' callable register a panel in the main content area.
		 * Items with 'external => true' render as link-outs in the rail only.
		 * FB28/FB30/FB34 register through this filter by appending an item with their own render callable.
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
		<div class="wrap">

			<div class="citewp-aiso-header">
				<span class="citewp-aiso-header__wordmark">[CiteWP]</span>
			</div>

			<div class="citewp-aiso-page">

				<nav class="citewp-aiso-rail" aria-label="<?php esc_attr_e( 'CiteWP sections', 'ai-search-optimizer' ); ?>">
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
							<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="citewp-aiso-rail__item-text">
							<span class="citewp-aiso-rail__item-label"><?php echo esc_html( $item['label'] ); ?></span>
							<?php if ( ! empty( $item['desc'] ) ) : ?>
								<span class="citewp-aiso-rail__item-desc"><?php echo esc_html( $item['desc'] ); ?></span>
							<?php endif; ?>
						</span>
					</a>
					<?php endforeach; ?>
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

		</div><!-- .wrap -->

		<script>
		(function () {
			var navItems = document.querySelectorAll( '.citewp-aiso-rail__item[data-panel]' );
			var panels   = document.querySelectorAll( '.citewp-aiso-panel[data-panel]' );
			var known    = <?php echo wp_json_encode( array_values( $nav_slugs ) ); ?>;
			var SS_KEY   = 'citewp_aiso_section';

			function activate( slug ) {
				navItems.forEach( function ( item ) {
					var active = item.dataset.panel === slug;
					item.classList.toggle( 'citewp-aiso-rail__item--active', active );
					if ( active ) {
						item.setAttribute( 'aria-current', 'page' );
					} else {
						item.removeAttribute( 'aria-current' );
					}
				} );
				panels.forEach( function ( panel ) {
					var active = panel.dataset.panel === slug;
					panel.classList.toggle( 'citewp-aiso-panel--active', active );
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

		$avg_grade = 'red';
		if ( $avg_score !== null ) {
			if ( $avg_score >= 80 ) {
				$avg_grade = 'green';
			} elseif ( $avg_score >= 60 ) {
				$avg_grade = 'yellow';
			} elseif ( $avg_score >= 40 ) {
				$avg_grade = 'orange';
			}
		}

		// Gauge arc: semicircle radius 54, circumference of half-circle = π * 54 ≈ 169.6.
		$circumference = M_PI * 54;
		$fill_pct      = $avg_score !== null ? max( 0, min( 100, $avg_score ) ) : 0;
		$offset        = $circumference - ( $fill_pct / 100 ) * $circumference;

		$logs_url = admin_url( 'admin.php?page=' . self::SLUG_PARENT ) . '#crawler-logs';

		/**
		 * Filters extra dashboard cards rendered after the built-in summary.
		 *
		 * Each card: title (string), value (string), description (string, optional),
		 * link_url (string, optional), link_label (string, optional).
		 *
		 * @param array<int, array<string, string>> $cards
		 */
		$extra_cards = apply_filters( 'citewp_aiso/dashboard/cards', [] );
		?>
		<div class="citewp-aiso-page-body">

			<h2 class="citewp-aiso-panel__title"><?php esc_html_e( 'Dashboard', 'ai-search-optimizer' ); ?></h2>

			<!-- Inline stat row — P27 single-column (no card grid) -->
			<div class="citewp-aiso-stat-row">

				<div class="citewp-aiso-stat-row__item">
					<?php if ( $avg_score !== null ) : ?>
						<svg class="citewp-aiso-gauge" width="80" height="44" viewBox="0 0 120 66" aria-hidden="true">
							<path class="citewp-aiso-gauge__track" d="M 6,60 A 54,54 0 0,1 114,60" />
							<path
								class="citewp-aiso-gauge__fill citewp-aiso-gauge__fill--<?php echo esc_attr( $avg_grade ); ?>"
								d="M 6,60 A 54,54 0 0,1 114,60"
								stroke-dasharray="<?php echo esc_attr( (string) round( $circumference, 2 ) ); ?>"
								stroke-dashoffset="<?php echo esc_attr( (string) round( $offset, 2 ) ); ?>"
							/>
							<text x="60" y="56" class="citewp-aiso-gauge__score-text"><?php echo esc_html( (string) $avg_score ); ?></text>
						</svg>
						<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Average Cite Score: %d out of 100', 'ai-search-optimizer' ), $avg_score ) ); ?></span>
					<?php else : ?>
						<span class="citewp-aiso-stat-row__value citewp-aiso-stat-row__value--empty">—</span>
					<?php endif; ?>
					<span class="citewp-aiso-stat-row__label"><?php esc_html_e( 'Avg Cite Score', 'ai-search-optimizer' ); ?></span>
				</div>

				<div class="citewp-aiso-stat-row__item">
					<span class="citewp-aiso-stat-row__value"><?php echo esc_html( number_format_i18n( $trend['this_week'] ) ); ?></span>
					<span class="citewp-aiso-stat-row__label">
						<?php esc_html_e( 'Bot visits (7d)', 'ai-search-optimizer' ); ?>
						<?php
						$diff = $trend['this_week'] - $trend['last_week'];
						if ( $trend['last_week'] > 0 && $diff !== 0 ) :
							$arrow = $diff > 0 ? '▲' : '▼';
							echo ' ' . esc_html( $arrow . ' ' . number_format_i18n( abs( $diff ) ) );
						endif;
						?>
					</span>
				</div>

			</div><!-- .citewp-aiso-stat-row -->

			<!-- Needs Attention -->
			<div class="citewp-aiso-section">
				<div class="citewp-aiso-section__header">
					<h3 class="citewp-aiso-section__title"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></h3>
					<p class="citewp-aiso-section__desc"><?php esc_html_e( 'Posts with the lowest Cite Scores.', 'ai-search-optimizer' ); ?></p>
				</div>
				<div class="citewp-aiso-section__body">
					<?php if ( ! empty( $lowest_posts ) ) : ?>
						<ul class="citewp-aiso-needs-attention__list">
							<?php foreach ( $lowest_posts as $post ) :
								$score    = (int) get_post_meta( $post->ID, Repository::META_KEY_TOTAL, true );
								$grade    = get_post_meta( $post->ID, Repository::META_KEY_GRADE, true );
								$grade    = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true ) ? $grade : 'red';
								$edit_url = get_edit_post_link( $post->ID );
							?>
							<li class="citewp-aiso-needs-attention__item">
								<span class="citewp-aiso-badge citewp-aiso-badge--<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( (string) $score ); ?></span>
								<?php if ( $edit_url ) : ?>
									<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-needs-attention__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
								<?php else : ?>
									<span class="citewp-aiso-needs-attention__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="citewp-aiso-empty">
							<p class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored posts yet.', 'ai-search-optimizer' ); ?></p>
							<p class="citewp-aiso-empty__desc"><?php esc_html_e( 'Open any post to trigger scoring.', 'ai-search-optimizer' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Quick Actions -->
			<div class="citewp-aiso-quick-actions">
				<a href="<?php echo esc_url( $logs_url ); ?>" class="button"><?php esc_html_e( 'View Crawler Logs', 'ai-search-optimizer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=citewp_aiso_regenerate_llms&_wpnonce=' . wp_create_nonce( 'citewp_aiso_regenerate_llms' ) ) ); ?>" class="button"><?php esc_html_e( 'Regenerate llms.txt', 'ai-search-optimizer' ); ?></a>
			</div>

			<!-- Extra cards from filter (Pro / extensions) -->
			<?php if ( ! empty( $extra_cards ) ) : ?>
			<div class="citewp-aiso-card-grid">
				<?php foreach ( $extra_cards as $card ) :
					if ( ! isset( $card['title'], $card['value'] ) ) {
						continue;
					}
				?>
				<div class="citewp-aiso-card">
					<p class="citewp-aiso-card__title"><?php echo esc_html( $card['title'] ); ?></p>
					<p class="citewp-aiso-card__value"><?php echo esc_html( $card['value'] ); ?></p>
					<?php if ( ! empty( $card['description'] ) ) : ?>
						<p class="citewp-aiso-card__desc"><?php echo esc_html( $card['description'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $card['link_url'] ) && ! empty( $card['link_label'] ) ) : ?>
						<a href="<?php echo esc_url( $card['link_url'] ); ?>" class="citewp-aiso-card__link"><?php echo esc_html( $card['link_label'] ); ?></a>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

		</div><!-- .citewp-aiso-page-body -->
		<?php
	}
}
