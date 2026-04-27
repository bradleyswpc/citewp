<?php
/**
 * Fires only when the plugin is deleted via the WordPress admin.
 * Removes all CiteWP data. Deactivation does NOT trigger this.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop our custom crawler logs table.
$table = $wpdb->prefix . 'citewp_crawler_logs'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script; variables are local to this file's global scope.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall only; table name is hardcoded prefix + constant string.

// Remove plugin options.
delete_option( 'citewp_settings' );
delete_option( 'citewp_llms_settings' );
delete_option( 'citewp_db_version' );

// Remove all CiteWP post meta.
$meta_keys = [ // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script; variables are local to this file's global scope.
	'_citewp_geo_score',
	'_citewp_geo_score_total',
	'_citewp_geo_score_grade',
	'_citewp_geo_score_time',
	'_citewp_exclude_from_llms',
];
foreach ( $meta_keys as $key ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- See above.
	$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $key ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Uninstall cleanup; intentional full-table meta sweep.
}

// Remove cached llms.txt transients.
delete_transient( 'citewp_llms_short' );
delete_transient( 'citewp_llms_full' );

// Clear scheduled cleanup hook.
$next = wp_next_scheduled( 'citewp_daily_cleanup' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script; variables are local to this file's global scope.
if ( $next ) {
	wp_unschedule_event( $next, 'citewp_daily_cleanup' );
}
