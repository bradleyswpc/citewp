<?php
/**
 * Database schema management.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const TABLE_CRAWLER_LOGS = 'citewp_crawler_logs';

	/**
	 * Full table name including WP prefix.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Run on activation. Idempotent — dbDelta only applies diffs.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table( self::TABLE_CRAWLER_LOGS );

		// Note: dbDelta is finicky — KEY definitions must be on their own lines,
		// two spaces after PRIMARY KEY, no backticks around column names in keys.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			detected_at DATETIME NOT NULL,
			bot_name VARCHAR(64) NOT NULL,
			bot_vendor VARCHAR(64) NOT NULL,
			user_agent VARCHAR(512) NOT NULL,
			ip_address VARCHAR(45) NOT NULL,
			request_uri VARCHAR(512) NOT NULL,
			http_status SMALLINT UNSIGNED NULL,
			referer VARCHAR(512) NULL,
			PRIMARY KEY  (id),
			KEY detected_at (detected_at),
			KEY bot_name (bot_name),
			KEY bot_vendor (bot_vendor)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Called on every boot. Compares stored DB version to constant
	 * and re-runs install() if they differ. Cheap when versions match.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'citewp_db_version' );
		if ( $installed === CITEWP_DB_VERSION ) {
			return;
		}
		self::install();
		update_option( 'citewp_db_version', CITEWP_DB_VERSION );
	}
}
