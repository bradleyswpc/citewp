<?php
/**
 * Shared admin page header with top navigation.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Settings\Page as SettingsPage;

defined( 'ABSPATH' ) || exit;

final class PageHeader {

	/**
	 * Render the top nav bar. Pass the current page's menu slug as $current_page.
	 */
	public static function render_nav( string $current_page ): void {
		$defaults = [
			'dashboard' => [
				'label' => __( 'Dashboard', 'ai-search-optimizer' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG_PARENT ),
				'slug'  => Menu::SLUG_PARENT,
			],
			'settings' => [
				'label' => __( 'Settings', 'ai-search-optimizer' ),
				'url'   => admin_url( 'admin.php?page=' . SettingsPage::SLUG ),
				'slug'  => SettingsPage::SLUG,
			],
			'logs' => [
				'label' => __( 'Crawler Logs', 'ai-search-optimizer' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG_LOGS ),
				'slug'  => Menu::SLUG_LOGS,
			],
			'pro' => [
				'label'    => __( 'Pro ↗', 'ai-search-optimizer' ),
				'url'      => 'https://citewp.com',
				'slug'     => 'pro',
				'external' => true,
			],
		];

		/**
		 * Filters the top navigation items for CiteWP admin pages.
		 *
		 * Each item is an associative array with keys: label (string), url (string),
		 * slug (string), external (bool, optional). The slug is compared against
		 * $current_page to apply the active state.
		 *
		 * @param array<string, array<string, mixed>> $items Navigation items keyed by an arbitrary identifier.
		 */
		$items = apply_filters( 'citewp_aiso/admin/nav', $defaults );

		?>
		<div class="citewp-aiso-header">
			<span class="citewp-aiso-header__wordmark">[CiteWP]</span>
			<nav class="citewp-aiso-nav" aria-label="<?php esc_attr_e( 'CiteWP navigation', 'ai-search-optimizer' ); ?>">
				<?php foreach ( $items as $item ) :
					if ( ! isset( $item['label'], $item['url'], $item['slug'] ) ) {
						continue;
					}
					$is_active = ( $current_page === $item['slug'] );
					$classes   = 'citewp-aiso-nav__item' . ( $is_active ? ' citewp-aiso-nav__item--active' : '' );
					$external  = ! empty( $item['external'] );
					?>
					<a
						href="<?php echo esc_url( $item['url'] ); ?>"
						class="<?php echo esc_attr( $classes ); ?>"
						<?php if ( $external ) : ?>
							target="_blank"
							rel="noopener noreferrer"
						<?php endif; ?>
						<?php if ( $is_active ) : ?>
							aria-current="page"
						<?php endif; ?>
					><?php echo esc_html( $item['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}
}
