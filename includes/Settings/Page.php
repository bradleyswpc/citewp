<?php
/**
 * Settings page (admin UI for plugin configuration).
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Settings;

use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Llms\Cache;

defined( 'ABSPATH' ) || exit;

final class Page {

	public const SLUG          = 'citewp-aiso-settings';
	public const OPTION_LLMS   = 'citewp_aiso_llms_settings';
	public const OPTION_CORE   = 'citewp_aiso_settings';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_citewp_aiso_regenerate_llms', [ $this, 'handle_regenerate' ] );
		add_action( 'admin_head', [ $this, 'inline_styles' ] );
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
					'enabled'         => true,
					'min_word_count'  => 500,
					'recent_days'     => 90,
					'extra_post_types'=> [],
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

	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'citewp_page_' . self::SLUG ) {
			return;
		}
		?>
		<style>
			.citewp-cpt-label { display: block; margin-bottom: 4px; }
		</style>
		<?php
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$core = get_option( self::OPTION_CORE, [] );
		$llms = get_option( self::OPTION_LLMS, [] );

		$public_types = get_post_types(
			[ 'public' => true, 'show_ui' => true, '_builtin' => false ],
			'objects'
		);

		$llms_short_url = home_url( '/llms.txt' );
		$llms_full_url  = home_url( '/llms-full.txt' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CiteWP Settings', 'ai-search-optimizer' ); ?></h1>

			<?php if ( isset( $_GET['regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by this plugin after safe redirect; no data modification. ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'llms.txt cache cleared. The next request will regenerate from scratch.', 'ai-search-optimizer' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; no data modification. ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'ai-search-optimizer' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'citewp_aiso_settings_group' ); ?>

				<h2><?php esc_html_e( 'Crawler Detection', 'ai-search-optimizer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable detection', 'ai-search-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_CORE ); ?>[enable_crawler_detection]" value="1" <?php checked( ! empty( $core['enable_crawler_detection'] ) ); ?> />
								<?php esc_html_e( 'Log AI crawler visits to your database', 'ai-search-optimizer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="citewp_aiso_log_retention_days"><?php esc_html_e( 'Log retention (days)', 'ai-search-optimizer' ); ?></label></th>
						<td>
							<input type="number" id="citewp_aiso_log_retention_days" name="<?php echo esc_attr( self::OPTION_CORE ); ?>[log_retention_days]" value="<?php echo esc_attr( (string) ( $core['log_retention_days'] ?? 7 ) ); ?>" min="1" max="365" />
							<p class="description"><?php esc_html_e( 'Free tier: 7 days. Older logs are pruned daily.', 'ai-search-optimizer' ); ?></p>
						</td>
					</tr>
				</table>

				<hr />

				<h2><?php esc_html_e( 'llms.txt Generation', 'ai-search-optimizer' ); ?></h2>

				<p>
					<?php esc_html_e( 'Your llms.txt file is served at:', 'ai-search-optimizer' ); ?>
					<a href="<?php echo esc_url( $llms_short_url ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_short_url ); ?></code></a>
					<br />
					<?php esc_html_e( 'Full version (with content bodies):', 'ai-search-optimizer' ); ?>
					<a href="<?php echo esc_url( $llms_full_url ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_full_url ); ?></code></a>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable llms.txt', 'ai-search-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[enabled]" value="1" <?php checked( ! empty( $llms['enabled'] ) ); ?> />
								<?php esc_html_e( 'Serve dynamic llms.txt to AI engines', 'ai-search-optimizer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="citewp_aiso_min_word_count"><?php esc_html_e( 'Minimum word count for posts', 'ai-search-optimizer' ); ?></label></th>
						<td>
							<input type="number" id="citewp_aiso_min_word_count" name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[min_word_count]" value="<?php echo esc_attr( (string) ( $llms['min_word_count'] ?? 500 ) ); ?>" min="0" max="10000" step="50" />
							<p class="description"><?php esc_html_e( 'Posts shorter than this are skipped (Pages and cornerstone content are always included regardless).', 'ai-search-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="citewp_aiso_recent_days"><?php esc_html_e( 'Include posts from last (days)', 'ai-search-optimizer' ); ?></label></th>
						<td>
							<input type="number" id="citewp_aiso_recent_days" name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[recent_days]" value="<?php echo esc_attr( (string) ( $llms['recent_days'] ?? 90 ) ); ?>" min="1" max="3650" />
						</td>
					</tr>

					<?php if ( ! empty( $public_types ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include custom post types', 'ai-search-optimizer' ); ?></th>
						<td>
							<?php foreach ( $public_types as $type ) : ?>
								<label class="citewp-cpt-label">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[extra_post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, (array) ( $llms['extra_post_types'] ?? [] ), true ) ); ?> />
									<?php echo esc_html( $type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<?php endif; ?>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Maintenance', 'ai-search-optimizer' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="citewp_aiso_regenerate_llms" />
				<?php wp_nonce_field( 'citewp_aiso_regenerate_llms' ); ?>
				<p>
					<button type="submit" class="button">
						<?php esc_html_e( 'Regenerate llms.txt now', 'ai-search-optimizer' ); ?>
					</button>
					<span class="description" style="margin-left:8px;">
						<?php esc_html_e( 'Clears the cache. Next request rebuilds from scratch.', 'ai-search-optimizer' ); ?>
					</span>
				</p>
			</form>
		</div>
		<?php
	}
}
