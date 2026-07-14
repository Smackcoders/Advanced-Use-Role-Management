<?php
/**
 * Connector class for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advausro_required_files = array(
	ADVAUSRO_PLUGIN_PATH . 'includes/modules/class-advausro-role.php'        => 'ADVAUSRO_RoleManager',
	ADVAUSRO_PLUGIN_PATH . 'includes/modules/class-advausro-temp-role.php'   => 'ADVAUSRO_TemporaryRoleManager',
	ADVAUSRO_PLUGIN_PATH . 'includes/modules/class-advausro-oauth.php'       => 'ADVAUSRO_OAuth2Login',
	ADVAUSRO_PLUGIN_PATH . 'includes/modules/class-advausro-audit-log.php'   => 'ADVAUSRO_AuditLogPage',
	ADVAUSRO_PLUGIN_PATH . 'includes/modules/class-advausro-timezone.php'    => 'ADVAUSRO_TimezoneManager',
);

foreach ( $advausro_required_files as $path => $class ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.WP.GlobalVariablesOverride.Prohibited -- Local loop variables.
	if ( ! file_exists( $path ) ) {
		\wp_die(
			\esc_html__( 'Plugin error: Missing required file. Please reinstall or contact support.', 'advanced-use-role-management' )
		);
	}
	require_once $path;
}

/**
 * Main connector class for the Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */
class ADVAUSRO_Connector {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $advausro_instance = null;

	/**
	 * Temporary role manager instance.
	 *
	 * @var ADVAUSRO_TemporaryRoleManager
	 */
	private static $advausro_temporary_role_instance;

	/**
	 * Role manager instance.
	 *
	 * @var ADVAUSRO_RoleManager
	 */
	private static $advausro_role_manager_instance;

	/**
	 * OAuth2 login instance.
	 *
	 * @var ADVAUSRO_OAuth2Login
	 */
	private static $advausro_oauth2_login_instance;

	/**
	 * Audit log page instance.
	 *
	 * @var ADVAUSRO_AuditLogPage
	 */
	private static $advausro_audit_log_page_instance;

	/**
	 * Timezone manager instance.
	 *
	 * @var ADVAUSRO_TimezoneManager
	 */
	private static $advausro_timezone_manager_instance;

	/**
	 * Admin menu hook suffixes.
	 *
	 * @var array
	 */
	private $advausro_menu_hooks = array();

	/**
	 * Constructor: sets up hooks and initializes module instances.
	 */
	private function __construct() {
		\add_action( 'admin_menu', array( $this, 'advausro_register_admin_menu' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'advausro_enqueue_admin_assets' ) );
		\add_action( 'admin_init', array( $this, 'advausro_handle_exports' ) );

		$classes = array(
			'ADVAUSRO_TemporaryRoleManager' => 'advausro_temporary_role_instance',
			'ADVAUSRO_RoleManager'          => 'advausro_role_manager_instance',
			'ADVAUSRO_OAuth2Login'          => 'advausro_oauth2_login_instance',
			'ADVAUSRO_AuditLogPage'         => 'advausro_audit_log_page_instance',
			'ADVAUSRO_TimezoneManager'      => 'advausro_timezone_manager_instance',
		);

		foreach ( $classes as $class => $property ) {
			$fqcn = "SmackCoders\\AdvancedUserRoleManager\\$class";

			if ( ! class_exists( $fqcn ) ) {
				/* translators: %s: Name of the missing class. */
				\wp_die( \esc_html( \sprintf( \__( 'Plugin error: Required class %s not found.', 'advanced-use-role-management' ), $class ) ) );
			}

			if ( ! method_exists( $fqcn, 'advausro_get_instance' ) ) {
				/* translators: %s: Name of the class missing the required instance method. */
				\wp_die( \esc_html( \sprintf( \__( 'Plugin error: Missing instance method for %s.', 'advanced-use-role-management' ), $class ) ) );
			}

			self::${$property} = call_user_func( array( $fqcn, 'advausro_get_instance' ) );
		}
	}

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
	 * Register admin menu pages for this plugin.
	 */
	public function advausro_register_admin_menu() {
		$this->advausro_menu_hooks[] = add_menu_page(
			__( 'User Role Manager', 'advanced-use-role-management' ),
			__( 'User Role Manager', 'advanced-use-role-management' ),
			'manage_options',
			'advausro-role-manager',
			array( $this, 'advausro_render_role_manager_page' ),
			'dashicons-groups',
			25
		);

		// Register sub-pages with parent slug to hide them from the sidebar.
		// They remain accessible via the dashboard header links.
		$this->advausro_menu_hooks[] = add_submenu_page(
			'options.php',
			__( 'Add New Role', 'advanced-use-role-management' ),
			__( 'Add Role', 'advanced-use-role-management' ),
			'manage_options',
			'advausro-add-role',
			array( $this, 'advausro_render_add_role_page' )
		);

		$this->advausro_menu_hooks[] = add_submenu_page(
			'options.php',
			__( 'OAuth2 Settings', 'advanced-use-role-management' ),
			__( 'OAuth2 Settings', 'advanced-use-role-management' ),
			'manage_options',
			'advausro-oauth2-settings',
			array( $this, 'advausro_render_oauth2_settings_page' )
		);

		$this->advausro_menu_hooks[] = add_submenu_page(
			'options.php',
			__( 'AURM Audit Logs', 'advanced-use-role-management' ),
			__( 'Audit Logs', 'advanced-use-role-management' ),
			'manage_options',
			'advausro-audit-log',
			array( $this, 'advausro_render_audit_log_page' )
		);

		$this->advausro_menu_hooks[] = add_submenu_page(
			'options.php',
			__( 'Import/Export', 'advanced-use-role-management' ),
			__( 'Import/Export', 'advanced-use-role-management' ),
			'manage_options',
			'advausro-import-export',
			array( $this, 'advausro_render_import_export_page' )
		);
	}

	/**
	 * Render the Import/Export admin page.
	 */
	public function advausro_render_import_export_page() {
		include_once ADVAUSRO_PLUGIN_PATH . 'admin/views/class-advausro-import-export.php';
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function advausro_enqueue_admin_assets( $hook_suffix ) {
		$allowed_pages = array(
			'toplevel_page_advausro-role-manager',
			'admin_page_advausro-add-role',
			'admin_page_advausro-oauth2-settings',
			'admin_page_advausro-audit-log',
			'admin_page_advausro-import-export',
		);

		// Always enqueue styles and scripts for allowed pages.
		if ( in_array( $hook_suffix, $allowed_pages, true ) ) {
			wp_enqueue_style(
				'advausro-admin-style',
				ADVAUSRO_PLUGIN_URL . 'assets/css/styles.css',
				array(),
				filemtime( ADVAUSRO_PLUGIN_PATH . 'assets/css/styles.css' )
			);

			wp_enqueue_script(
				'advausro-admin-script',
				ADVAUSRO_PLUGIN_URL . 'assets/js/scripts.js',
				array( 'jquery' ),
				filemtime( ADVAUSRO_PLUGIN_PATH . 'assets/js/scripts.js' ),
				true
			);

			wp_localize_script(
				'advausro-admin-script',
				'advausro_ajax_object',
				array(
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'advausro_ajax_nonce' ),
					'bulk_nonce' => wp_create_nonce( 'bulk-logs' ),
					'messages'   => array(
						'confirm_delete' => __( 'Are you sure you want to delete the selected log entries? This action cannot be undone.', 'advanced-use-role-management' ),
						'select_action'  => __( 'Please select an action.', 'advanced-use-role-management' ),
						'select_items'   => __( 'Please select at least one log entry.', 'advanced-use-role-management' ),
						'processing'     => __( 'Processing...', 'advanced-use-role-management' ),
					),
				)
			);
		}

		// Enqueue timezone script only when needed.
		if ( 'toplevel_page_advausro-role-manager' === $hook_suffix && get_option( 'advausro_timezone_pending' ) ) {
			wp_enqueue_script(
				'advausro-timezone',
				ADVAUSRO_PLUGIN_URL . 'assets/js/timezone.js',
				array( 'jquery' ),
				filemtime( ADVAUSRO_PLUGIN_PATH . 'assets/js/timezone.js' ),
				true
			);

			wp_localize_script(
				'advausro-timezone',
				'advausro_timezone_object',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'advausro_timezone_nonce' ),
					'redirect_url' => admin_url( 'admin.php?page=advausro-role-manager' ),
				)
			);
		}
	}

	/**
	 * Render the role manager admin page.
	 */
	public function advausro_render_role_manager_page() {
		include_once ADVAUSRO_PLUGIN_PATH . 'admin/views/class-advausro-manager.php';
		include_once ADVAUSRO_PLUGIN_PATH . 'admin/views/class-advausro-timezone.php';
	}

	/**
	 * Render the add role admin page.
	 */
	public function advausro_render_add_role_page() {
		include_once ADVAUSRO_PLUGIN_PATH . 'admin/views/class-advausro-role.php';
	}

	/**
	 * Render the OAuth2 settings admin page.
	 */
	public function advausro_render_oauth2_settings_page() {
		include_once ADVAUSRO_PLUGIN_PATH . 'admin/views/class-advausro-oauth.php';
	}

	/**
	 * Handle export and bulk actions for admin pages.
	 */
	public function advausro_handle_exports() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		// 1. Audit Log Bulk Actions (Export and Delete).
		$log_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ( isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '' );

		if ( ! empty( $log_action ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-logs' ) ) {
			$table_name   = $wpdb->prefix . 'advausro_role_management_logs';
			$advausro_ids = isset( $_POST['bulk-delete'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['bulk-delete'] ) ) : array();

			if ( ! empty( $advausro_ids ) ) {
				if ( 'bulk_export' === $log_action ) {
					$placeholders = implode( ',', array_fill( 0, count( $advausro_ids ), '%d' ) );
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is built from the local WordPress prefix and placeholders are generated dynamically for the sanitized ID list.
					$query = $wpdb->prepare( "SELECT id, action, user_id, log_message, created_at FROM `{$table_name}` WHERE id IN ({$placeholders})", $advausro_ids );
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query string is prepared immediately above for export.
					$logs = $wpdb->get_results( $query );

					if ( ! empty( $logs ) ) {
						$filename = 'advausro_logs_export_' . gmdate( 'YmdHis' ) . '.json';
						if ( ob_get_length() ) {
							ob_end_clean();
						}
						header( 'Content-Type: application/json; charset=utf-8' );
						header( 'Content-Disposition: attachment; filename=' . $filename );
						echo wp_json_encode( $logs, JSON_PRETTY_PRINT );
						exit;
					}
				} elseif ( 'bulk_delete' === $log_action ) {
					$placeholders = implode( ',', array_fill( 0, count( $advausro_ids ), '%d' ) );
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is built from the local WordPress prefix and placeholders are generated dynamically for the sanitized ID list.
					$query = $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE id IN ({$placeholders})", $advausro_ids );
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query string is prepared immediately above for deletion.
					$wpdb->query( $query );
					wp_safe_redirect(
						add_query_arg(
							array(
								'page'    => 'advausro-audit-log',
								'message' => 'bulk_deleted',
								'deleted' => count( $advausro_ids ),
							),
							admin_url( 'admin.php' )
						)
					);
					exit;
				}
			}
		}

		// 2. User Role Assignment Export (Bulk Action).
		$bulk_action_top    = isset( $_POST['bulk_action_top'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action_top'] ) ) : '';
		$bulk_action_bottom = isset( $_POST['bulk_action_bottom'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action_bottom'] ) ) : '';
		$legacy_bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$bulk_action        = ! empty( $bulk_action_top ) ? $bulk_action_top : $bulk_action_bottom;
		if ( empty( $bulk_action ) ) {
			$bulk_action = $legacy_bulk_action;
		}

		if ( 'export' === $bulk_action && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'advausro_role_action_nonce' ) ) {
			$user_ids    = isset( $_POST['user_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['user_ids'] ) ) : array();
			$export_data = array();
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					$export_data[] = array(
						'user_login' => $user->user_login,
						'user_email' => $user->user_email,
						'roles'      => $user->roles,
					);
				}
			}
			if ( ! empty( $export_data ) ) {
				$filename = 'advausro-user-roles-export-' . gmdate( 'Y-m-d' ) . '.json';
				if ( ob_get_length() ) {
					ob_end_clean();
				}
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
				exit;
			}
		}

		// 3. Role Definition Export (Import/Export Page).
		if ( isset( $_POST['advausro_export_roles_action'] ) ) {
			if ( isset( $_POST['advausro_import_export_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['advausro_import_export_nonce'] ) ), 'advausro_import_export_action' ) ) {
				$selected_roles = isset( $_POST['export_roles'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['export_roles'] ) ) : array();
				if ( ! empty( $selected_roles ) ) {
					$export_data = array();
					$roles_table = $wpdb->prefix . 'advausro_custom_roles';
					$caps_table  = $wpdb->prefix . 'advausro_role_capabilities';
					foreach ( $selected_roles as $role_slug ) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is built from the local WordPress prefix.
						$role_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, role_slug, role_name, display_name FROM {$roles_table} WHERE role_slug = %s", $role_slug ), ARRAY_A );
						if ( $role_row ) {
                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is built from the local WordPress prefix.
							$caps          = $wpdb->get_col( $wpdb->prepare( "SELECT capability FROM {$caps_table} WHERE role_id = %d", (int) $role_row['id'] ) );
							$export_data[] = array(
								'role_slug'    => $role_row['role_slug'],
								'role_name'    => $role_row['role_name'],
								'display_name' => $role_row['display_name'],
								'capabilities' => $caps,
								'type'         => 'custom',
							);
						} else {
							$wp_role = get_role( $role_slug );
							if ( $wp_role ) {
								$export_data[] = array(
									'role_slug'    => $role_slug,
									'role_name'    => $role_slug,
									'display_name' => ucwords( $role_slug ),
									'capabilities' => array_keys( $wp_role->capabilities ),
									'type'         => 'default',
								);
							}
						}
					}
					if ( ! empty( $export_data ) ) {
						$filename = 'advausro-roles-export-' . gmdate( 'Y-m-d' ) . '.json';
						if ( ob_get_length() ) {
							ob_end_clean();
						}
						header( 'Content-Type: application/json; charset=utf-8' );
						header( 'Content-Disposition: attachment; filename=' . $filename );
						echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
						exit;
					}
				}
			}
		}
	}

	/**
	 * Render the audit log admin page.
	 */
	public function advausro_render_audit_log_page() {
		include_once ADVAUSRO_PLUGIN_PATH . 'includes/modules/class-advausro-audit-log.php';
		advausro_render_audit_log_page();
	}
}

// Instantiate plugin connector.
ADVAUSRO_Connector::advausro_get_instance();
