<?php
/**
 * Routes /llms.txt and /llms-full.txt requests through WP rewrite rules.
 *
 * Why rewrite rules instead of writing physical files:
 *  - Works on hosts with locked-down webroot (most managed WP)
 *  - No multisite filesystem collision
 *  - Cache invalidation is just a transient flush, not a file write
 *  - Identical pattern to how Yoast/RankMath serve sitemap.xml
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Llms;

defined( 'ABSPATH' ) || exit;

final class Router {

	private const QUERY_VAR = 'citewp_llms';

	private Cache $cache;
	private Generator $generator;

	public function __construct( ?Cache $cache = null, ?Generator $generator = null ) {
		$this->cache     = $cache ?? new Cache();
		$this->generator = $generator ?? new Generator();
	}

	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
	}

	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^llms\.txt$',      'index.php?' . self::QUERY_VAR . '=short', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?' . self::QUERY_VAR . '=full',  'top' );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Called on every front-end request. Cheap when not matched.
	 */
	public function maybe_serve(): void {
		$flag = get_query_var( self::QUERY_VAR );
		if ( $flag !== 'short' && $flag !== 'full' ) {
			return;
		}

		$content = $flag === 'full'
			? $this->get_or_build_full()
			: $this->get_or_build_short();

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Generated-By: CiteWP/' . CITEWP_VERSION );

		// Make caches respect the conditional regeneration.
		header( 'Cache-Control: public, max-age=3600' );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional plaintext
		exit;
	}

	private function get_or_build_short(): string {
		$cached = $this->cache->get_short();
		if ( $cached !== null ) {
			return $cached;
		}
		$content = $this->generator->build_short();
		$this->cache->set_short( $content );
		return $content;
	}

	private function get_or_build_full(): string {
		$cached = $this->cache->get_full();
		if ( $cached !== null ) {
			return $cached;
		}
		$content = $this->generator->build_full();
		$this->cache->set_full( $content );
		return $content;
	}

	/**
	 * Called on plugin activation to ensure rewrite rules are flushed in.
	 */
	public static function flush_rewrite_rules_on_activation(): void {
		// Re-register and flush. Add_rewrite_rule must run before flush.
		( new self() )->add_rewrite_rules();
		flush_rewrite_rules();
	}
}
