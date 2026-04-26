<?php
/**
 * Crawler logs list table.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Admin;

use CiteWP\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class LogsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			[
				'singular' => 'crawler_log',
				'plural'   => 'crawler_logs',
				'ajax'     => false,
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'detected_at' => __( 'When', 'citewp' ),
			'bot_name'    => __( 'Bot', 'citewp' ),
			'bot_vendor'  => __( 'Vendor', 'citewp' ),
			'request_uri' => __( 'URL', 'citewp' ),
			'ip_address'  => __( 'IP', 'citewp' ),
			'user_agent'  => __( 'User Agent', 'citewp' ),
		];
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return [
			'detected_at' => [ 'detected_at', true ],
			'bot_name'    => [ 'bot_name', false ],
			'bot_vendor'  => [ 'bot_vendor', false ],
		];
	}

	public function prepare_items(): void {
		global $wpdb;

		$per_page = 25;
		$current  = $this->get_pagenum();

		$orderby = $this->validated_orderby();
		$order   = $this->validated_order();

		$table = Schema::table( Schema::TABLE_CRAWLER_LOGS );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$offset = ( $current - 1 ) * $per_page;

		// $orderby/$order are whitelisted by the validator methods, safe to interpolate.
		$query = $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$this->items = $wpdb->get_results( $query, ARRAY_A ) ?: [];

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	private function validated_orderby(): string {
		$allowed = [ 'detected_at', 'bot_name', 'bot_vendor' ];
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'detected_at';
		return in_array( $orderby, $allowed, true ) ? $orderby : 'detected_at';
	}

	private function validated_order(): string {
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		return in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_default( $item, $column_name ): string {
		$value = $item[ $column_name ] ?? '';
		return esc_html( (string) $value );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_detected_at( $item ): string {
		$gmt   = (string) ( $item['detected_at'] ?? '' );
		$local = get_date_from_gmt( $gmt, 'M j, Y g:i a' );
		return esc_html( $local );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_bot_name( $item ): string {
		return '<strong>' . esc_html( (string) $item['bot_name'] ) . '</strong>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_request_uri( $item ): string {
		$uri = (string) ( $item['request_uri'] ?? '' );
		// Truncate long paths in display, keep full value as title attribute.
		$display = mb_strlen( $uri ) > 60 ? mb_substr( $uri, 0, 57 ) . '…' : $uri;
		return sprintf(
			'<code title="%s">%s</code>',
			esc_attr( $uri ),
			esc_html( $display )
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_user_agent( $item ): string {
		$ua      = (string) ( $item['user_agent'] ?? '' );
		$display = mb_strlen( $ua ) > 80 ? mb_substr( $ua, 0, 77 ) . '…' : $ua;
		return sprintf( '<span title="%s">%s</span>', esc_attr( $ua ), esc_html( $display ) );
	}

	public function no_items(): void {
		esc_html_e( 'No crawler activity logged yet.', 'citewp' );
	}
}
