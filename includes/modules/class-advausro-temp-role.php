<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Temporary role manager module for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Manages temporary user role assignments with automatic expiration.
 *
 * @package Advanced_User_Role_Manager
 */
class ADVAUSRO_TemporaryRoleManager {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $advausro_instance = null;

	/**
	 * Database table name for temp roles.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor: initializes hooks and cron schedule.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'advausro_temp_roles';

		// Register custom schedule early.
		add_filter(
			'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
			function ( $advausro_schedules ) {
				$advausro_schedules['every_five_minutes'] = array(
					'interval' => 300, // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
					'display'  => __( 'Every 5 Minutes', 'advanced-use-role-management' ),
				);
				return $advausro_schedules;
			}
		);

		// Register AJAX actions and hooks.
		add_action( 'wp_ajax_advausro_assign_temp_role', array( $this, 'advausro_assign_temp_role' ) );
		add_action( 'wp_ajax_advausro_remove_temp_role', array( $this, 'advausro_remove_temp_role' ) );
		add_action( 'wp_ajax_advausro_check_and_remove_expired_roles', array( $this, 'advausro_check_and_remove_expired_roles' ) );
		add_action( 'advausro_check_expired_roles', array( $this, 'advausro_remove_expired_roles' ) );

		// Schedule cron after init.
		add_action(
			'init',
			function () {
				if ( ! wp_next_scheduled( 'advausro_check_expired_roles' ) ) {
					wp_schedule_event( time(), 'every_five_minutes', 'advausro_check_expired_roles' );
				}
			}
		);
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
	 * AJAX handler: assign a temporary role to a user.
	 */
	public function advausro_assign_temp_role() {
		check_ajax_referer( 'advausro_ajax_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_user_id    = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$advausro_role_slug  = isset( $_POST['role_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['role_slug'] ) ) : '';
		$advausro_expires_at = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';

		if ( ! $advausro_user_id || ! $advausro_role_slug || ! $advausro_expires_at ) {
			wp_send_json_error( array( 'message' => 'Missing required fields.' ) );
		}

		try {
			$advausro_expires_dt = new \DateTime( $advausro_expires_at, new \DateTimeZone( wp_timezone_string() ) );
		} catch ( \Exception $advausro_e ) {
			wp_send_json_error( array( 'message' => 'Invalid expiration format.' ) );
		}

		if ( $advausro_expires_dt->getTimestamp() <= time() ) {
			wp_send_json_error( array( 'message' => 'Expiration must be in the future.' ) );
		}

		$advausro_user = get_user_by( 'id', $advausro_user_id );
		if ( ! $advausro_user ) {
			wp_send_json_error( array( 'message' => 'User not found.' ) );
		}

		if ( ! get_role( $advausro_role_slug ) ) {
			wp_send_json_error( array( 'message' => 'Selected role does not exist.' ) );
		}

		global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE user_id = %d AND role_slug = %s",
				$advausro_user_id,
				$advausro_role_slug
			)
		);

		if ( $advausro_existing ) {
			wp_send_json_error( array( 'message' => 'Role already temporarily assigned.' ) );
		}

		$role_snapshot = $this->advausro_get_role_snapshot( $advausro_user );
		if ( empty( $role_snapshot['roles'] ) ) {
			wp_send_json_error( array( 'message' => 'User has no roles available to snapshot.' ) );
		}

		if ( ! get_user_meta( $advausro_user_id, '_advausro_original_roles', true ) ) {
			update_user_meta( $advausro_user_id, '_advausro_original_roles', $role_snapshot );
		}

		if ( ! in_array( $advausro_role_slug, $advausro_user->roles, true ) ) {
			$advausro_user->add_role( $advausro_role_slug );
		}

		$advausro_expires_at_formatted = $advausro_expires_dt->format( 'Y-m-d H:i:s' );

		$insert_result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'    => $advausro_user_id,
				'role_slug'  => $advausro_role_slug,
				'expires_at' => $advausro_expires_at_formatted,
			)
		);

		if ( false === $insert_result ) {
			$this->advausro_restore_roles( $advausro_user, $role_snapshot );
			delete_user_meta( $advausro_user_id, '_advausro_original_roles' );
			error_log( 'DEBUG AURM: temporary role assignment insert failed for user ' . (int) $advausro_user_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			wp_send_json_error( array( 'message' => 'Failed to assign temporary role: ' . $wpdb->last_error ) );
		}

		wp_send_json_success( array( 'message' => 'Temporary role assigned.' ) );
	}

	/**
	 * AJAX handler: remove a temporary role from a user.
	 */
	public function advausro_remove_temp_role() {
		check_ajax_referer( 'advausro_ajax_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}
		$advausro_role_id = isset( $_POST['role_id'] ) ? absint( wp_unslash( $_POST['role_id'] ) ) : 0;
		global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $advausro_role_id ) );
		if ( ! $advausro_entry ) {
			wp_send_json_error( array( 'message' => 'Entry not found.' ) );
		}

		$advausro_user = get_user_by( 'id', $advausro_entry->user_id );
		if ( $advausro_user ) {
			$this->advausro_restore_user_from_entry( $advausro_user, $advausro_entry );
		}

		$wpdb->delete( $this->table_name, array( 'id' => $advausro_entry->id ) );

		wp_send_json_success( array( 'message' => 'Temporary role removed.' ) );
	}

	/**
	 * Cron handler: remove all expired temporary roles.
	 */
	public function advausro_remove_expired_roles() {
		$this->advausro_process_expired_roles();
	}

	/**
	 * AJAX handler: check and remove expired temporary roles.
	 */
	public function advausro_check_and_remove_expired_roles() {
		check_ajax_referer( 'advausro_ajax_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}
		global $wpdb;
		$advausro_now = current_time( 'mysql' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$advausro_expired     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE expires_at <= %s",
				$advausro_now
			)
		);
		$advausro_expired_ids = array_column( $advausro_expired, 'id' );
		$this->advausro_process_expired_roles();
		wp_send_json_success(
			array(
				'message'     => 'Checked and cleaned expired roles.',
				'removed_ids' => $advausro_expired_ids,
			)
		);
	}

	/**
	 * Process and remove all expired role assignments.
	 */
	private function advausro_process_expired_roles(): void {
		global $wpdb;

		$advausro_now     = current_time( 'mysql' );
		$advausro_expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE expires_at <= %s",
				$advausro_now
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		);

		foreach ( $advausro_expired as $advausro_entry ) {
			$advausro_user = get_user_by( 'id', $advausro_entry->user_id );

			if ( $advausro_user ) {
				$this->advausro_restore_user_from_entry( $advausro_user, $advausro_entry );
			}

			$wpdb->delete( $this->table_name, array( 'id' => $advausro_entry->id ) );
		}
	}

	/**
	 * Restore a user to their original roles from a temp role entry.
	 *
	 * @param \WP_User $advausro_user  The user object.
	 * @param object   $advausro_entry Temp role database entry.
	 */
	private function advausro_restore_user_from_entry( \WP_User $advausro_user, $advausro_entry ): void {
		$snapshot = get_user_meta( $advausro_entry->user_id, '_advausro_original_roles', true );
		$advausro_user->remove_role( $advausro_entry->role_slug );

		if ( ! is_array( $snapshot ) || empty( $snapshot['roles'] ) || ! is_array( $snapshot['roles'] ) ) {
			return;
		}

		$this->advausro_restore_roles( $advausro_user, $snapshot );

		if ( ! $this->advausro_user_has_active_temp_roles( (int) $advausro_entry->user_id, (int) $advausro_entry->id ) ) {
			delete_user_meta( $advausro_entry->user_id, '_advausro_original_roles' );
		}
	}

	/**
	 * Get a snapshot of a user current roles.
	 *
	 * @param \WP_User $advausro_user The user object.
	 * @return array Role snapshot.
	 */
	private function advausro_get_role_snapshot( \WP_User $advausro_user ): array {
		$roles = array_values( array_filter( array_map( 'sanitize_key', (array) $advausro_user->roles ) ) );

		return array(
			'roles'       => $roles,
			'captured_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Restore user roles from a snapshot.
	 *
	 * @param \WP_User $advausro_user The user object.
	 * @param array    $snapshot      Role snapshot.
	 */
	private function advausro_restore_roles( \WP_User $advausro_user, array $snapshot ): void {
		$snapshot_roles = array_values( array_filter( array_map( 'sanitize_key', (array) ( $snapshot['roles'] ?? array() ) ) ) );

		if ( empty( $snapshot_roles ) ) {
			return;
		}

		$current_roles = array_values( array_filter( array_map( 'sanitize_key', (array) $advausro_user->roles ) ) );

		foreach ( $snapshot_roles as $role_slug ) {
			if ( get_role( $role_slug ) && ! in_array( $role_slug, $current_roles, true ) ) {
				$advausro_user->add_role( $role_slug );
			}
		}

		$current_roles = array_values( array_filter( array_map( 'sanitize_key', (array) $advausro_user->roles ) ) );
		$extra_roles   = array_diff( $current_roles, $snapshot_roles );

		foreach ( $extra_roles as $role_slug ) {
			$advausro_user->remove_role( $role_slug );
		}

		if ( empty( $advausro_user->roles ) && ! empty( $snapshot_roles[0] ) && get_role( $snapshot_roles[0] ) ) {
			$advausro_user->set_role( $snapshot_roles[0] );
		}
	}

	/**
	 * Check if a user has any active temporary roles.
	 *
	 * @param int $user_id          User ID.
	 * @param int $exclude_entry_id  Entry ID to exclude (default 0).
	 * @return bool
	 */
	private function advausro_user_has_active_temp_roles( int $user_id, int $exclude_entry_id = 0 ): bool {
		global $wpdb;

		if ( $exclude_entry_id > 0 ) {
			$active_count = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
					$user_id,
					$exclude_entry_id
				)
			);
		} else {
			$active_count = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
					$user_id
				)
			);
		}

		return (int) $active_count > 0;
	}
}



add_action(
	'init',
	function () {
		ADVAUSRO_TemporaryRoleManager::advausro_get_instance();
	}
);
