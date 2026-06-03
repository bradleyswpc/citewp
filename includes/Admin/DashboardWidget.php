<?php
/**
 * WordPress Dashboard widget.
 *
 * Shows average GEO score, bot visit trend, top crawled pages,
 * and lowest-scoring posts to prompt action.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;
use CiteWP\Aiso\Admin\DashboardData;

defined( 'ABSPATH' ) || exit;

final class DashboardWidget {

	private const WIDGET_ID = 'citewp_aiso_dashboard_widget';

	public function register(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'add_widget' ] );
		add_action( 'admin_head',         [ $this, 'inline_styles' ] );
	}

	public function add_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'CiteWP — Cite Score', 'ai-search-optimizer' ),
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$avg_score    = $this->get_average_score();
		$trend        = $this->get_visit_trend();
		$top_crawlers = $this->get_top_crawlers_data();
		$lowest_posts = $this->get_lowest_scoring_posts();

		/**
		 * Fires to allow registration of extra dashboard card data.
		 * Currently unused by the WP Dashboard widget — extension point for Pro.
		 *
		 * @param array<int, array<string, string>> $cards Extra cards (empty by default).
		 */
		apply_filters( 'citewp_aiso/dashboard/cards', [] );

		$logs_url     = admin_url( 'admin.php?page=' . Menu::SLUG_PARENT );
		$all_posts_url = admin_url( 'edit.php?orderby=citewp_aiso_geo_score&order=asc' );

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

		$this_week  = $trend['this_week'];
		$last_week  = $trend['last_week'];
		$diff       = $this_week - $last_week;
		$trend_icon = '';
		$trend_class = '';
		if ( $last_week > 0 ) {
			$pct = ( $diff / $last_week ) * 100;
			if ( $pct >= 5 ) {
				$trend_icon  = '▲';
				$trend_class = 'citewp-aiso-trend--up';
			} elseif ( $pct <= -5 ) {
				$trend_icon  = '▼';
				$trend_class = 'citewp-aiso-trend--down';
			} else {
				$trend_icon  = '—';
				$trend_class = 'citewp-aiso-trend--flat';
			}
		}
		?>
		<div class="citewp-aiso-widget">

			<div class="citewp-aiso-widget__stats">
				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Avg Cite Score', 'ai-search-optimizer' ); ?></span>
					<?php if ( $avg_score !== null ) : ?>
						<span class="citewp-aiso-stat__value citewp-aiso-stat__value--<?php echo esc_attr( $avg_grade ); ?>"><?php echo esc_html( (string) $avg_score ); ?></span>
						<span class="citewp-aiso-stat__sub"><?php esc_html_e( 'across scored posts', 'ai-search-optimizer' ); ?></span>
					<?php else : ?>
						<span class="citewp-aiso-stat__value citewp-aiso-stat__value--none">—</span>
						<span class="citewp-aiso-stat__sub"><?php esc_html_e( 'No posts scored yet', 'ai-search-optimizer' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-stat__value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></span>
					<span class="citewp-aiso-stat__sub">
						<?php if ( $trend_icon ) : ?>
							<span class="citewp-aiso-trend <?php echo esc_attr( $trend_class ); ?>"><?php echo esc_html( $trend_icon . ' ' . number_format_i18n( abs( $diff ) ) ); ?></span>
						<?php endif; ?>
						<?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?>
					</span>
				</div>
			</div>

			<?php if ( ! empty( $top_crawlers ) ) : ?>
			<div class="citewp-aiso-widget__section">
				<h4 class="citewp-aiso-widget__heading"><?php esc_html_e( 'Top AI Crawlers (Last 7 Days)', 'ai-search-optimizer' ); ?></h4>
				<ul class="citewp-aiso-list">
					<?php foreach ( $top_crawlers as $crawler ) : ?>
					<li class="citewp-aiso-list__item">
						<span class="citewp-aiso-widget__bot-avatar citewp-aiso-bot--<?php echo esc_attr( $crawler['color_class'] ); ?>"><?php echo esc_html( $crawler['initial'] ); ?></span>
						<span class="citewp-aiso-list__title"><?php echo esc_html( $crawler['display_name'] ); ?></span>
						<span class="citewp-aiso-list__count"><?php echo esc_html( number_format_i18n( (int) $crawler['visits'] ) ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $logs_url ); ?>" class="citewp-aiso-widget__link"><?php esc_html_e( 'View all crawler logs →', 'ai-search-optimizer' ); ?></a>
			</div>
			<?php else : ?>
			<p class="citewp-aiso-widget__empty">
				<?php esc_html_e( 'No bot visits logged yet.', 'ai-search-optimizer' ); ?>
				<a href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'View logs →', 'ai-search-optimizer' ); ?></a>
			</p>
			<?php endif; ?>

			<?php if ( ! empty( $lowest_posts ) ) : ?>
			<div class="citewp-aiso-widget__section">
				<h4 class="citewp-aiso-widget__heading"><?php esc_html_e( 'Lowest Cite Scores — Needs Attention', 'ai-search-optimizer' ); ?></h4>
				<ul class="citewp-aiso-list">
					<?php foreach ( $lowest_posts as $post ) : ?>
					<?php
					$score    = (int) get_post_meta( $post->ID, Repository::META_KEY_TOTAL, true );
					$grade    = get_post_meta( $post->ID, Repository::META_KEY_GRADE, true );
					$grade    = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true )
						? $grade : 'red';
					$edit_url = get_edit_post_link( $post->ID );
					?>
					<li class="citewp-aiso-list__item">
						<span class="citewp-aiso-score-badge citewp-aiso-score-badge--<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( (string) $score ); ?></span>
						<?php if ( $edit_url ) : ?>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<?php else : ?>
							<span class="citewp-aiso-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $all_posts_url ); ?>" class="citewp-aiso-widget__link"><?php esc_html_e( 'See all post scores →', 'ai-search-optimizer' ); ?></a>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	private function get_average_score(): ?int {
		return ( new DashboardData() )->get_average_score();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_crawlers_data(): array {
		return ( new DashboardData() )->get_top_crawlers();
	}

	/**
	 * @return array{this_week: int, last_week: int}
	 */
	private function get_visit_trend(): array {
		return ( new DashboardData() )->get_visit_trend();
	}

	/**
	 * @return \WP_Post[]
	 */
	private function get_lowest_scoring_posts(): array {
		return ( new DashboardData() )->get_lowest_scoring_posts();
	}

	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'dashboard' ) {
			return;
		}
		?>
		<style>
			.citewp-aiso-widget { font-size: 13px; }
			.citewp-aiso-widget__stats { display: flex; gap: 16px; margin-bottom: 16px; }
			.citewp-aiso-stat { flex: 1; background: #f9f9f9; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 14px; }
			.citewp-aiso-stat__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 4px; }
			.citewp-aiso-stat__value { display: block; font-size: 28px; font-weight: 700; line-height: 1; color: #111827; }
			.citewp-aiso-stat__value--green  { color: #00A32A; }
			.citewp-aiso-stat__value--yellow { color: #DBA617; }
			.citewp-aiso-stat__value--orange { color: #D63638; }
			.citewp-aiso-stat__value--red    { color: #8C1B1B; }
			.citewp-aiso-stat__value--none   { color: #9ca3af; }
			.citewp-aiso-stat__sub { display: block; font-size: 11px; color: #6b7280; margin-top: 4px; }
			.citewp-aiso-trend--up   { color: #00A32A; font-weight: 600; }
			.citewp-aiso-trend--down { color: #8C1B1B; font-weight: 600; }
			.citewp-aiso-trend--flat { color: #6b7280; }
			.citewp-aiso-widget__section { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
			.citewp-aiso-widget__heading { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #374151; margin: 0 0 8px; font-weight: 600; }
			.citewp-aiso-list { margin: 0 0 8px; padding: 0; list-style: none; }
			.citewp-aiso-list__item { display: flex; align-items: center; gap: 8px; padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
			.citewp-aiso-list__item:last-child { border-bottom: none; }
			.citewp-aiso-list__count { min-width: 32px; font-weight: 700; font-variant-numeric: tabular-nums; color: #111827; text-align: right; }
			.citewp-aiso-list__uri { color: #4b5563; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; font-family: monospace; font-size: 12px; }
			.citewp-aiso-list__title { color: #2271b1; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 230px; }
			.citewp-aiso-list__title:hover { text-decoration: underline; }
			.citewp-aiso-score-badge { min-width: 30px; text-align: center; font-weight: 700; font-size: 12px; padding: 2px 6px; border-radius: 4px; flex-shrink: 0; }
			.citewp-aiso-score-badge--green  { background: #dcfce7; color: #00A32A; }
			.citewp-aiso-score-badge--yellow { background: #fef9c3; color: #DBA617; }
			.citewp-aiso-score-badge--orange { background: #ffedd5; color: #D63638; }
			.citewp-aiso-score-badge--red    { background: #fee2e2; color: #8C1B1B; }
			.citewp-aiso-widget__link { font-size: 12px; color: #2271b1; text-decoration: none; }
			.citewp-aiso-widget__link:hover { text-decoration: underline; }
			.citewp-aiso-widget__empty { color: #6b7280; font-size: 12px; margin: 0; }
			.citewp-aiso-widget__bot-avatar { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
			.citewp-aiso-bot--gpt    { background: #ec4899; }
			.citewp-aiso-bot--claude { background: #F97316; }
			.citewp-aiso-bot--perp   { background: #7C3AED; }
			.citewp-aiso-bot--google { background: #2563EB; }
			.citewp-aiso-bot--default { background: #14B8A6; }
		</style>
		<?php
	}
}
