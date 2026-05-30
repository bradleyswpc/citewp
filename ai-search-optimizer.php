<?php
/**
 * Plugin Name:       CiteWP AI Search Optimizer – Optimize Content for AI Engines
 * Plugin URI:        https://citewp.com/ai-search-optimizer
 * Description:       Optimize WordPress content for AI search engines like ChatGPT, Claude, Perplexity, and Gemini. Includes AI crawler detection, llms.txt generation, and the Cite Score — a transparent 100-point GEO score for content citability.
 * Version:           0.7.10
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            CiteWP
 * Author URI:        https://citewp.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       citewp-ai-search-optimizer
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'CITEWP_AISO_VERSION', '0.7.10' );
define( 'CITEWP_AISO_PLUGIN_FILE', __FILE__ );
define( 'CITEWP_AISO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CITEWP_AISO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CITEWP_AISO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CITEWP_AISO_DB_VERSION', '1' );

// ---------------------------------------------------------------------------
// PSR-4 style autoloader (namespace: CiteWP\Aiso\)
// ---------------------------------------------------------------------------
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'CiteWP\\Aiso\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		// CiteWP\Aiso\Crawler\Detector -> includes/Crawler/Detector.php
		$path = CITEWP_AISO_PLUGIN_DIR . 'includes/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

// ---------------------------------------------------------------------------
// Activation / Deactivation / Uninstall
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, [ \CiteWP\Aiso\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CiteWP\Aiso\Plugin::class, 'deactivate' ] );

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------
add_action(
	'plugins_loaded',
	static function (): void {
		\CiteWP\Aiso\Plugin::instance()->boot();
	}
);
