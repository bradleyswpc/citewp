<?php
/**
 * Crawler logs list table.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class LogsTable extends \WP_List_Table {

	/** Populated in prepare_items(); used by extra_tablenav() for the bot dropdown. */
	private array $distinct_bots = [];

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

		$table = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );

		// Load distinct bots before filter validation (validator checks against this list).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Filter dropdown; $table is esc_sql() of hardcoded constant. Real-time, intentionally uncached.
		$rows                = $wpdb->get_col( "SELECT DISTINCT bot_name FROM {$table} ORDER BY bot_name ASC" );
		$this->distinct_bots = is_array( $rows ) ? $rows : [];

		$per_page   = 25;
		$current    = $this->get_pagenum();
		$orderby    = $this->validated_orderby();
		$order      = $this->validated_order();
		$bot_filter = $this->validated_bot_filter();
		$since      = $this->range_to_since( $this->validated_range_filter() );

		$where       = '';
		$filter_args = [];

		if ( $bot_filter !== '' ) {
			$where        .= ' AND bot_name = %s';
			$filter_args[] = $bot_filter;
		}
		if ( $since !== null ) {
			$where        .= ' AND detected_at >= %s';
			$filter_args[] = $since;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table queries; $table is esc_sql() of a hardcoded constant, $where/$orderby/$order built from whitelisted values. Real-time admin data.
		if ( ! empty( $filter_args ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}", ...$filter_args )
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		$offset    = ( $current - 1 ) * $per_page;
		$data_args = array_merge( $filter_args, [ $per_page, $offset ] );

		if ( ! empty( $filter_args ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE 1=1 {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				...$data_args
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$this->items = $wpdb->get_results( $query, ARRAY_A ) ?: [];
		// phpcs:enable

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	protected function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		$current_bot   = $this->validated_bot_filter();
		$current_range = $this->validated_range_filter();
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="citewp_aiso_bot_filter">
				<?php esc_html_e( 'Filter by bot', 'citewp' ); ?>
			</label>
			<select id="citewp_aiso_bot_filter" name="citewp_aiso_bot">
				<option value=""><?php esc_html_e( 'All bots', 'citewp' ); ?></option>
				<?php foreach ( $this->distinct_bots as $bot ) : ?>
					<option value="<?php echo esc_attr( $bot ); ?>" <?php selected( $current_bot, $bot ); ?>>
						<?php echo esc_html( $bot ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="citewp_aiso_range_filter">
				<?php esc_html_e( 'Filter by date range', 'citewp' ); ?>
			</label>
			<select id="citewp_aiso_range_filter" name="citewp_aiso_range">
				<option value=""><?php esc_html_e( 'All time', 'citewp' ); ?></option>
				<option value="24h" <?php selected( $current_range, '24h' ); ?>><?php esc_html_e( 'Last 24 hours', 'citewp' ); ?></option>
				<option value="7d"  <?php selected( $current_range, '7d' );  ?>><?php esc_html_e( 'Last 7 days', 'citewp' ); ?></option>
				<option value="30d" <?php selected( $current_range, '30d' ); ?>><?php esc_html_e( 'Last 30 days', 'citewp' ); ?></option>
			</select>

			<?php submit_button( __( 'Filter', 'citewp' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	private function validated_orderby(): string {
		$allowed = [ 'detected_at', 'bot_name', 'bot_vendor' ];
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'detected_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only WP_List_Table sort param; no data modification.
		return in_array( $orderby, $allowed, true ) ? $orderby : 'detected_at';
	}

	private function validated_order(): string {
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only WP_List_Table sort direction; no data modification.
		return in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';
	}

	private function validated_bot_filter(): string {
		$bot = isset( $_GET['citewp_aiso_bot'] ) ? sanitize_text_field( wp_unslash( $_GET['citewp_aiso_bot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter param; validated against DB values below.
		return in_array( $bot, $this->distinct_bots, true ) ? $bot : '';
	}

	private function validated_range_filter(): string {
		$range = isset( $_GET['citewp_aiso_range'] ) ? sanitize_key( wp_unslash( $_GET['citewp_aiso_range'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter param; validated against a whitelist below.
		return in_array( $range, [ '24h', '7d', '30d' ], true ) ? $range : '';
	}

	private function range_to_since( string $range ): ?string {
		return match ( $range ) {
			'24h'   => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'7d'    => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
			'30d'   => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
			default => null,
		};
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
		$uri     = (string) ( $item['request_uri'] ?? '' );
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
		esc_html_e( 'No crawler activity matches your filters.', 'citewp' );
	}
}
