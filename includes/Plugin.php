<?php
/**
 * Core plugin orchestrator.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP;

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

		// Admin-only modules.
		if ( is_admin() ) {
			$this->modules['admin_menu'] = new Admin\Menu();
			$this->modules['admin_menu']->register();

			$this->modules['admin_logs_page'] = new Admin\LogsPage();
			$this->modules['admin_logs_page']->register();
		}
	}

	/**
	 * Activation hook. Creates DB tables, sets defaults.
	 */
	public static function activate(): void {
		Database\Schema::install();
		add_option( 'citewp_db_version', CITEWP_DB_VERSION );
		add_option(
			'citewp_settings',
			[
				'enable_crawler_detection' => true,
				'log_retention_days'       => 7,
			]
		);

		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook. Non-destructive — preserve user data.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function module( string $key ): ?object {
		return $this->modules[ $key ] ?? null;
	}
}
