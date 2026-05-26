<?php
/**
 * Core plugin orchestrator.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	/** @var array<string, object> */
	private array $modules = [];

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {}

	/**
	 * Wire up all modules. Called once on plugins_loaded.
	 */
	public function boot(): void {
		// Run any pending DB migrations on version bump.
		Database\Schema::maybe_upgrade();

		// Crawler detection runs on every request — register early.
		$this->modules['crawler_detector'] = new Crawler\Detector();
		$this->modules['crawler_detector']->register();

		// llms.txt: cache invalidation + request routing run on every request.
		$llms_settings = get_option( 'citewp_aiso_llms_settings', [] );
		if ( ! empty( $llms_settings['enabled'] ) || ! isset( $llms_settings['enabled'] ) ) {
			$this->modules['llms_cache'] = new Llms\Cache();
			$this->modules['llms_cache']->register();

			$this->modules['llms_router'] = new Llms\Router();
			$this->modules['llms_router']->register();
		}

		// Schema detector: captures rendered-page JSON-LD via template_redirect cache
		// and tier-1 self-request. Must run on every request, not admin-only.
		$this->modules['schema_detector'] = new Schema\Detector();
		$this->modules['schema_detector']->register();

		// Scoring: persists scores on save_post; runs on every request because
		// the save_post hook needs to be registered.
		$this->modules['scoring_repository'] = new Scoring\Repository(
			null,
			$this->modules['schema_detector']
		);
		$this->modules['scoring_repository']->register();

		// REST API for score retrieval (Gutenberg sidebar + post list).
		$this->modules['rest_score_controller'] = new Rest\ScoreController();
		$this->modules['rest_score_controller']->register();

		// REST API for schema suggestions (Document Settings panel).
		$this->modules['rest_schema_controller'] = new Rest\SchemaController(
			null,
			$this->modules['schema_detector']
		);
		$this->modules['rest_schema_controller']->register();

		// Score history: cron callback registered on every request.
		$this->modules['score_history'] = new Scoring\ScoreHistory();
		$this->modules['score_history']->register();

		// Register post meta with REST API support (needed on all requests, including REST).
		add_action( 'init', [ $this, 'register_post_meta_fields' ] );

		// Admin-only modules.
		if ( is_admin() ) {
			$this->modules['admin_menu'] = new Admin\Menu();
			$this->modules['admin_menu']->register();

			$this->modules['admin_logs_page'] = new Admin\LogsPage();
			$this->modules['admin_logs_page']->register();

			$this->modules['settings_page'] = new Settings\Page();
			$this->modules['settings_page']->register();

			$this->modules['post_list_column'] = new Admin\PostListColumn();
			$this->modules['post_list_column']->register();

			$this->modules['editor_assets'] = new Admin\EditorAssets();
			$this->modules['editor_assets']->register();

			$this->modules['dashboard_widget'] = new Admin\DashboardWidget();
			$this->modules['dashboard_widget']->register();

			$this->modules['editor_panel'] = new Admin\EditorPanel();
			$this->modules['editor_panel']->register();

			$this->modules['recommendation_filter'] = new Admin\RecommendationFilter();
			$this->modules['recommendation_filter']->register();
		}
	}

	/**
	 * Register post meta with REST API support.
	 *
	 * Separate register_post_meta() calls per post type (not one call with an array)
	 * so FB40 (CPT scope) can extend to additional types from its own module
	 * without modifying this method.
	 */
	public function register_post_meta_fields(): void {
		foreach ( [ 'post', 'page' ] as $post_type ) {
			register_post_meta(
				$post_type,
				'_citewp_aiso_exclude_from_llms',
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
						return current_user_can( 'edit_post', $post_id );
					},
				]
			);
		}
	}

	/**
	 * Activation hook. Creates DB tables, sets defaults, registers rewrite rules.
	 */
	public static function activate(): void {
		Database\Schema::install();
		add_option( 'citewp_aiso_db_version', CITEWP_AISO_DB_VERSION );
		add_option(
			'citewp_aiso_settings',
			[
				'enable_crawler_detection' => true,
				'log_retention_days'       => 7,
			]
		);
		add_option(
			'citewp_aiso_llms_settings',
			[
				'enabled'          => true,
				'min_word_count'   => 500,
				'recent_days'      => 90,
				'extra_post_types' => [],
			]
		);

		// Register llms.txt rewrite rules and flush so /llms.txt resolves immediately.
		Llms\Router::flush_rewrite_rules_on_activation();
		( new Scoring\ScoreHistory() )->schedule();
	}

	/**
	 * Deactivation hook. Non-destructive — preserve user data.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
		( new Scoring\ScoreHistory() )->unschedule();
	}

	public function module( string $key ): ?object {
		return $this->modules[ $key ] ?? null;
	}
}
