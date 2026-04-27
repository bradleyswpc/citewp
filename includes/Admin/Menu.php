<?php
/**
 * Admin menu registration.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG_PARENT = 'citewp';
	public const SLUG_LOGS   = 'citewp-aiso-crawler-logs';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'CiteWP', 'citewp' ),
			__( 'CiteWP', 'citewp' ),
			'manage_options',
			self::SLUG_PARENT,
			[ $this, 'render_dashboard' ],
			'dashicons-chart-line',
			81
		);

		add_submenu_page(
			self::SLUG_PARENT,
			__( 'Crawler Logs', 'citewp' ),
			__( 'Crawler Logs', 'citewp' ),
			'manage_options',
			self::SLUG_LOGS,
			[ \CiteWP\Aiso\Plugin::instance()->module( 'admin_logs_page' ), 'render' ]
		);

		// Rename the auto-added duplicate "CiteWP" submenu to "Dashboard".
		global $submenu;
		if ( isset( $submenu[ self::SLUG_PARENT ][0][0] ) ) {
			$submenu[ self::SLUG_PARENT ][0][0] = __( 'Dashboard', 'citewp' );
		}
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CiteWP', 'citewp' ); ?></h1>
			<p><?php esc_html_e( 'Generative Engine Optimization for WordPress.', 'citewp' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG_LOGS ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Crawler Logs', 'citewp' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
