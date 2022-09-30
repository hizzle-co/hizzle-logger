<?php

namespace Hizzle\Logger;

/**
 * Displays logs in a list table.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Logs table class.
 */
class List_Table extends \WP_List_Table {

	/**
	 * Logs.
	 *
	 * @var array
	 */
	protected $logs;

	/**
	 * Total logs
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	public $total;

	/**
	 * Per page.
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	public $per_page = 20;

	/**
	 *  Constructor function.
	 */
	public function __construct() {

		parent::__construct(
			array(
				'singular' => 'id',
				'plural'   => 'ids',
			)
		);

		$this->per_page = $this->get_items_per_page( 'hizzle_logs_per_page', 20 );

		$this->process_bulk_action();
		$this->prepare_query();
		$this->prepare_items();
	}

	/**
	 *  Processes a bulk action.
	 */
	public function process_bulk_action() {

		$action = 'bulk-' . $this->_args['plural'];

		if ( empty( $_POST['id'] ) || empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $action ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $this->current_action();

		/**@var Handlers\DB $db */
		$db = Logger::get_instance()->get_handler( 'db' );

		if ( $db && 'delete' === $action ) {
			$db->delete( $_POST['id'] );
		}

		do_action( 'hizzle_logs_process_bulk_action', $action, $this );
	}

	/**
	 *  Prepares the display query
	 */
	public function prepare_query() {
		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->prefix}hizzle_log WHERE 1=1";

		// Add search query.
		if ( ! empty( $_GET['s'] ) ) {
			$search = trim( sanitize_text_field( $_GET['s'] ), '%' );
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$sql   .= $wpdb->prepare( ' AND (`message` LIKE %s OR `context` LIKE %s)', $like, $like );
		}

		// Filter by source.
		if ( ! empty( $_GET['hlog_source'] ) ) {
			$source = sanitize_text_field( $_GET['hlog_source'] );
			$sql   .= $wpdb->prepare( ' AND `source` = %s', $source );
		}

		// Filter by level.
		if ( ! empty( $_GET['hlog_level'] ) && Levels::is_valid_level( sanitize_text_field( $_GET['hlog_level'] ) ) ) {
			$sql .= $wpdb->prepare( ' AND `level` = %d', Levels::get_level_severity( sanitize_text_field( $_GET['hlog_level'] ) ) );
		}

		// Order by.
		$orderby = ! empty( $_GET['orderby'] ) ? esc_sql( sanitize_key( $_GET['orderby'] ) ) : 'timestamp';
		$order   = empty( $_GET['order'] ) || 'desc' === strtolower( $_GET['order'] ) ? 'DESC' : 'ASC';

		$sql .= " ORDER BY `{$orderby}` {$order}";

		// Limit.
		$sql .= $wpdb->prepare( ' LIMIT %d, %d', $this->per_page * ( $this->get_pagenum() - 1 ), $this->per_page );

		$this->items = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->set_pagination_args(
			array(
				'total_items' => $this->total,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $this->total / $this->per_page ),
			)
		);
	}

	/**
	 * Table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'timestamp' => __( 'Timestamp', 'hizzle-logger' ),
			'level'     => __( 'Level', 'hizzle-logger' ),
			'source'    => __( 'Source', 'hizzle-logger' ),
			'message'   => __( 'Message', 'hizzle-logger' ),
			'context'   => __( 'Context', 'hizzle-logger' ),
		);

		/**
		 * Filters the columns shown in the logs list table.
		 *
		 * @param array $columns Coupons table columns.
		 */
		return apply_filters( 'manage_hizzle_logs_table_columns', $columns );
	}

	/**
	 * Table sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'timestamp' => array( 'timestamp', true ),
			'level'     => array( 'level', false ),
			'source'    => array( 'source', true ),
			'message'   => array( 'message', true ),
			'context'   => array( 'context', true ),
		);
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since 1.0.0
	 *
	 * @param object $error_log Error log.
	 */
	public function single_row( $error_log ) {
		$error_log->context = json_decode( $error_log->context, true );

		if ( ! is_array( $error_log->context ) ) {
			$error_log->context = array();
		}

		echo '<tr class="hizzle-log-row hizzle-log-row-' . esc_attr( Levels::get_severity_level( $error_log->level ) ) . '">';
		$this->single_row_columns( $error_log );
		echo '</tr>';
	}

	/**
	 * Default columns.
	 *
	 * @param object $error_log Error log.
	 * @param string $column_name column name.
	 */
	public function column_default( $error_log, $column_name ) {

		$value = '';

		switch ( $column_name ) {
			case 'timestamp':
				$value = date_i18n( 'Y-m-d H:i:s', strtotime( $error_log->timestamp ) + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
				break;
			case 'level':
				$levels = Levels::get_levels();
				$level  = Levels::get_severity_level( $error_log->level );
				$value  = isset( $levels[ $level ] ) ? $levels[ $level ] : $level;
				$value  = '<span class="hizzle-log-level hizzle-log-level-' . esc_attr( $level ) . '">' . esc_html( $value ) . '</span>';
				break;
			case 'source':
				$value = esc_html( $error_log->source );
				break;
			case 'message':
				$value = $error_log->message;
				break;
			case 'context':
				$context = is_array( $error_log->context ) ? $error_log->context : array();

				if ( isset( $context['source'] ) ) {
					unset( $context['source'] );
				}

				$value = '<pre style="overflow: auto;">' . esc_html( json_encode( $context, JSON_PRETTY_PRINT ) ) . '</pre>';
				break;
		}

		return wp_kses_post( apply_filters( "manage_hizzle_logs_table_column_{$column_name}", $value, $error_log ) );

	}

	/**
	 * This is how checkbox column renders.
	 *
	 * @param  object $item item.
	 * @return HTML
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="id[]" value="%d" />', absint( $item->log_id ) );
	}

	/**
	 * [OPTIONAL] Return array of bult actions if has any
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {

		$actions = array(
			'delete' => __( 'Delete', 'hizzle-logger' ),
		);

		/**
		 * Filters the bulk table actions shown on error logs.
		 *
		 * @param array $actions An array of bulk actions.
		 */
		return apply_filters( 'manage_hizzle_logs_table_bulk_actions', $actions );

	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param string $which
	 */
	public function extra_tablenav( $which ) {
		global $wpdb;

		if ( 'top' !== $which ) {
			return;
		}

		$level   = isset( $_GET['hlog_level'] ) ? sanitize_text_field( $_GET['hlog_level'] ) : '';
		$source  = isset( $_GET['hlog_source'] ) ? sanitize_text_field( $_GET['hlog_source'] ) : '';
		$sources = $wpdb->get_col( "SELECT DISTINCT source FROM {$wpdb->prefix}hizzle_log" );
		?>
		<div class="alignleft actions">

			<select name="hlog_source">
				<option value="" <?php selected( empty( $source ) ); ?>><?php esc_html_e( 'Any Source', 'hizzle-logger' ); ?></option>
				<?php foreach ( $sources as $_source ) : ?>
					<option value="<?php echo esc_attr( $_source ); ?>" <?php selected( $_source, $source ); ?>><?php echo esc_html( $_source ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="hlog_level">
				<option value="" <?php selected( empty( $level ) ); ?>><?php esc_html_e( 'Any Level', 'hizzle-logger' ); ?></option>
				<?php foreach ( Levels::get_levels() as $_level => $label ) : ?>
					<option value="<?php echo esc_attr( $_level ); ?>" <?php selected( $_level, $level ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'hizzle-logger' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<style>
			.hizzle-log-level {
				display: inline-block;
				margin-right: 5px;
				padding: 2px 5px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: bold;
				color: #fff;
				background-color: #000;
			}
			.hizzle-log-level-debug {
				background-color: #0074a2;
			}
			.hizzle-log-level-info {
				background-color: #3bafda;
			}
			.hizzle-log-level-notice {
				background-color: #616161;
			}
			.hizzle-log-level-warning {
				background-color: #f0a800;
			}
			.hizzle-log-level-error {
				background-color: #da3b47;
			}
			.hizzle-log-level-critical {
				background-color: #e65100;
			}
			.hizzle-log-level-alert {
				background-color: #c8370a;
			}
			.hizzle-log-level-emergency {
				background-color: #a00c0c;
			}
			.hizzle-log-row-debug .check-column {
				border-left: 3px solid #0074a2;
			}
			.hizzle-log-row-info .check-column {
				border-left: 3px solid #3bafda;
			}
			.hizzle-log-row-notice .check-column {
				border-left: 3px solid #616161;
			}
			.hizzle-log-row-warning .check-column {
				border-left: 3px solid #f0a800;
			}
			.hizzle-log-row-error .check-column {
				border-left: 3px solid #da3b47;
			}
			.hizzle-log-row-critical .check-column {
				border-left: 3px solid #e65100;
			}
			.hizzle-log-row-alert .check-column {
				border-left: 3px solid #c8370a;
			}
			.hizzle-log-row-emergency .check-column {
				border-left: 3px solid #a00c0c;
			}
			.hizzle-log-row .check-column input {
				margin-top: -4px;
			}
			#the-list .hizzle-log-row td,
			#the-list .hizzle-log-row th {
				vertical-align: middle;
			}
			.column-level {
				width: 100px;
			}
			.column-timestamp,
			.column-source {
				width: 140px;
			}
		</style>
		<?php

	}

}
