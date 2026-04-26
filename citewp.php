<?php
/**
 * Plugin Name:       CiteWP
 * Plugin URI:        https://citewp.com
 * Description:       Generative Engine Optimization for WordPress. Detect AI crawlers, generate llms.txt, and score content for AI citability.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            CiteWP
 * Author URI:        https://citewp.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       citewp
 * Domain Path:       /languages
 *
 * @package CiteWP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'CITEWP_VERSION', '0.2.0' );
define( 'CITEWP_PLUGIN_FILE', __FILE__ );
define( 'CITEWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CITEWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CITEWP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CITEWP_DB_VERSION', '1' );

// ---------------------------------------------------------------------------
// PSR-4 style autoloader (namespace: CiteWP\)
// ---------------------------------------------------------------------------
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'CiteWP\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		// CiteWP\Crawler\Detector -> includes/Crawler/Detector.php
		$path = CITEWP_PLUGIN_DIR . 'includes/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

// ---------------------------------------------------------------------------
// Activation / Deactivation / Uninstall
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, [ \CiteWP\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CiteWP\Plugin::class, 'deactivate' ] );

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------
add_action(
	'plugins_loaded',
	static function (): void {
		\CiteWP\Plugin::instance()->boot();
	}
);
