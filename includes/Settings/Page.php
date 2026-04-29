<?php
/**
 * Settings page (admin UI for plugin configuration).
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Settings;

use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Admin\PageHeader;
use CiteWP\Aiso\Llms\Cache;

defined( 'ABSPATH' ) || exit;

final class Page {

	public const SLUG        = 'citewp-aiso-settings';
	public const OPTION_LLMS = 'citewp_aiso_llms_settings';
	public const OPTION_CORE = 'citewp_aiso_settings';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_citewp_aiso_regenerate_llms', [ $this, 'handle_regenerate' ] );
	}

	public function add_submenu(): void {
		add_submenu_page(
			Menu::SLUG_PARENT,
			__( 'CiteWP Settings', 'ai-search-optimizer' ),
			__( 'Settings', 'ai-search-optimizer' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
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
				[ 'page' => self::SLUG, 'regenerated' => '1' ],
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
				'general'           => __( 'General', 'ai-search-optimizer' ),
				'crawler-detection' => __( 'Crawler Detection', 'ai-search-optimizer' ),
				'llms-txt'          => __( 'llms.txt', 'ai-search-optimizer' ),
			]
		);

		$default_tab = array_key_first( $tabs ) ?? 'general';
		?>
		<div class="wrap">

			<?php PageHeader::render_nav( self::SLUG ); ?>

			<?php if ( isset( $_GET['regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by this plugin after safe redirect; no data modification. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt cache cleared. The next request will regenerate from scratch.', 'ai-search-optimizer' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; no data modification. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ai-search-optimizer' ); ?></p></div>
			<?php endif; ?>

			<div class="citewp-aiso-tabs" id="citewp-aiso-settings-tabs">

				<div class="citewp-aiso-tabs__nav" role="tablist">
					<?php foreach ( $tabs as $slug => $label ) :
						$btn_id   = 'citewp-aiso-tab-btn-' . esc_attr( $slug );
						$panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
					?>
					<button
						type="button"
						id="<?php echo esc_attr( $btn_id ); ?>"
						class="citewp-aiso-tabs__btn"
						data-tab="<?php echo esc_attr( $slug ); ?>"
						role="tab"
						aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						aria-selected="false"
					><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>

				<form method="post" action="options.php" id="citewp-aiso-settings-form">
					<?php settings_fields( 'citewp_aiso_settings_group' ); ?>

					<!-- General Tab -->
					<?php if ( isset( $tabs['general'] ) ) : ?>
					<div
						id="citewp-aiso-tab-general"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-general"
					>
						<div class="citewp-aiso-section">
							<div class="citewp-aiso-section__header">
								<h2 class="citewp-aiso-section__title"><?php esc_html_e( 'Maintenance', 'ai-search-optimizer' ); ?></h2>
								<p class="citewp-aiso-section__desc"><?php esc_html_e( 'Tools to reset or regenerate plugin data.', 'ai-search-optimizer' ); ?></p>
							</div>
							<div class="citewp-aiso-section__body">
								<div class="citewp-aiso-field citewp-aiso-field--stacked">
									<span class="citewp-aiso-field__label-text"><?php esc_html_e( 'Regenerate llms.txt', 'ai-search-optimizer' ); ?></span>
									<span class="citewp-aiso-field__label-desc"><?php esc_html_e( 'Clears the cache. The next request to /llms.txt rebuilds from scratch.', 'ai-search-optimizer' ); ?></span>
									<button
										type="submit"
										form="citewp-aiso-regenerate-form"
										class="button"
									><?php esc_html_e( 'Regenerate now', 'ai-search-optimizer' ); ?></button>
								</div>
							</div>
						</div>
						<?php do_action( 'citewp_aiso/settings/panel/general' ); ?>
					</div>
					<?php endif; ?>

					<!-- Crawler Detection Tab -->
					<?php if ( isset( $tabs['crawler-detection'] ) ) : ?>
					<div
						id="citewp-aiso-tab-crawler-detection"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-crawler-detection"
					>
						<div class="citewp-aiso-section">
							<div class="citewp-aiso-section__header">
								<h2 class="citewp-aiso-section__title"><?php esc_html_e( 'Crawler Detection', 'ai-search-optimizer' ); ?></h2>
								<p class="citewp-aiso-section__desc"><?php esc_html_e( 'Controls whether AI crawler visits are logged to your database.', 'ai-search-optimizer' ); ?></p>
							</div>
							<div class="citewp-aiso-section__body">
								<div class="citewp-aiso-field">
									<label class="citewp-aiso-field__label" for="citewp_aiso_enable_crawler_detection">
										<span class="citewp-aiso-field__label-text"><?php esc_html_e( 'Enable detection', 'ai-search-optimizer' ); ?></span>
										<span class="citewp-aiso-field__label-desc"><?php esc_html_e( 'Log AI crawler visits (GPTBot, ClaudeBot, PerplexityBot, and others).', 'ai-search-optimizer' ); ?></span>
									</label>
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
								<div class="citewp-aiso-input-row">
									<label class="citewp-aiso-input-row__label" for="citewp_aiso_log_retention_days">
										<?php esc_html_e( 'Log retention (days)', 'ai-search-optimizer' ); ?>
									</label>
									<div class="citewp-aiso-input-row__field">
										<input
											type="number"
											id="citewp_aiso_log_retention_days"
											name="<?php echo esc_attr( self::OPTION_CORE ); ?>[log_retention_days]"
											class="small-text citewp-aiso-input--number"
											value="<?php echo esc_attr( (string) ( $core['log_retention_days'] ?? 7 ) ); ?>"
											min="1"
											max="365"
										/>
										<span class="description"><?php esc_html_e( 'Free tier: 7 days. Older logs pruned daily.', 'ai-search-optimizer' ); ?></span>
									</div>
								</div>
							</div>
						</div>
						<?php do_action( 'citewp_aiso/settings/panel/crawler-detection' ); ?>
					</div>
					<?php endif; ?>

					<!-- llms.txt Tab -->
					<?php if ( isset( $tabs['llms-txt'] ) ) : ?>
					<div
						id="citewp-aiso-tab-llms-txt"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-llms-txt"
					>
						<div class="citewp-aiso-section">
							<div class="citewp-aiso-section__header">
								<h2 class="citewp-aiso-section__title"><?php esc_html_e( 'llms.txt Generation', 'ai-search-optimizer' ); ?></h2>
								<p class="citewp-aiso-section__desc">
									<?php esc_html_e( 'Your llms.txt:', 'ai-search-optimizer' ); ?>
									<a href="<?php echo esc_url( $llms_short ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_short ); ?></code></a>
									&nbsp;|&nbsp;
									<a href="<?php echo esc_url( $llms_full ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_full ); ?></code></a>
								</p>
							</div>
							<div class="citewp-aiso-section__body">
								<div class="citewp-aiso-field">
									<label class="citewp-aiso-field__label" for="citewp_aiso_llms_enabled">
										<span class="citewp-aiso-field__label-text"><?php esc_html_e( 'Enable llms.txt', 'ai-search-optimizer' ); ?></span>
										<span class="citewp-aiso-field__label-desc"><?php esc_html_e( 'Serve dynamic llms.txt to AI engines.', 'ai-search-optimizer' ); ?></span>
									</label>
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
								<div class="citewp-aiso-input-row">
									<label class="citewp-aiso-input-row__label" for="citewp_aiso_min_word_count">
										<?php esc_html_e( 'Minimum word count', 'ai-search-optimizer' ); ?>
									</label>
									<div class="citewp-aiso-input-row__field">
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
										<span class="description"><?php esc_html_e( 'Posts shorter than this are skipped. Pages always included.', 'ai-search-optimizer' ); ?></span>
									</div>
								</div>
								<div class="citewp-aiso-input-row">
									<label class="citewp-aiso-input-row__label" for="citewp_aiso_recent_days">
										<?php esc_html_e( 'Include posts from last (days)', 'ai-search-optimizer' ); ?>
									</label>
									<div class="citewp-aiso-input-row__field">
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
								<div class="citewp-aiso-input-row citewp-aiso-input-row--stacked">
									<span class="citewp-aiso-input-row__label"><?php esc_html_e( 'Include custom post types', 'ai-search-optimizer' ); ?></span>
									<div>
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
						</div>
						<?php do_action( 'citewp_aiso/settings/panel/llms-txt' ); ?>
					</div>
					<?php endif; ?>

					<!-- Extra tabs from filter (Pro registers here when citewp.com SaaS ships via citewp_aiso/settings/tabs) -->
					<?php foreach ( $tabs as $slug => $label ) :
						if ( in_array( $slug, [ 'general', 'crawler-detection', 'llms-txt' ], true ) ) {
							continue;
						}
						$panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
					?>
					<div
						id="<?php echo esc_attr( $panel_id ); ?>"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-<?php echo esc_attr( $slug ); ?>"
					>
						<?php do_action( 'citewp_aiso/settings/panel/' . $slug ); ?>
					</div>
					<?php endforeach; ?>

					<div class="citewp-aiso-save-bar">
						<?php submit_button( null, 'primary', 'submit', false ); ?>
					</div>

				</form>

				<!-- Regenerate form (outside main form to avoid nesting) -->
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="citewp-aiso-regenerate-form">
					<input type="hidden" name="action" value="citewp_aiso_regenerate_llms" />
					<?php wp_nonce_field( 'citewp_aiso_regenerate_llms' ); ?>
				</form>

			</div><!-- .citewp-aiso-tabs -->

		</div><!-- .wrap -->

		<script>
		(function () {
			var tabs   = document.querySelectorAll( '.citewp-aiso-tabs__btn' );
			var panels = document.querySelectorAll( '.citewp-aiso-tabs__panel' );

			function activate( slug ) {
				tabs.forEach( function ( btn ) {
					var active = btn.dataset.tab === slug;
					btn.classList.toggle( 'citewp-aiso-tabs__btn--active', active );
					btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( panel ) {
					var active = panel.id === 'citewp-aiso-tab-' + slug;
					panel.classList.toggle( 'citewp-aiso-tabs__panel--active', active );
				} );
			}

			// Restore from URL hash.
			var hash    = location.hash.replace( '#', '' );
			var initial = hash && document.getElementById( 'citewp-aiso-tab-' + hash ) ? hash : <?php echo wp_json_encode( $default_tab ); ?>;
			activate( initial );

			tabs.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var slug = btn.dataset.tab;
					history.replaceState( null, '', '#' + slug );
					activate( slug );
				} );
			} );
		}());
		</script>
		<?php
	}
}
