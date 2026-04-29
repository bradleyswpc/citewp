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
	public const SLUG_LOGS   = 'citewp-aiso-crawler-logs';

	public function register(): void {
		add_action( 'admin_menu',             [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'CiteWP', 'ai-search-optimizer' ),
			__( 'CiteWP', 'ai-search-optimizer' ),
			'manage_options',
			self::SLUG_PARENT,
			[ $this, 'render_dashboard' ],
			'dashicons-chart-line',
			81
		);

		add_submenu_page(
			self::SLUG_PARENT,
			__( 'Crawler Logs', 'ai-search-optimizer' ),
			__( 'Crawler Logs', 'ai-search-optimizer' ),
			'manage_options',
			self::SLUG_LOGS,
			[ \CiteWP\Aiso\Plugin::instance()->module( 'admin_logs_page' ), 'render' ]
		);

		// Rename the auto-added duplicate "CiteWP" submenu to "Dashboard".
		global $submenu;
		if ( isset( $submenu[ self::SLUG_PARENT ][0][0] ) ) {
			$submenu[ self::SLUG_PARENT ][0][0] = __( 'Dashboard', 'ai-search-optimizer' );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_citewp_screen = (
			$hook === 'toplevel_page_' . self::SLUG_PARENT ||
			strpos( $hook, 'citewp_page_' ) === 0
		);

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

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

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

		$settings_url = admin_url( 'admin.php?page=citewp-aiso-settings' );
		$logs_url     = admin_url( 'admin.php?page=' . self::SLUG_LOGS );

		/**
		 * Filters extra dashboard cards rendered after the built-in summary cards.
		 *
		 * Each card is an associative array with keys: title (string), value (string),
		 * description (string, optional), link_url (string, optional), link_label (string, optional).
		 * Return an empty array (default) to render no extra cards.
		 *
		 * @param array<int, array<string, string>> $cards Extra cards to render.
		 */
		$extra_cards = apply_filters( 'citewp_aiso/dashboard/cards', [] );
		?>
		<div class="wrap">

			<?php PageHeader::render_nav( self::SLUG_PARENT ); ?>

			<div class="citewp-aiso-page-body">

				<div class="citewp-aiso-card-grid citewp-aiso-card-grid--3col">

					<!-- Average Score Gauge -->
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php esc_html_e( 'Avg GEO Score', 'ai-search-optimizer' ); ?></p>
						<?php if ( $avg_score !== null ) : ?>
							<div class="citewp-aiso-gauge">
								<svg width="120" height="66" viewBox="0 0 120 66" aria-hidden="true">
									<path
										class="citewp-aiso-gauge__track"
										d="M 6,60 A 54,54 0 0,1 114,60"
									/>
									<path
										class="citewp-aiso-gauge__fill citewp-aiso-gauge__fill--<?php echo esc_attr( $avg_grade ); ?>"
										d="M 6,60 A 54,54 0 0,1 114,60"
										stroke-dasharray="<?php echo esc_attr( (string) round( $circumference, 2 ) ); ?>"
										stroke-dashoffset="<?php echo esc_attr( (string) round( $offset, 2 ) ); ?>"
									/>
									<text x="60" y="56" class="citewp-aiso-gauge__score-text"><?php echo esc_html( (string) $avg_score ); ?></text>
								</svg>
								<span class="citewp-aiso-gauge__label"><?php esc_html_e( 'across all scored posts', 'ai-search-optimizer' ); ?></span>
							</div>
						<?php else : ?>
							<div class="citewp-aiso-empty">
								<p class="citewp-aiso-empty__title"><?php esc_html_e( 'No scores yet', 'ai-search-optimizer' ); ?></p>
								<p class="citewp-aiso-empty__desc"><?php esc_html_e( 'Open any post to trigger scoring.', 'ai-search-optimizer' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<!-- Bot Visit Trend -->
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-card__value"><?php echo esc_html( number_format_i18n( $trend['this_week'] ) ); ?></p>
						<?php
						$diff = $trend['this_week'] - $trend['last_week'];
						if ( $trend['last_week'] > 0 && $diff !== 0 ) :
							$arrow = $diff > 0 ? '▲' : '▼';
						?>
						<p class="citewp-aiso-card__desc"><?php echo esc_html( $arrow . ' ' . number_format_i18n( abs( $diff ) ) . ' ' . __( 'vs. prior 7 days', 'ai-search-optimizer' ) ); ?></p>
						<?php else : ?>
						<p class="citewp-aiso-card__desc"><?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Lowest Scoring Posts -->
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></p>
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
							<p class="citewp-aiso-needs-attention__nodata"><?php esc_html_e( 'No scored posts yet.', 'ai-search-optimizer' ); ?></p>
						<?php endif; ?>
					</div>

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

				<!-- Quick actions -->
				<p class="citewp-aiso-quick-actions">
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Settings', 'ai-search-optimizer' ); ?></a>
					<a href="<?php echo esc_url( $logs_url ); ?>" class="button"><?php esc_html_e( 'Crawler Logs', 'ai-search-optimizer' ); ?></a>
				</p>

			</div>
		</div>
		<?php
	}
}
