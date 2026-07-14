<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Role manager module for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Manages custom WordPress user roles and capabilities.
 *
 * @package Advanced_User_Role_Manager
 */
class ADVAUSRO_RoleManager {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $advausro_instance = null;

	/**
	 * Constructor: registers all AJAX and hook handlers.
	 */
	private function __construct() {
		add_action( 'wp_ajax_advausro_add_new_custom_role', array( $this, 'advausro_add_new_custom_role' ) );
		add_action( 'wp_ajax_advausro_get_custom_roles', array( $this, 'advausro_get_custom_roles_for_dropdown' ) );
		add_action( 'wp_ajax_advausro_delete_custom_roles', array( $this, 'advausro_delete_custom_roles' ) );
		add_action( 'wp_ajax_advausro_add_new_capability', array( $this, 'advausro_add_new_capability' ) );
		add_action( 'wp_ajax_advausro_update_role_capabilities', array( $this, 'advausro_update_role_capabilities' ) );
		add_action( 'wp_ajax_advausro_delete_custom_capabilities', array( $this, 'advausro_delete_custom_capabilities' ) );
		add_action( 'wp_ajax_advausro_clone_role', array( $this, 'advausro_clone_role' ) );
		add_action( 'wp_ajax_advausro_fetch_role_capabilities', array( $this, 'advausro_fetch_role_capabilities' ) );
		add_action( 'wp_ajax_advausro_fetch_custom_capabilities', array( $this, 'advausro_fetch_custom_capabilities' ) );
		add_action( 'edit_user_profile_update', array( $this, 'advausro_assign_custom_role_capabilities' ) );
		add_action( 'personal_options_update', array( $this, 'advausro_assign_custom_role_capabilities' ) );
		add_action( 'user_register', array( $this, 'advausro_assign_custom_role_capabilities' ) );
		add_action( 'wp_ajax_advausro_bulk_action', array( $this, 'advausro_process_bulk_action' ) );
		add_action( 'init', array( $this, 'advausro_register_and_sync_custom_roles' ) );
		add_action( 'restrict_manage_users', array( $this, 'advausro_add_role_filter_to_users_list' ) );
		add_filter( 'pre_get_users', array( $this, 'advausro_filter_users_by_role_native' ) );
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
	 * Register and sync custom roles with WordPress.
	 */
	public function advausro_register_and_sync_custom_roles() {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		global $wpdb;

		$advausro_roles_table = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_caps_table  = $wpdb->prefix . 'advausro_role_capabilities';

		if (
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $advausro_roles_table ) ) !== $advausro_roles_table ||
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $advausro_caps_table ) ) !== $advausro_caps_table
		) {
			return;
		}

		// Get all custom roles - FIXED: Use prepared statement.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_roles = $wpdb->get_results( "SELECT id, role_slug, display_name FROM {$advausro_roles_table}", ARRAY_A );

		foreach ( $advausro_roles as $advausro_role ) {
			// Get capabilities for this role - FIXED: Use prepared statement.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
			$custom_caps = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT capability FROM {$advausro_caps_table} WHERE role_id = %d",
					$advausro_role['id']
				)
			);

			$custom_caps                       = $this->advausro_normalize_capabilities( $custom_caps );
			$advausro_caps_array               = array_fill_keys( $custom_caps, true );
			$advausro_caps_array['read']       = true;
			$advausro_caps_array['edit_posts'] = true;

			// Register or update the role.
			if ( ! get_role( $advausro_role['role_slug'] ) ) {
				add_role( $advausro_role['role_slug'], $advausro_role['display_name'], $advausro_caps_array );
			} else {
				$advausro_wp_role = get_role( $advausro_role['role_slug'] );

				// Add capabilities that should be present.
				foreach ( $advausro_caps_array as $advausro_cap => $grant ) {
					if ( ! $advausro_wp_role->has_cap( $advausro_cap ) ) {
						$advausro_wp_role->add_cap( $advausro_cap );
					}
				}

				// Remove only capabilities controlled by this plugin.
				foreach ( array_keys( $advausro_wp_role->capabilities ) as $existing_cap ) {
					if (
						'read' !== $existing_cap &&
						'edit_posts' !== $existing_cap &&
						! isset( $advausro_caps_array[ $existing_cap ] )
					) {
						$advausro_wp_role->remove_cap( $existing_cap );
					}
				}
			}
		}
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Assign custom role capabilities to a user.
	 *
	 * @param int $advausro_user_id User ID.
	 */
	public function advausro_assign_custom_role_capabilities( $advausro_user_id ) {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Check user capabilities and registration context.
		if ( ! current_user_can( 'edit_user', $advausro_user_id ) && did_action( 'user_register' ) !== 1 ) {
			return;
		}

		// Get user object.
		$advausro_user = get_user_by( 'id', $advausro_user_id );
		if ( ! $advausro_user ) {
			return;
		}

		// Get user's primary role.
		$advausro_role_slug = $advausro_user->roles[0] ?? '';
		if ( empty( $advausro_role_slug ) ) {
			return;
		}

		global $wpdb;
		$advausro_roles_table = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_caps_table  = $wpdb->prefix . 'advausro_role_capabilities';

		// First check if tables exist.
		if (
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $advausro_roles_table ) ) !== $advausro_roles_table ||
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $advausro_caps_table ) ) !== $advausro_caps_table
		) {
			// Tables don't exist - this is normal for standard WordPress roles.
			return;
		}

		// Check if this is a custom role - FIXED: Use prepared statement.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_role_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$advausro_roles_table} WHERE role_slug = %s",
				$advausro_role_slug
			)
		);

		if ( ! $advausro_role_data ) {
			// Not a custom role - exit gracefully.
			return;
		}

		// Get capabilities for this role - FIXED: Use prepared statement.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_capabilities = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT capability FROM {$advausro_caps_table} WHERE role_id = %d",
				$advausro_role_data->id
			)
		);

		// Assign capabilities to user.
		foreach ( $advausro_capabilities as $advausro_cap ) {
			if ( ! $advausro_user->has_cap( $advausro_cap ) ) {
				$advausro_user->add_cap( $advausro_cap );
			}
		}

		// Ensure basic capabilities.
		foreach ( array( 'read', 'edit_posts' ) as $advausro_basic ) {
			if ( ! $advausro_user->has_cap( $advausro_basic ) ) {
				$advausro_user->add_cap( $advausro_basic );
			}
		}
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Log a role management action.
	 *
	 * @param string $advausro_action       Action type.
	 * @param string $advausro_log_message  Log message.
	 */
	public function advausro_log( $advausro_action, $advausro_log_message ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		global $wpdb;
		$advausro_table_logs = $wpdb->prefix . 'advausro_role_management_logs';

		$wpdb->insert(
			$advausro_table_logs,
			array(
				'action'      => sanitize_text_field( $advausro_action ),
				'user_id'     => get_current_user_id(),
				'log_message' => sanitize_textarea_field( $advausro_log_message ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * AJAX handler: add a new custom role.
	 */
	public function advausro_add_new_custom_role() {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		global $wpdb;

		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_role_name    = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
		$advausro_display_name = isset( $_POST['role_display'] ) ? sanitize_text_field( wp_unslash( $_POST['role_display'] ) ) : '';
		$advausro_capabilities = isset( $_POST['capabilities'] ) ?
			array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['capabilities'] ) ) :
			array();

		$advausro_capabilities = $this->advausro_normalize_capabilities( $advausro_capabilities );

		if ( empty( $advausro_role_name ) || empty( $advausro_display_name ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Role Name and Display Name are required.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_role_slug = sanitize_title( $advausro_role_name );
		if ( empty( $advausro_role_slug ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'A valid role slug is required.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_table_roles        = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_table_capabilities = $wpdb->prefix . 'advausro_role_capabilities';

		// Check if role already exists - FIXED: Use prepared statement.
		$advausro_existing = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$wpdb->prepare( "SELECT id FROM {$advausro_table_roles} WHERE role_slug = %s", $advausro_role_slug )
		);

		if ( $advausro_existing ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Role already exists!', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		if ( get_role( $advausro_role_slug ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'A WordPress role with this slug already exists.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		// Insert new role.
		$advausro_inserted = $wpdb->insert(
			$advausro_table_roles,
			array(
				'role_slug'    => $advausro_role_slug,
				'role_name'    => $advausro_role_name,
				'display_name' => $advausro_display_name,
			),
			array( '%s', '%s', '%s' )
		);

		if ( ! $advausro_inserted ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Database error: Role could not be created.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_role_id = $wpdb->insert_id;

		// Create the WordPress role with initial capabilities.
		$advausro_initial_caps               = array_fill_keys( $advausro_capabilities, true );
		$advausro_initial_caps['read']       = true;
		$advausro_initial_caps['edit_posts'] = true;
		add_role( $advausro_role_slug, $advausro_display_name, $advausro_initial_caps );

		// Add capabilities to our custom table.
		if ( ! empty( $advausro_capabilities ) ) {
			foreach ( $advausro_capabilities as $advausro_capability ) {
				$wpdb->insert(
					$advausro_table_capabilities,
					array(
						'role_id'    => $advausro_role_id,
						'capability' => $advausro_capability,
					),
					array( '%d', '%s' )
				);
			}
		}

		$this->advausro_log(
			'add_role',
			sprintf(
				'Created new role: %s (Slug: %s)',
				$advausro_display_name,
				$advausro_role_slug
			)
		);

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Role created successfully!', 'advanced-use-role-management' ),
				'role_id'      => $advausro_role_id,
				'role_slug'    => $advausro_role_slug,
				'role_name'    => $advausro_role_name,
				'display_name' => $advausro_display_name,
			)
		);
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * AJAX handler: delete custom roles.
	 */
	public function advausro_delete_custom_roles() {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		global $wpdb;

		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_table_roles        = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_table_capabilities = $wpdb->prefix . 'advausro_role_capabilities';

		$advausro_role_slugs = isset( $_POST['roles'] ) ?
			array_map( 'sanitize_text_field', json_decode( sanitize_textarea_field( wp_unslash( $_POST['roles'] ) ), true ) ) :
			array();

		if ( empty( $advausro_role_slugs ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No roles selected', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		// Filter out protected roles from deletion list for security.
		$protected_roles     = $this->advausro_get_protected_roles();
		$advausro_role_slugs = array_diff( $advausro_role_slugs, $protected_roles );

		if ( empty( $advausro_role_slugs ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Selected protected roles cannot be deleted.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		foreach ( $advausro_role_slugs as $advausro_role_slug ) {
			$advausro_role_id = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
				$wpdb->prepare( "SELECT id FROM {$advausro_table_roles} WHERE role_slug = %s", $advausro_role_slug )
			);

			if ( ! $advausro_role_id ) {
				continue;
			}

			// Remove WordPress role if it exists.
			if ( get_role( $advausro_role_slug ) ) {
				remove_role( $advausro_role_slug );
			}

			// Delete capabilities.
			$wpdb->delete( $advausro_table_capabilities, array( 'role_id' => $advausro_role_id ), array( '%d' ) );

			// Delete role.
			$wpdb->delete( $advausro_table_roles, array( 'id' => $advausro_role_id ), array( '%d' ) );

			$this->advausro_log(
				'delete_role',
				sprintf( 'Deleted custom role: %s by user ID %d', $advausro_role_slug, get_current_user_id() )
			);
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Roles deleted successfully!', 'advanced-use-role-management' ) ) );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * AJAX handler: get custom roles for a dropdown.
	 */
	public function advausro_get_custom_roles_for_dropdown() {
		global $wpdb;

		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		// Get WordPress roles.
		$wp_roles  = wp_roles()->roles;
		$all_roles = array();

		// Add ALL WordPress roles.
		foreach ( $wp_roles as $role_slug => $role_data ) {
			$all_roles[] = array(
				'role_slug'    => $role_slug,
				'display_name' => $role_data['name'],
			);
		}

		// Sort roles by display_name.
		usort(
			$all_roles,
			function ( $a, $b ) {
				return strcmp( $a['display_name'], $b['display_name'] );
			}
		);

		wp_send_json_success( array( 'roles' => $all_roles ) );
	}

	/**
	 * AJAX handler: add a new custom capability.
	 */
	public function advausro_add_new_capability() {
		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$capability = isset( $_POST['capability'] ) ? sanitize_text_field( wp_unslash( $_POST['capability'] ) ) : '';

		if ( empty( $capability ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Capability name is required.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$custom_capabilities = get_option( 'advausro_custom_capabilities', array() );

		if ( ! is_array( $custom_capabilities ) ) {
			$custom_capabilities = array();
		}

		if ( in_array( $capability, $custom_capabilities, true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Capability already exists.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$custom_capabilities[] = $capability;
		update_option( 'advausro_custom_capabilities', $custom_capabilities );

		$this->advausro_log(
			'add_capability',
			sprintf( "Capability '%s' was added by user ID %d", $capability, get_current_user_id() )
		);

		wp_send_json_success(
			array(
				'message'    => esc_html__( 'Capability added successfully!', 'advanced-use-role-management' ),
				'capability' => $capability,
			)
		);
	}

	/**
	 * AJAX handler: fetch all custom capabilities.
	 */
	public function advausro_fetch_custom_capabilities() {
		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$custom_capabilities = get_option( 'advausro_custom_capabilities', array() );
		wp_send_json_success( array( 'capabilities' => $custom_capabilities ) );
	}

	/**
	 * AJAX handler: delete custom capabilities.
	 */
	public function advausro_delete_custom_capabilities() {
		global $wpdb;

		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_capabilities = isset( $_POST['capabilities'] ) ?
			array_map( 'sanitize_text_field', json_decode( sanitize_textarea_field( wp_unslash( $_POST['capabilities'] ) ), true ) ) :
			array();

		if ( empty( $advausro_capabilities ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No capabilities selected', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_custom_capabilities  = get_option( 'advausro_custom_capabilities', array() );
		$advausro_updated_capabilities = array_diff( $advausro_custom_capabilities, $advausro_capabilities );
		update_option( 'advausro_custom_capabilities', array_values( $advausro_updated_capabilities ) );

		$advausro_table_capabilities = $wpdb->prefix . 'advausro_role_capabilities';
		$advausro_wp_roles           = wp_roles();
		foreach ( $advausro_capabilities as $advausro_capability ) {
			$wpdb->delete( $advausro_table_capabilities, array( 'capability' => $advausro_capability ), array( '%s' ) );

			// Remove from all WordPress roles.
			foreach ( $advausro_wp_roles->role_objects as $advausro_role_obj ) {
				if ( $advausro_role_obj->has_cap( $advausro_capability ) ) {
					$advausro_role_obj->remove_cap( $advausro_capability );
				}
			}
		}

		$this->advausro_log(
			'delete_capability',
			sprintf( 'Deleted capabilities: %s by user ID %d', implode( ', ', $advausro_capabilities ), get_current_user_id() )
		);

		wp_send_json_success( array( 'message' => esc_html__( 'Capabilities deleted successfully!', 'advanced-use-role-management' ) ) );
	}

	/**
	 * AJAX handler: clone an existing role.
	 */
	public function advausro_clone_role() {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		global $wpdb;

		check_ajax_referer( 'advausro_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_role_slug = isset( $_POST['role_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['role_slug'] ) ) : '';

		if ( empty( $advausro_role_slug ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Role slug is required.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_table_roles        = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_table_capabilities = $wpdb->prefix . 'advausro_role_capabilities';

		$advausro_original_role_data = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$wpdb->prepare( "SELECT * FROM {$advausro_table_roles} WHERE role_slug = %s", $advausro_role_slug ),
			ARRAY_A
		);

		$advausro_capabilities  = array();
		$advausro_original_name = '';

		if ( $advausro_original_role_data ) {
			$advausro_original_name    = $advausro_original_role_data['display_name'];
			$advausro_capabilities_res = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
				$wpdb->prepare( "SELECT capability FROM {$advausro_table_capabilities} WHERE role_id = %d", $advausro_original_role_data['id'] ),
				ARRAY_A
			);
			$advausro_capabilities = $advausro_capabilities_res;

			if ( preg_match( '/\(Clone(?: \d+)?\)$/i', $advausro_original_name ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You cannot clone an already cloned role.', 'advanced-use-role-management' ) ) );
				wp_die();
			}
		} else {
			// Check if it's a standard WordPress role.
			$advausro_wp_roles_obj = wp_roles();
			$advausro_wp_role      = $advausro_wp_roles_obj->get_role( $advausro_role_slug );

			if ( ! $advausro_wp_role ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Original role not found.', 'advanced-use-role-management' ) ) );
				wp_die();
			}

			$advausro_original_name = $advausro_wp_roles_obj->roles[ $advausro_role_slug ]['name'] ?? $advausro_role_slug;

			foreach ( $advausro_wp_role->capabilities as $cap => $granted ) {
				if ( $granted ) {
					$advausro_capabilities[] = array( 'capability' => $cap );
				}
			}
		}

		$advausro_clone_name    = $advausro_original_name . ' (Clone)';
		$advausro_new_role_slug = sanitize_title( $advausro_clone_name );

		$advausro_existing = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$advausro_table_roles} WHERE role_slug = %s", $advausro_new_role_slug )
		);

		if ( $advausro_existing > 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'This role has already been cloned.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_inserted = $wpdb->insert(
			$advausro_table_roles,
			array(
				'role_slug'    => $advausro_new_role_slug,
				'role_name'    => $advausro_clone_name,
				'display_name' => $advausro_clone_name,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( ! $advausro_inserted ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Database error: Failed to clone role.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_new_role_id = $wpdb->insert_id;

		foreach ( $advausro_capabilities as $advausro_cap ) {
			$wpdb->insert(
				$advausro_table_capabilities,
				array(
					'role_id'    => $advausro_new_role_id,
					'capability' => $advausro_cap['capability'],
				),
				array( '%d', '%s' )
			);
		}

		// Register the new role in WordPress immediately.
		$advausro_caps_to_register             = array_fill_keys( wp_list_pluck( $advausro_capabilities, 'capability' ), true );
			$advausro_caps_to_register['read'] = true; // Ensure basic access.
		add_role( $advausro_new_role_slug, $advausro_clone_name, $advausro_caps_to_register );

		$this->advausro_log(
			'clone_role',
			sprintf(
				"Cloned role '%s' as '%s' by user ID %d",
				$advausro_original_name,
				$advausro_clone_name,
				get_current_user_id()
			)
		);

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Role cloned successfully!', 'advanced-use-role-management' ),
				'role_slug'    => $advausro_new_role_slug,
				'role_name'    => $advausro_clone_name,
				'capabilities' => wp_list_pluck( $advausro_capabilities, 'capability' ),
			)
		);
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * AJAX handler: update capabilities for a role.
	 */
	public function advausro_update_role_capabilities() {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		global $wpdb;

		check_ajax_referer( 'advausro_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_role_slug    = isset( $_POST['role_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['role_slug'] ) ) : '';
		$advausro_capabilities = isset( $_POST['capabilities'] ) ?
			array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['capabilities'] ) ) :
			array();

		$advausro_capabilities = $this->advausro_normalize_capabilities( $advausro_capabilities );

		if ( empty( $advausro_role_slug ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid role selected.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_wp_role         = get_role( $advausro_role_slug );
		$advausro_is_default_role = ! is_null( $advausro_wp_role );

		// All capabilities manageable via our UI.
		$advausro_manageable_caps = array(
			'publish_posts',
			'create_posts',
			'delete_posts',
			'edit_published_posts',
			'delete_private_photos',
			'delete_published_posts',
			'delete_other_photos',
			'manage_categories',
			'view_dashboard',
			'edit_dashboard',
			'upload_files',
			'delete_files',
			'moderate_comments',
			'delete_comments',
			'edit_theme_options',
			'install_themes',
			'activate_plugins',
			'install_plugins',
			'list_users',
			'edit_users',
			'delete_users',
			'manage_roles',
			'use_tools',
			'read',
			'edit_files',
			'manage_woocommerce',
			'edit_product_variations',
			'edit_posts',
			'edit_others_posts',
			'edit_published_posts',
			'edit_private_posts',
			'publish_pages',
			'edit_pages',
			'edit_published_pages',
			'edit_others_pages',
			'edit_private_pages',
			'delete_pages',
			'delete_published_pages',
			'delete_others_pages',
			'delete_private_pages',
			'manage_options',
			'switch_themes',
			'edit_themes',
			'delete_themes',
			'edit_plugins',
			'delete_plugins',
			'create_users',
			'promote_users',
			'remove_users',
			// Include expanded sub-capabilities so they can be properly removed.
			'view_woocommerce_reports',
			'manage_woocommerce_orders',
			'manage_woocommerce_coupons',
			'manage_woocommerce_products',
			'publish_products',
			'delete_products',
			'edit_published_products',
			'assign_product_terms',
			'manage_links',
			'unfiltered_html',
		);
		$advausro_custom_caps     = get_option( 'advausro_custom_capabilities', array() );
		if ( is_array( $advausro_custom_caps ) ) {
			$advausro_manageable_caps = array_merge( $advausro_manageable_caps, $advausro_custom_caps );
		}
		$advausro_manageable_caps = array_unique( $advausro_manageable_caps );
		if ( $advausro_is_default_role && ! $this->advausro_is_custom_role_slug( $advausro_role_slug ) ) {
			if ( 'administrator' === $advausro_role_slug ) {
				wp_send_json_error( array( 'message' => esc_html__( 'The Administrator role remains locked for security reasons.', 'advanced-use-role-management' ) ) );
				wp_die();
			}

			// Only remove manageable capabilities that are NOT in the submitted list.
			foreach ( $advausro_manageable_caps as $advausro_cap ) {
				if ( ! in_array( $advausro_cap, $advausro_capabilities, true ) ) {
					$advausro_wp_role->remove_cap( $advausro_cap );
				}
			}

			foreach ( $advausro_capabilities as $advausro_capability ) {
				$advausro_wp_role->add_cap( $advausro_capability );
			}

			$this->advausro_log(
				'update_capabilities',
				sprintf(
					"Updated capabilities for default role '%s' by user ID %d",
					$advausro_role_slug,
					get_current_user_id()
				)
			);

			wp_send_json_success( array( 'message' => esc_html__( 'Capabilities updated successfully for default role.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_table_roles        = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_table_capabilities = $wpdb->prefix . 'advausro_role_capabilities';

		$advausro_role_id = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$wpdb->prepare( "SELECT id FROM {$advausro_table_roles} WHERE role_slug = %s", $advausro_role_slug )
		);

		if ( ! $advausro_role_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Role not found.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$wpdb->delete( $advausro_table_capabilities, array( 'role_id' => $advausro_role_id ), array( '%d' ) );

		foreach ( $advausro_capabilities as $advausro_capability ) {
			$wpdb->insert(
				$advausro_table_capabilities,
				array(
					'role_id'    => $advausro_role_id,
					'capability' => $advausro_capability,
				),
				array( '%d', '%s' )
			);
		}

		// Sync with WordPress role object.
		if ( $advausro_wp_role ) {
			// Build the desired capabilities array (include read as basic access).
			$advausro_desired_caps         = array_fill_keys( $advausro_capabilities, true );
			$advausro_desired_caps['read'] = true;

			// Remove ALL capabilities not in the desired set.
			foreach ( array_keys( $advausro_wp_role->capabilities ) as $existing_cap ) {
				if ( ! isset( $advausro_desired_caps[ $existing_cap ] ) ) {
					$advausro_wp_role->remove_cap( $existing_cap );
				}
			}

			// Add all desired capabilities.
			foreach ( $advausro_desired_caps as $advausro_cap => $grant ) {
				$advausro_wp_role->add_cap( $advausro_cap );
			}
		} else {
			// If role missing from WP but exists in our table, register it now.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$advausro_display_name    = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
				$wpdb->prepare( "SELECT display_name FROM {$advausro_table_roles} WHERE id = %d", $advausro_role_id )
			);
			$advausro_wp_caps         = array_fill_keys( $advausro_capabilities, true );
			$advausro_wp_caps['read'] = true;
			add_role( $advausro_role_slug, $advausro_display_name, $advausro_wp_caps );
		}

		$this->advausro_log(
			'update_capabilities',
			sprintf(
				"Updated capabilities for custom role '%s' by user ID %d",
				$advausro_role_slug,
				get_current_user_id()
			)
		);

		wp_send_json_success( array( 'message' => esc_html__( 'Capabilities updated successfully for custom role.', 'advanced-use-role-management' ) ) );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * AJAX handler: fetch capabilities for a role.
	 */
	public function advausro_fetch_role_capabilities() {
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		global $wpdb;

		if ( isset( $_POST['security'] ) ) {
			check_ajax_referer( 'advausro_ajax_nonce', 'security' );
		} else {
			check_ajax_referer( 'advausro_ajax_nonce', 'nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_role_slug = isset( $_POST['role_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['role_slug'] ) ) : '';
		if ( empty( $advausro_role_slug ) ) {
			$advausro_role_slug = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		}

		if ( empty( $advausro_role_slug ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid role selected.', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		// 1. Prioritize check from our custom roles table.
		$advausro_table_roles        = $wpdb->prefix . 'advausro_custom_roles';
		$advausro_table_capabilities = $wpdb->prefix . 'advausro_role_capabilities';

		// Check if role exists in custom roles table.
		$advausro_role_id = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$wpdb->prepare( "SELECT id FROM {$advausro_table_roles} WHERE role_slug = %s", $advausro_role_slug )
		);

		if ( $advausro_role_id ) {
			$advausro_capabilities = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses a table name built from the local WordPress prefix.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
				$wpdb->prepare( "SELECT capability FROM {$advausro_table_capabilities} WHERE role_id = %d", $advausro_role_id )
			);
			if ( ! empty( $advausro_capabilities ) ) {
				wp_send_json_success( array( 'capabilities' => $advausro_capabilities ) );
				wp_die();
			}
		}

		// 2. Fallback to standard WordPress roles.
		$advausro_wp_role = get_role( $advausro_role_slug );
		if ( ! is_null( $advausro_wp_role ) ) {
			// Include only capabilities where grant is true.
			$advausro_capabilities = array();
			foreach ( $advausro_wp_role->capabilities as $cap => $granted ) {
				if ( $granted ) {
					$advausro_capabilities[] = $cap;
				}
			}
			wp_send_json_success( array( 'capabilities' => $advausro_capabilities ) );
			wp_die();
		}

		wp_send_json_error( array( 'message' => esc_html__( 'Role not found.', 'advanced-use-role-management' ) ) );
		wp_die();
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * AJAX handler: process bulk user role actions.
	 */
	public function advausro_process_bulk_action() {
		check_ajax_referer( 'advausro_ajax_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access', 'advanced-use-role-management' ) ) );
		}

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$user_ids    = isset( $_POST['user_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['user_ids'] ) ) : array();
		$role_slug   = isset( $_POST['role_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['role_slug'] ) ) : '';
		$expires_at  = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';

		if ( empty( $bulk_action ) || empty( $user_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No action or users selected', 'advanced-use-role-management' ) ) );
		}

		$critical_roles = array( 'administrator', 'shop_manager' );
		$success_count  = 0;
		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$is_critical_user = false;
			$protected_roles  = $this->advausro_get_protected_roles();
			foreach ( $protected_roles as $role ) {
				if ( in_array( $role, $user->roles, true ) ) {
					$is_critical_user = true;
					break;
				}
			}

			if ( 'delete' === $bulk_action ) {
				if ( get_current_user_id() !== $user_id && current_user_can( 'delete_user', $user_id ) && ! $is_critical_user ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					if ( wp_delete_user( $user_id ) ) {
						++$success_count;
					}
				}
			} elseif ( 'add_role' === $bulk_action && ! empty( $role_slug ) ) {
				$user->add_role( $role_slug );
				++$success_count;
			} elseif ( 'remove_role' === $bulk_action && ! empty( $role_slug ) ) {
				if ( in_array( $role_slug, $critical_roles, true ) ) {
					continue; // Skip removing critical roles.
				}
				$user->remove_role( $role_slug );
				++$success_count;
			} elseif ( 'export' === $bulk_action ) {
				// Export logic.
				continue;
			}
		}

		if ( 'export' === $bulk_action ) {
			wp_send_json_error( array( 'message' => __( 'Bulk export must be submitted through the standard form workflow.', 'advanced-use-role-management' ) ) );
		}

		if ( $success_count > 0 ) {
			/* translators: %d: Number of users processed. */
			wp_send_json_success( array( 'message' => sprintf( __( '%d users processed successfully.', 'advanced-use-role-management' ), $success_count ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'No users were updated. Protected roles are preserved.', 'advanced-use-role-management' ) ) );
		}
	}

	/**
	 * Get list of protected roles that should not be deleted or easily modified.
	 * Includes system roles and common plugin roles like WooCommerce.
	 */
	/**
	 * Get list of protected roles that cannot be deleted.
	 *
	 * @return array
	 */
	public function advausro_get_protected_roles(): array {
		$protected = array(
			'administrator',
			'shop_manager',
			'customer',
			'wp_seo_manager',
			'wp_seo_editor',
			'bbp_keymaster',
			'member',
		);

		return apply_filters( 'advausro_protected_roles', $protected );
	}

	/**
	 * Add role filter dropdown to the users list.
	 *
	 * @param string $which Which tablenav (top/bottom).
	 */
	public function advausro_add_role_filter_to_users_list( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list filter value used for display.
		$selected_role = isset( $_GET['advausro_role_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['advausro_role_filter'] ) ) : '';
		$roles         = wp_roles()->roles;
		?>
		<select name="advausro_role_filter" id="advausro_role_filter_native" style="float:none; margin-left: 10px;">
			<option value=""><?php esc_html_e( 'Filter by Role', 'advanced-use-role-management' ); ?></option>
			<?php foreach ( $roles as $role_slug => $role_data ) : ?>
				<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( $selected_role, $role_slug ); ?>>
					<?php echo esc_html( $role_data['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Filter users by role in WP_User_Query.
	 *
	 * @param \WP_User_Query $query User query object.
	 */
	public function advausro_filter_users_by_role_native( $query ) {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return $query;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'users' !== $screen->id ) {
			return $query;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only users list filter handled by WordPress query vars.
		if ( isset( $_GET['advausro_role_filter'] ) && ! empty( $_GET['advausro_role_filter'] ) ) {
			$role = sanitize_text_field( wp_unslash( $_GET['advausro_role_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only users list filter handled by WordPress query vars.
			$query->set( 'role', $role );
		}

		return $query;
	}

	/**
	 * Expand high-level capabilities into required sub-capabilities.
	 *
	 * @param array $capabilities Input capabilities.
	 * @return array
	 */
	private function advausro_expand_specialized_capabilities( array $capabilities ): array {
		$mappings = array(
			'manage_woocommerce' => array(
				'view_woocommerce_reports',
				'manage_woocommerce_orders',
				'manage_woocommerce_coupons',
				'manage_woocommerce_products',
			),
			'edit_products'      => array(
				'publish_products',
				'delete_products',
				'edit_published_products',
				'assign_product_terms',
			),
			'manage_options'     => array(
				'manage_categories',
				'manage_links',
				'unfiltered_html',
				'edit_theme_options',
			),
		);

		$expanded = $capabilities;
		foreach ( $capabilities as $cap ) {
			if ( isset( $mappings[ $cap ] ) ) {
				$expanded = array_merge( $expanded, $mappings[ $cap ] );
			}
		}

		return array_unique( $expanded );
	}

	/**
	 * Check if a slug is a custom role.
	 *
	 * @param string $role_slug Role slug to check.
	 * @return bool
	 */
	public function advausro_is_custom_role_slug( string $role_slug ): bool {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'advausro_custom_roles' );

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return false;
		}

		$role_id = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE role_slug = %s", $role_slug ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		);

		return ! empty( $role_id );
	}

	/**
	 * Normalize capabilities to a flat array of strings.
	 *
	 * @param array $capabilities Input capabilities.
	 * @return array
	 */
	public function advausro_normalize_capabilities( array $capabilities ): array {
		$capabilities = array_filter( array_map( 'sanitize_text_field', $capabilities ) );
		$capabilities = array_map( 'sanitize_key', $capabilities );
		$capabilities = array_filter( $capabilities );

		return array_values( array_unique( $this->advausro_expand_specialized_capabilities( $capabilities ) ) );
	}
}
