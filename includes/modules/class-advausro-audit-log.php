<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Audit log module for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.OneClassPerFile,Generic.Files.OneObjectStructurePerFile.MultipleFound,Universal.Files.SeparateFunctionsFromOO.Mixed
// phpcs:disable WordPress.WP.AlternativeFunctions
namespace SmackCoders\AdvancedUserRoleManager;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include WordPress List Table class.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Include WP_Filesystem.
require_once ABSPATH . 'wp-admin/includes/file.php';

// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Manages audit log page data and rendering for Advanced User Role Manager.
 *
 * @package Advanced_User_Role_Manager
 */
class ADVAUSRO_AuditLogPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $advausro_instance = null;
	/**
	 * Logs database table name.
	 *
	 * @var string
	 */
	private $advausro_logs_table;

	/**
	 * Items per page.
	 *
	 * @var int
	 */
	private $advausro_items_per_page = 20;

	/**
	 * Current page number.
	 *
	 * @var int
	 */
	private $advausro_current_page = 1;

	/**
	 * Total log items count.
	 *
	 * @var int
	 */
	private $advausro_total_items = 0;

	/**
	 * Log entries.
	 *
	 * @var array
	 */
	private $advausro_logs;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return self
	 */
	public static function advausro_get_instance() {
		if ( null === self::$advausro_instance ) {
			self::$advausro_instance = new self();
		}
		return self::$advausro_instance;
	}

	/**
	 * Initialize the audit log page data.
	 */
	public function advausro_init() {
		global $wpdb;
		$this->advausro_logs_table = $wpdb->prefix . 'advausro_role_management_logs';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page navigation parameter for the admin list table.
		$this->advausro_current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$this->advausro_fetch_logs();
	}

	/**
	 * Fetches logs from the database with proper security measures
	 *
	 * @return void
	 */
	private function advausro_fetch_logs() {
		global $wpdb;
		// 🔐 Permission check - ensure user has manage_options capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 🔐 Verify nonce before processing any filters
		$nonce_verified = false;
		if ( is_admin() && isset( $_GET['advausro_filter_nonce'] ) ) {
			$nonce_verified = wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET['advausro_filter_nonce'] ) ),
				'advausro_filter_action'
			);
		}

		// 🛡️ Initialize variables with sanitized values
		$advausro_offset        = absint( ( $this->advausro_current_page - 1 ) * $this->advausro_items_per_page );
		$advausro_where_clauses = array();
		$advausro_args          = array();

		// 🛡️ Use prepare() with proper table name - FIXED: Use prepared statement
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_query = "SELECT id, action, user_id, log_message, created_at FROM {$this->advausro_logs_table}";

		// 🔍 Process filters only if nonce is verified
		if ( $nonce_verified ) {
			// 🔍 Search by keyword in 'action' or 'log_message'
			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
				$search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );
				if ( ! empty( $search_term ) ) {
					$advausro_like_term       = '%' . $wpdb->esc_like( $search_term ) . '%';
					$advausro_where_clauses[] = '(action LIKE %s OR log_message LIKE %s)';
					$advausro_args[]          = $advausro_like_term;
					$advausro_args[]          = $advausro_like_term;
				}
			}

			// 📌 Filter by event type with strict validation
			if ( isset( $_GET['event_type'] ) && ! empty( $_GET['event_type'] ) ) {
				$allowed_actions = array( 'create', 'update', 'delete' ); // Example allowed actions.
				$event_type      = sanitize_key( wp_unslash( $_GET['event_type'] ) );
				if ( in_array( $event_type, $allowed_actions, true ) ) {
					$advausro_where_clauses[] = 'action = %s';
					$advausro_args[]          = $event_type;
				}
			}

			// 📅 Filter by date range with strict validation
			if ( ! empty( $_GET['from_date'] ) && ! empty( $_GET['to_date'] ) ) {
				$from_date = sanitize_text_field( wp_unslash( $_GET['from_date'] ) );
				$to_date   = sanitize_text_field( wp_unslash( $_GET['to_date'] ) );

				// Validate date format (YYYY-MM-DD).
				if (
					preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from_date ) &&
					preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to_date )
				) {
					$advausro_where_clauses[] = 'created_at BETWEEN %s AND %s';
					$advausro_args[]          = $from_date . ' 00:00:00';
					$advausro_args[]          = $to_date . ' 23:59:59';
				}
			}
		}

		// 🧱 Build WHERE clause if filters exist
		if ( ! empty( $advausro_where_clauses ) ) {
			$advausro_query .= ' WHERE ' . implode( ' AND ', $advausro_where_clauses );
		}

		// 🧭 Add ORDER BY and LIMIT with proper parameterization
		$advausro_query   .= ' ORDER BY created_at DESC LIMIT %d, %d';
		$advausro_all_args = array_merge( $advausro_args, array( $advausro_offset, $this->advausro_items_per_page ) );

		// 📊 Prepare count query for pagination - FIXED: Use prepared statement
		$advausro_count_query = "SELECT COUNT(*) FROM {$this->advausro_logs_table}";
		if ( ! empty( $advausro_where_clauses ) ) {
			$advausro_count_query .= ' WHERE ' . implode( ' AND ', $advausro_where_clauses );
		}

		// ✅ Execute queries with proper error handling
		try {
			$this->advausro_logs = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string is prepared immediately here from sanitized parts.
				$wpdb->prepare( $advausro_query, $advausro_all_args )
			);

            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Count query is assembled from validated fragments and prepared when placeholders exist.
			$this->advausro_total_items = $wpdb->get_var(
				! empty( $advausro_args ) ? $wpdb->prepare( $advausro_count_query, $advausro_args ) : $advausro_count_query
			);
		} catch ( Exception $e ) {
			$this->advausro_logs        = array();
			$this->advausro_total_items = 0;
		}
	}

	/**
	 * Get log entries.
	 *
	 * @return array
	 */
	public function advausro_get_logs() {
		return $this->advausro_logs;
	}

	/**
	 * Get total items count.
	 *
	 * @return int
	 */
	public function advausro_get_total_items() {
		return $this->advausro_total_items;
	}

	/**
	 * Get items per page.
	 *
	 * @return int
	 */
	public function advausro_get_items_per_page() {
		return $this->advausro_items_per_page;
	}

	/**
	 * Get current page number.
	 *
	 * @return int
	 */
	public function advausro_get_current_page() {
		return $this->advausro_current_page;
	}

	/**
	 * Get all distinct action types.
	 *
	 * @return array
	 */
	public function advausro_get_all_actions() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
		return $wpdb->get_col( "SELECT DISTINCT action FROM {$this->advausro_logs_table} ORDER BY action ASC" );
	}
}

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * List table class for displaying audit logs.
 *
 * @package Advanced_User_Role_Manager
 */
class ADVAUSRO_Audit_Log_Table extends \WP_List_Table {

	/**
	 * Constructor for ADVAUSRO_Audit_Log_Table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns - Inherited from WP_List_Table.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'id'          => __( 'ID', 'advanced-use-role-management' ),
			'action'      => __( 'Action', 'advanced-use-role-management' ),
			'user_id'     => __( 'User', 'advanced-use-role-management' ),
			'log_message' => __( 'Message', 'advanced-use-role-management' ),
			'created_at'  => __( 'Date', 'advanced-use-role-management' ),
		);
	}

	/**
	 * Get sortable columns - Inherited from WP_List_Table.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'id'         => array( 'id', false ),
			'action'     => array( 'action', true ),
			'user_id'    => array( 'user_id', true ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Get bulk actions - Inherited from WP_List_Table.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'bulk_delete' => __( 'Delete', 'advanced-use-role-management' ),
			'bulk_export' => __( 'Export', 'advanced-use-role-management' ),
		);
	}

	/**
	 * Column default - Inherited from WP_List_Table.
	 *
	 * @param array  $advausro_item        Row data.
	 * @param string $advausro_column_name  Column name.
	 * @return string
	 */
	public function column_default( $advausro_item, $advausro_column_name ) {
		switch ( $advausro_column_name ) {
			case 'id':
				return isset( $advausro_item['ID'] ) ? '<span style="color: #6b7280; font-weight: 500;">#' . esc_html( $advausro_item['ID'] ) . '</span>' : esc_html__( 'N/A', 'advanced-use-role-management' );
			default:
				return isset( $advausro_item[ $advausro_column_name ] ) ? esc_html( $advausro_item[ $advausro_column_name ] ) : '';
		}
	}

	/**
	 * Column checkbox - Inherited from WP_List_Table.
	 *
	 * @param array $advausro_item Row data.
	 * @return string
	 */
	public function column_cb( $advausro_item ) {
		return sprintf( '<input type="checkbox" name="bulk-delete[]" value="%s" />', esc_attr( $advausro_item['ID'] ) );
	}

	/**
	 * Column action - Inherited from WP_List_Table.
	 *
	 * @param array $advausro_item Row data.
	 * @return string
	 */
	public function column_action( $advausro_item ) {
		$advausro_action_labels = array(
			'add_role'            => __( 'Add Role', 'advanced-use-role-management' ),
			'delete_role'         => __( 'Delete Role', 'advanced-use-role-management' ),
			'add_capability'      => __( 'Add Capability', 'advanced-use-role-management' ),
			'delete_capability'   => __( 'Delete Capability', 'advanced-use-role-management' ),
			'clone_role'          => __( 'Clone Role', 'advanced-use-role-management' ),
			'update_capabilities' => __( 'Update Capabilities', 'advanced-use-role-management' ),
			'remove_temp_role'    => __( 'Remove Temporary Role', 'advanced-use-role-management' ),
			'assign_temp_role'    => __( 'Assign Temporary Role', 'advanced-use-role-management' ),
		);

		$advausro_label = $advausro_action_labels[ $advausro_item['action'] ] ?? ucfirst( str_replace( '_', ' ', $advausro_item['action'] ) );
		$advausro_class = 'advausro-action-badge advausro-action-' . sanitize_html_class( $advausro_item['action'] );

		return sprintf( '<span class="%s">%s</span>', esc_attr( $advausro_class ), esc_html( $advausro_label ) );
	}

	/**
	 * Column user ID - Inherited from WP_List_Table.
	 *
	 * @param array $advausro_item Row data.
	 * @return string
	 */
	public function column_user_id( $advausro_item ) {
		$advausro_user_id = isset( $advausro_item['user_id'] ) ? absint( $advausro_item['user_id'] ) : 0;
		if ( $advausro_user_id ) {
			$advausro_user = get_userdata( $advausro_user_id );
			if ( $advausro_user ) {
				return '<strong style="color: #111827; font-weight: 600;">' . esc_html( $advausro_user->display_name ) . '</strong> <span style="color: #6b7280; font-size: 0.875rem;">@' . esc_html( $advausro_user->user_login ) . '</span>';
			}
		}
		return esc_html__( 'Unknown User', 'advanced-use-role-management' );
	}

	/**
	 * Column log message - Inherited from WP_List_Table.
	 *
	 * @param array $advausro_item Row data.
	 * @return string
	 */
	public function column_log_message( $advausro_item ) {
		$advausro_message = esc_html( $advausro_item['log_message'] );
		if ( strlen( $advausro_message ) > 100 ) {
			$advausro_message = substr( $advausro_message, 0, 100 ) . '...';
		}
		return $advausro_message;
	}

	/**
	 * Column created at - Inherited from WP_List_Table.
	 *
	 * @param array $advausro_item Row data.
	 * @return string
	 */
	public function column_created_at( $advausro_item ) {
		// Use WordPress default date and time format options with fallbacks.
		$date_format = get_option( 'date_format', 'F j, Y' );
		$time_format = get_option( 'time_format', 'g:i a' );

		// Ensure we have valid format strings.
		if ( empty( $date_format ) ) {
			$date_format = 'F j, Y';
		}
		if ( empty( $time_format ) ) {
			$time_format = 'g:i a';
		}

		// Check if created_at exists and is valid (TIMESTAMP columns cannot be empty strings).
		if ( ! empty( $advausro_item['created_at'] ) && '0000-00-00 00:00:00' !== $advausro_item['created_at'] && null !== $advausro_item['created_at'] ) {
			$timestamp = strtotime( $advausro_item['created_at'] );
			if ( false !== $timestamp && $timestamp > 0 ) {
				$advausro_date = date_i18n( $date_format . ' ' . $time_format, $timestamp );
				if ( ! empty( $advausro_date ) ) {
					return esc_html( $advausro_date );
				}

				// Fallback to basic date formatting when date_i18n returns empty.
				$advausro_date = gmdate( 'Y-m-d H:i:s', $timestamp );
				return esc_html( $advausro_date );
			}
		}

		// If we get here, the date is invalid or empty.

		return esc_html__( 'N/A', 'advanced-use-role-management' );
	}

	/**
	 * Prepare the list table items.
	 */
	public function advausro_prepare_items() {
		global $wpdb;

		// 🔐 Permission check - ensure user has at least read capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'advanced-use-role-management' ) );
		}

		// 🔐 Nonce verification for AJAX requests
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$advausro_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
			$advausro_nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

			// Verify nonce for specific bulk actions.
			if ( 'bulk_export' === $advausro_action || 'bulk_delete' === $advausro_action ) {
				if ( ! isset( $advausro_nonce ) || ! wp_verify_nonce( $advausro_nonce, 'bulk-logs' ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Nonce verification failed', 'advanced-use-role-management' ) ) );
				}
			}
		}

		// 📋 Setup table columns, hidden columns, and sortable columns
		$advausro_columns      = $this->get_columns();
		$advausro_hidden       = array();
		$advausro_sortable     = $this->get_sortable_columns();
		$this->_column_headers = array( $advausro_columns, $advausro_hidden, $advausro_sortable );

		// 🛡️ Sanitize table name using prefix
		$advausro_table = $wpdb->prefix . 'advausro_role_management_logs';

		// Initialize WHERE clauses and arguments array.
		$advausro_where_clauses = array();
		$advausro_args          = array();

		// 🔍 Search filter - safely handle search term
		$advausro_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( ! empty( $advausro_search ) ) {
			$advausro_like            = '%' . $wpdb->esc_like( $advausro_search ) . '%';
			$advausro_where_clauses[] = '(action LIKE %s OR log_message LIKE %s)';
			$advausro_args[]          = $advausro_like;
			$advausro_args[]          = $advausro_like;
		}

		// 📌 Event type filter - sanitize input
		$advausro_event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';
		if ( ! empty( $advausro_event_type ) ) {
			$advausro_where_clauses[] = 'action = %s';
			$advausro_args[]          = $advausro_event_type;
		}

		// 📅 Date range filter - validate dates
		$advausro_start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$advausro_end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		if ( ! empty( $advausro_start_date ) && ! empty( $advausro_end_date ) ) {
			// Validate date format.
			if (
				preg_match( '/^\d{4}-\d{2}-\d{2}$/', $advausro_start_date ) &&
				preg_match( '/^\d{4}-\d{2}-\d{2}$/', $advausro_end_date )
			) {
				$advausro_where_clauses[] = 'DATE(created_at) BETWEEN %s AND %s';
				$advausro_args[]          = $advausro_start_date;
				$advausro_args[]          = $advausro_end_date;
			}
		}

		// 🧭 Ordering parameters - validate allowed fields and directions
		$advausro_allowed_orderby = array( 'created_at', 'action', 'user_id' );
		$advausro_orderby         = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$advausro_orderby         = in_array( $advausro_orderby, $advausro_allowed_orderby, true ) ? $advausro_orderby : 'created_at';

		$advausro_order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$advausro_order = in_array( $advausro_order, array( 'ASC', 'DESC' ), true ) ? $advausro_order : 'DESC';

		// 📊 Count total items with prepared statement
		$advausro_count_query = "SELECT COUNT(*) FROM {$advausro_table}";
		if ( ! empty( $advausro_where_clauses ) ) {
			$advausro_count_query .= ' WHERE ' . implode( ' AND ', $advausro_where_clauses );
		}

		$advausro_total_items = ! empty( $advausro_args )
			? $wpdb->get_var( $wpdb->prepare( $advausro_count_query, $advausro_args ) ) // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Count query is assembled from validated fragments and prepared immediately here.
			: $wpdb->get_var( $advausro_count_query ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- No placeholders are present when filters are empty.

		// 📄 Pagination configuration
		$advausro_per_page     = 20;
		$advausro_current_page = $this->get_pagenum();
		$advausro_offset       = ( $advausro_current_page - 1 ) * $advausro_per_page;

		// 📥 Fetch paginated data with prepared statement
		$advausro_select_query = "SELECT id AS ID, action, user_id, log_message, created_at FROM {$advausro_table}";
		if ( ! empty( $advausro_where_clauses ) ) {
			$advausro_select_query .= ' WHERE ' . implode( ' AND ', $advausro_where_clauses );
		}

		// 🔐 SECURITY FIX: Build the complete query with validated parameters to prevent SQL injection
		$advausro_all_args   = $advausro_args;
		$advausro_all_args[] = $advausro_per_page;
		$advausro_all_args[] = $advausro_offset;

		// Use validated orderby and order directly in the query since they are already sanitized.
		$advausro_select_query .= " ORDER BY {$advausro_orderby} {$advausro_order} LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string is prepared immediately here from sanitized parts.
		$this->items = $wpdb->get_results( $wpdb->prepare( $advausro_select_query, $advausro_all_args ), ARRAY_A );

		// 🧮 Set pagination arguments
		$this->set_pagination_args(
			array(
				'total_items' => $advausro_total_items,
				'per_page'    => $advausro_per_page,
				'total_pages' => ceil( $advausro_total_items / $advausro_per_page ),
			)
		);
	}
	

}

// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Render the audit log admin page.
 */
function advausro_render_audit_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'advanced-use-role-management' ) );
	}

	global $wpdb;
	$advausro_table_name = $wpdb->prefix . 'advausro_role_management_logs';

	$advausro_action_labels = array(
		'add_role'            => __( 'Add Role', 'advanced-use-role-management' ),
		'delete_role'         => __( 'Delete Role', 'advanced-use-role-management' ),
		'add_capability'      => __( 'Add Capability', 'advanced-use-role-management' ),
		'delete_capability'   => __( 'Delete Capability', 'advanced-use-role-management' ),
		'clone_role'          => __( 'Clone Role', 'advanced-use-role-management' ),
		'update_capabilities' => __( 'Update Capabilities', 'advanced-use-role-management' ),
		'remove_temp_role'    => __( 'Remove Temporary Role', 'advanced-use-role-management' ),
		'assign_temp_role'    => __( 'Assign Temporary Role', 'advanced-use-role-management' ),
	);

	// SQL query without prepare() since there are no placeholders.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
	$advausro_event_types = $wpdb->get_col( "SELECT DISTINCT action FROM {$advausro_table_name} ORDER BY action ASC" );

	if ( empty( $advausro_event_types ) ) {
		$advausro_event_types = array_keys( $advausro_action_labels );
	}

	$advausro_event_types_labeled = array();
	foreach ( $advausro_event_types as $advausro_event ) {
		$advausro_event_types_labeled[ $advausro_event ] = $advausro_action_labels[ $advausro_event ] ?? ucfirst( str_replace( '_', ' ', $advausro_event ) );
	}

	$advausro_list_table = new ADVAUSRO_Audit_Log_Table();
	ob_start();
	// Bulk actions are handled in ADVAUSRO_Connector::advausro_handle_exports().

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status message displayed after a validated admin redirect.
	if ( isset( $_GET['message'] ) && 'bulk_deleted' === sanitize_text_field( wp_unslash( $_GET['message'] ) ) && isset( $_GET['deleted'] ) ) {
		$advausro_deleted_count = absint( wp_unslash( $_GET['deleted'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status count displayed after a validated admin redirect.
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: The number of logs deleted */
					_n(
						'Successfully deleted %d log.',
						'Successfully deleted %d logs.',
						$advausro_deleted_count,
						'advanced-use-role-management'
					),
					$advausro_deleted_count
				)
			)
		);
	}

	// Bulk export is handled in ADVAUSRO_Connector::advausro_handle_exports().

	$advausro_list_table->advausro_prepare_items();
	ob_end_clean();
	?>
	<?php include_once ADVAUSRO_PLUGIN_PATH . 'admin/views/advausro-header.php'; ?>
	<div class="wrap crm-container">

		<?php if ( empty( $advausro_event_types ) && current_user_can( 'read' ) ) : ?>
			<div class="notice notice-warning is-dismissible">
				<p><?php esc_html_e( 'No actions found in the audit log table. Using fallback actions.', 'advanced-use-role-management' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="crm-box" style="padding: 24px; border-radius: 12px; background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
			<form method="get" id="advausro-log-filter-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['page'] ?? 'advausro-audit-log' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request value preserved in the admin filter form. ?>" />
				<?php wp_nonce_field( 'advausro_filter_action', 'advausro_filter_nonce' ); ?>

				<div class="advausro-audit-filter-row" style="display: flex; flex-wrap: nowrap; align-items: center; gap: 12px; border-bottom: 1px solid #f3f4f6; padding-bottom: 20px; width: 100%;">
					<div style="position: relative; display: flex; align-items: center; flex: 1 1 140px; min-width: 80px;">
						<select name="event_type" id="event-type-filter" class="advausro-audit-event-type-select" style="height: 40px; width: 100%; min-width: 0; border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; font-size: 0.875rem; color: #374151; padding: 0 12px; outline: none;">
							<option value=""><?php esc_html_e( 'All Actions', 'advanced-use-role-management' ); ?></option>
							<?php foreach ( $advausro_event_types_labeled as $advausro_event => $advausro_label ) : ?>
								<option value="<?php echo esc_attr( $advausro_event ); ?>" <?php selected( sanitize_text_field( wp_unslash( $_REQUEST['event_type'] ?? '' ) ), $advausro_event ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request value preserved in the admin filter form. ?>>
									<?php echo esc_html( $advausro_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div style="position: relative; display: flex; align-items: center; flex: 1 1 130px; min-width: 100px;">
						<input type="date" name="start_date" id="start-date-filter" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['start_date'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request value preserved in the admin filter form. ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 40px; padding: 0 12px; font-size: 0.875rem; color: #374151; width: 100%; min-width: 0;">
					</div>

					<div style="position: relative; display: flex; align-items: center; flex: 1 1 130px; min-width: 100px;">
						<input type="date" name="end_date" id="end-date-filter" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['end_date'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request value preserved in the admin filter form. ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 40px; padding: 0 12px; font-size: 0.875rem; color: #374151; width: 100%; min-width: 0;">
					</div>

					<button type="submit" name="action" value="advausro_filter_action" class="crm-btn" style="flex: 0 0 auto; min-width: 90px; height: 40px; display: inline-flex; justify-content: center; align-items: center; gap: 6px; margin: 0; border: none; border-radius: 8px; background: #6366f1; color: #fff; font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif; padding: 0 16px; cursor: pointer; transition: background 0.15s ease;">
						<?php esc_html_e( 'Filter', 'advanced-use-role-management' ); ?>
					</button>

					<?php if ( ! empty( $_REQUEST['event_type'] ) || ! empty( $_REQUEST['start_date'] ) || ! empty( $_REQUEST['end_date'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request values control reset-link visibility. ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=advausro-audit-log' ) ); ?>" style="color: #6b7280; font-size: 0.875rem; text-decoration: underline; margin-left: 0; flex-shrink: 0; white-space: nowrap;">
							<?php esc_html_e( 'Reset', 'advanced-use-role-management' ); ?>
						</a>
					<?php endif; ?>

					<div style="position: relative; display: flex; align-items: center; flex: 1 1 240px; max-width: 240px; min-width: 120px; margin-left: auto;">
						<input type="search" id="advausro-log-search-input" name="s" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request value preserved in the admin filter form. ?>" placeholder="<?php esc_attr_e( 'Search logs...', 'advanced-use-role-management' ); ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 40px; padding: 0 12px 0 36px; font-size: 0.875rem; color: #374151; width: 100%; min-width: 0; box-sizing: border-box;">
						<span class="dashicons dashicons-search" style="position: absolute; left: 10px; color: #9ca3af; font-size: 16px;"></span>
					</div>
				</div>
			</form>

			<form method="post" id="advausro-log-table-form" class="advausro-audit-log-table-form" style="background: #ffffff; border-radius: 8px;">
				<?php wp_nonce_field( 'bulk-logs', '_wpnonce' ); ?>
				<div id="advausro-log-table-container">
					<?php $advausro_list_table->display(); ?>
				</div>
			</form>
		</div>
	</div>
	<?php
}

// Redundant admin menu registration was removed.

/**
 * AJAX callback for searching audit logs.
 */
function advausro_search_logs_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
		wp_die();
	}
	check_ajax_referer( 'advausro_ajax_nonce', 'filter_nonce' );

	global $wpdb;
	$advausro_table_name = $wpdb->prefix . 'advausro_role_management_logs';
	$advausro_list_table = new ADVAUSRO_Audit_Log_Table();

	if ( isset( $_POST['s'] ) ) {
		$_REQUEST['s'] = sanitize_text_field( wp_unslash( $_POST['s'] ) );
	}
	if ( isset( $_POST['event_type'] ) ) {
		$_REQUEST['event_type'] = sanitize_text_field( wp_unslash( $_POST['event_type'] ) );
	}
	if ( isset( $_POST['start_date'] ) ) {
		$_REQUEST['start_date'] = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
	}
	if ( isset( $_POST['end_date'] ) ) {
		$_REQUEST['end_date'] = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
	}

	ob_start();
	$advausro_list_table->advausro_prepare_items();
	$advausro_list_table->display();
	$advausro_table_html = ob_get_clean();

	wp_send_json_success(
		array(
			'table' => $advausro_table_html,
		)
	);
}

add_action( 'wp_ajax_advausro_search_logs', 'SmackCoders\\AdvancedUserRoleManager\\advausro_search_logs_callback' );

/**
 * Fix existing audit log entries with empty created_at values.
 * This function will be called once to update any existing records.
 */
function advausro_fix_empty_audit_log_dates() {
	// Only run this fix once per session to avoid unnecessary database queries.
	static $fix_applied = false;
	if ( $fix_applied ) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'advausro_role_management_logs';

	// Check if the table exists before trying to update it.
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table_name
		)
	);

	if ( ! $table_exists ) {
		// Table does not exist, mark as applied to avoid repeated checks.
		$fix_applied = true;
		return;
	}

	// For TIMESTAMP columns, we can only check for NULL and '0000-00-00 00:00:00'.
	// Empty strings are not valid for TIMESTAMP columns.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
	$result = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table_name} SET created_at = %s WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'",
			current_time( 'mysql' )
		)
	);

	// Mark as applied regardless of result.
	$fix_applied = true;
}

// Run the fix once when the plugin is loaded.
add_action( 'init', 'SmackCoders\\AdvancedUserRoleManager\\advausro_fix_empty_audit_log_dates' );
