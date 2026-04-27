<?php
/**
 * Enqueues the compiled Gutenberg sidebar in the block editor.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

defined( 'ABSPATH' ) || exit;

final class EditorAssets {

	public function register(): void {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		$asset_file = CITEWP_AISO_PLUGIN_DIR . 'build/index.asset.php';

		// If JS hasn't been built yet (e.g. fresh checkout), bail silently —
		// the PHP scoring still works, the sidebar just won't load.
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'citewp-sidebar',
			CITEWP_AISO_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'] ?? [],
			$asset['version']      ?? CITEWP_AISO_VERSION,
			true
		);
	}
}
