<?php
/**
 * Settings page (admin UI for plugin configuration).
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Settings;

use CiteWP\Aiso\Admin\IconLibrary;
use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Llms\Cache;

defined( 'ABSPATH' ) || exit;

final class Page {

	public const SLUG        = 'citewp-aiso-settings';
	public const OPTION_LLMS = 'citewp_aiso_llms_settings';
	public const OPTION_CORE = 'citewp_aiso_settings';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_citewp_aiso_regenerate_llms', [ $this, 'handle_regenerate' ] );
	}

	public function register_settings(): void {
		register_setting(
			'citewp_aiso_settings_group',
			self::OPTION_CORE,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_core' ],
				'default'           => [
					'enable_crawler_detection' => true,
					'log_retention_days'       => 7,
				],
			]
		);

		register_setting(
			'citewp_aiso_settings_group',
			self::OPTION_LLMS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_llms' ],
				'default'           => [
					'enabled'          => true,
					'min_word_count'   => 500,
					'recent_days'      => 90,
					'extra_post_types' => [],
				],
			]
		);
	}

	/**
	 * @param array<string, mixed>|null $input
	 * @return array<string, mixed>
	 */
	public function sanitize_core( $input ): array {
		$input = is_array( $input ) ? $input : [];
		return [
			'enable_crawler_detection' => ! empty( $input['enable_crawler_detection'] ),
			'log_retention_days'       => max( 1, min( 365, (int) ( $input['log_retention_days'] ?? 7 ) ) ),
		];
	}

	/**
	 * @param array<string, mixed>|null $input
	 * @return array<string, mixed>
	 */
	public function sanitize_llms( $input ): array {
		$input = is_array( $input ) ? $input : [];

		$extra = [];
		if ( ! empty( $input['extra_post_types'] ) && is_array( $input['extra_post_types'] ) ) {
			$valid_types = array_keys( get_post_types( [ 'public' => true, 'show_ui' => true ] ) );
			$extra       = array_values( array_intersect( array_map( 'sanitize_key', $input['extra_post_types'] ), $valid_types ) );
		}

		return [
			'enabled'          => ! empty( $input['enabled'] ),
			'min_word_count'   => max( 0, min( 10000, (int) ( $input['min_word_count'] ?? 500 ) ) ),
			'recent_days'      => max( 1, min( 3650, (int) ( $input['recent_days'] ?? 90 ) ) ),
			'extra_post_types' => $extra,
		];
	}

	public function handle_regenerate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-search-optimizer' ) );
		}
		check_admin_referer( 'citewp_aiso_regenerate_llms' );

		( new Cache() )->flush();

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => Menu::SLUG_PARENT, 'regenerated' => '1', 'citewp_section' => 'settings' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$core         = get_option( self::OPTION_CORE, [] );
		$llms         = get_option( self::OPTION_LLMS, [] );
		$public_types = get_post_types( [ 'public' => true, 'show_ui' => true, '_builtin' => false ], 'objects' );
		$llms_short   = home_url( '/llms.txt' );
		$llms_full    = home_url( '/llms-full.txt' );

		/**
		 * Filters the inner tab definitions for the CiteWP Settings page.
		 *
		 * Array keys are tab slugs (used for URL hash and panel IDs).
		 * Array values are translated tab labels.
		 * Built-in slugs: general, crawler-detection, llms-txt.
		 *
		 * @param array<string, string> $tabs Tab slug => label.
		 */
		$tabs = apply_filters(
			'citewp_aiso/settings/tabs',
			[
				'general' => __( 'General', 'ai-search-optimizer' ),
			]
		);

		$default_tab = array_key_first( $tabs ) ?? 'general';
		?>
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h1 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Settings', 'ai-search-optimizer' ); ?></h1>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Configure your AI search optimization preferences.', 'ai-search-optimizer' ); ?></p>
			</div>
			<div class="citewp-aiso-page-header__right">
				<nav class="citewp-aiso-settings-tabnav" role="tablist">
					<?php foreach ( $tabs as $slug => $label ) :
						$btn_id   = 'citewp-aiso-tab-btn-' . esc_attr( $slug );
						$panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
					?>
					<button
						type="button"
						id="<?php echo esc_attr( $btn_id ); ?>"
						class="citewp-aiso-settings-tabnav__item"
						data-tab="<?php echo esc_attr( $slug ); ?>"
						role="tab"
						aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						aria-selected="false"
					><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>

		<?php if ( sanitize_key( wp_unslash( $_GET['regenerated'] ?? '' ) ) === '1' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by this plugin after safe redirect; no data modification. ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt cache cleared. The next request will regenerate from scratch.', 'ai-search-optimizer' ); ?></p></div>
		<?php endif; ?>

		<?php if ( sanitize_key( wp_unslash( $_GET['settings-updated'] ?? '' ) ) !== '' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; no data modification. ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ai-search-optimizer' ); ?></p></div>
		<?php endif; ?>

		<div class="citewp-aiso-page-body" id="citewp-aiso-settings-tabs">

			<form method="post" action="options.php" id="citewp-aiso-settings-form">
					<?php settings_fields( 'citewp_aiso_settings_group' ); ?>

					<!-- General Tab — contains all three section cards -->
					<?php if ( isset( $tabs['general'] ) ) : ?>
					<div
						id="citewp-aiso-tab-general"
						class="citewp-aiso-settings-tabpanel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-general"
					>

						<!-- AI Crawler Detection -->
						<div class="citewp-aiso-fscard">
							<div class="citewp-aiso-fscard__header">
								<div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--orange"><?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
								<?php esc_html_e( 'AI Crawler Detection', 'ai-search-optimizer' ); ?>
							</div>

							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Enable detection', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Log AI crawler visits (GPTBot, ClaudeBot, PerplexityBot, and others).', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<label class="citewp-aiso-toggle">
										<input
											type="checkbox"
											id="citewp_aiso_enable_crawler_detection"
											name="<?php echo esc_attr( self::OPTION_CORE ); ?>[enable_crawler_detection]"
											value="1"
											<?php checked( ! empty( $core['enable_crawler_detection'] ) ); ?>
										/>
										<span class="citewp-aiso-toggle__track" aria-hidden="true"></span>
									</label>
								</div>
							</div>

							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Log retention (days)', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Free tier: 7 days. Older logs pruned daily.', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<input
										type="number"
										id="citewp_aiso_log_retention_days"
										name="<?php echo esc_attr( self::OPTION_CORE ); ?>[log_retention_days]"
										class="small-text citewp-aiso-input--number"
										value="<?php echo esc_attr( (string) ( $core['log_retention_days'] ?? 7 ) ); ?>"
										min="1"
										max="365"
									/>
								</div>
							</div>
						</div>

						<!-- llms.txt Generation -->
						<div class="citewp-aiso-fscard">
							<div class="citewp-aiso-fscard__header">
								<div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--teal"><?php echo IconLibrary::icon( 'llms-txt', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
								<?php esc_html_e( 'llms.txt Generation', 'ai-search-optimizer' ); ?>
							</div>

							<div class="citewp-aiso-fscard__row citewp-aiso-fscard__row--info">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-desc">
										<?php esc_html_e( 'Your llms.txt:', 'ai-search-optimizer' ); ?>
										<a href="<?php echo esc_url( $llms_short ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_short ); ?></code></a>
										&nbsp;|&nbsp;
										<a href="<?php echo esc_url( $llms_full ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_full ); ?></code></a>
									</p>
								</div>
							</div>

							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Enable llms.txt', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Serve dynamic llms.txt to AI engines.', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<label class="citewp-aiso-toggle">
										<input
											type="checkbox"
											id="citewp_aiso_llms_enabled"
											name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[enabled]"
											value="1"
											<?php checked( ! empty( $llms['enabled'] ) ); ?>
										/>
										<span class="citewp-aiso-toggle__track" aria-hidden="true"></span>
									</label>
								</div>
							</div>

							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Minimum word count', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Posts shorter than this are skipped. Pages always included.', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<input
										type="number"
										id="citewp_aiso_min_word_count"
										name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[min_word_count]"
										class="small-text citewp-aiso-input--number"
										value="<?php echo esc_attr( (string) ( $llms['min_word_count'] ?? 500 ) ); ?>"
										min="0"
										max="10000"
										step="50"
									/>
								</div>
							</div>

							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Include posts from last (days)', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Posts published before this window are excluded from llms.txt.', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<input
										type="number"
										id="citewp_aiso_recent_days"
										name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[recent_days]"
										class="small-text citewp-aiso-input--number"
										value="<?php echo esc_attr( (string) ( $llms['recent_days'] ?? 90 ) ); ?>"
										min="1"
										max="3650"
									/>
								</div>
							</div>

							<?php if ( ! empty( $public_types ) ) : ?>
							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Include custom post types', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Selected types will appear alongside posts and pages in llms.txt.', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<?php foreach ( $public_types as $type ) : ?>
										<label class="citewp-aiso-cpt-label">
											<input
												type="checkbox"
												name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[extra_post_types][]"
												value="<?php echo esc_attr( $type->name ); ?>"
												<?php checked( in_array( $type->name, (array) ( $llms['extra_post_types'] ?? [] ), true ) ); ?>
											/>
											<?php echo esc_html( $type->labels->name ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>

						</div>

						<!-- Maintenance -->
						<div class="citewp-aiso-fscard">
							<div class="citewp-aiso-fscard__header">
								<div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--gray"><?php echo IconLibrary::icon( 'refresh-cw', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
								<?php esc_html_e( 'Maintenance', 'ai-search-optimizer' ); ?>
							</div>
							<div class="citewp-aiso-fscard__row">
								<div class="citewp-aiso-fscard__left">
									<p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Regenerate llms.txt', 'ai-search-optimizer' ); ?></p>
									<p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Clears the cache. The next request to /llms.txt rebuilds from scratch.', 'ai-search-optimizer' ); ?></p>
								</div>
								<div class="citewp-aiso-fscard__right">
									<button
										type="submit"
										form="citewp-aiso-regenerate-form"
										class="citewp-aiso-fscard__btn"
									><?php esc_html_e( 'Regenerate now →', 'ai-search-optimizer' ); ?></button>
								</div>
							</div>
						</div>

						<?php do_action( 'citewp_aiso/settings/panel/general' ); ?>
					</div>
					<?php endif; ?>

					<!-- Extra tabs from filter (Pro registers here when citewp.com SaaS ships via citewp_aiso/settings/tabs) -->
					<?php foreach ( $tabs as $slug => $label ) :
						if ( in_array( $slug, [ 'general' ], true ) ) {
							continue;
						}
						$panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
					?>
					<div
						id="<?php echo esc_attr( $panel_id ); ?>"
						class="citewp-aiso-settings-tabpanel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-<?php echo esc_attr( $slug ); ?>"
					>
						<?php do_action( 'citewp_aiso/settings/panel/' . $slug ); ?>
					</div>
					<?php endforeach; ?>

					<div class="citewp-aiso-save-bar">
						<button type="submit" name="submit" class="citewp-aiso-btn--primary-action">
							<?php esc_html_e( 'Save Changes', 'ai-search-optimizer' ); ?>
						</button>
					</div>

				</form>

				<!-- Regenerate form (outside main form to avoid nesting) -->
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="citewp-aiso-regenerate-form">
					<input type="hidden" name="action" value="citewp_aiso_regenerate_llms" />
					<?php wp_nonce_field( 'citewp_aiso_regenerate_llms' ); ?>
				</form>

		</div><!-- .citewp-aiso-page-body -->

		<script>
		(function () {
			var tabs   = document.querySelectorAll( '.citewp-aiso-settings-tabnav__item' );
			var panels = document.querySelectorAll( '.citewp-aiso-settings-tabpanel' );
			var LS_KEY = 'citewp_aiso_settings_tab';

			function activate( slug ) {
				tabs.forEach( function ( btn ) {
					var active = btn.dataset.tab === slug;
					btn.classList.toggle( 'citewp-aiso-settings-tabnav__item--active', active );
					btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( panel ) {
					var active = panel.id === 'citewp-aiso-tab-' + slug;
					panel.classList.toggle( 'citewp-aiso-settings-tabpanel--active', active );
				} );
			}

			// Restore from localStorage, fall back to first tab.
			var stored  = localStorage.getItem( LS_KEY );
			var initial = stored && document.getElementById( 'citewp-aiso-tab-' + stored )
				? stored
				: <?php echo wp_json_encode( (string) $default_tab ); ?>;
			activate( initial );

			tabs.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var slug = btn.dataset.tab;
					localStorage.setItem( LS_KEY, slug );
					activate( slug );
				} );
			} );
		}() );
		</script>
		<?php
	}
}
