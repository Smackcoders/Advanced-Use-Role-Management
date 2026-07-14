<?php
/**
 * Installation class for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Handles plugin activation, deactivation, and uninstallation.
 *
 * @package Advanced_User_Role_Manager
 */
class ADVAUSRO_Install {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $advausro_instance = null;

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
	 * Activate the plugin by creating required database tables.
	 */
	public static function advausro_activate() {
		$advausro_tables = self::advausro_get_tables();

		// Create tables.
		foreach ( $advausro_tables as $advausro_table_suffix => $advausro_sql_definition ) {
			self::advausro_create_table( $advausro_table_suffix, $advausro_sql_definition );
		}

		// Manually add foreign key constraint after both tables are created.
		self::advausro_add_foreign_keys();

		flush_rewrite_rules();
	}

	/**
	 * Uninstall the plugin by removing tables and options.
	 */
	public static function advausro_uninstall() {
		global $wpdb;

		// Remove all plugin tables.
		$tables_to_drop = array(
			$wpdb->prefix . 'advausro_custom_roles',
			$wpdb->prefix . 'advausro_role_capabilities',
			$wpdb->prefix . 'advausro_oauth2_settings',
			$wpdb->prefix . 'advausro_external_role_map',
			$wpdb->prefix . 'advausro_temp_roles',
			$wpdb->prefix . 'advausro_assigned_capabilities',
			$wpdb->prefix . 'advausro_role_management_logs',
		);

		foreach ( $tables_to_drop as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		// Remove plugin options.
		delete_option( 'advausro_custom_capabilities' );
		delete_option( 'advausro_oauth2_settings' );
		delete_option( 'advausro_timezone_pending' );
		delete_option( 'advausro_timezone_string' );
		delete_option( 'advausro_gmt_offset' );

		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'advausro_check_expired_roles' );

		// Remove user meta.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", '_advausro_%' ) );

		// Remove transient state rows created during OAuth logins.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_advausro_oauth2_state_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_advausro_oauth2_state_' ) . '%'
			)
		);

		flush_rewrite_rules();
	}

	/**
	 * Create a database table if it does not already exist.
	 *
	 * @param string $advausro_suffix      Table name suffix.
	 * @param string $advausro_columns_sql  SQL column definitions.
	 */
	private static function advausro_create_table( $advausro_suffix, $advausro_columns_sql ) {
		global $wpdb;

		$advausro_table_name = $wpdb->prefix . $advausro_suffix;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $advausro_table_name ) ) !== $advausro_table_name ) {
			$advausro_charset_collate = $wpdb->get_charset_collate();

			$advausro_sql = "CREATE TABLE $advausro_table_name (
                $advausro_columns_sql
            ) ENGINE=InnoDB $advausro_charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $advausro_sql );
		}
	}

	/**
	 * Add foreign key constraints between related tables.
	 */
	private static function advausro_add_foreign_keys() {
		global $wpdb;

		$advausro_role_cap_table = $wpdb->prefix . 'advausro_role_capabilities';
		$advausro_roles_table    = $wpdb->prefix . 'advausro_custom_roles';

		// Check if constraint already exists.
		$advausro_constraint_exists = $wpdb->get_var(
			$wpdb->prepare(
				'
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = %s 
              AND CONSTRAINT_NAME = %s
        ',
				$advausro_role_cap_table,
				'fk_role_capabilities'
			)
		);

		if ( ! $advausro_constraint_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are built from the local WordPress prefix.
			$wpdb->query(
				"
                ALTER TABLE $advausro_role_cap_table 
                ADD CONSTRAINT fk_role_capabilities 
                FOREIGN KEY (role_id) REFERENCES $advausro_roles_table(id) ON DELETE CASCADE
            "
			);
		}
	}

	/**
	 * Get table definitions for plugin database tables.
	 *
	 * @return array Table suffix => column definitions map.
	 */
	private static function advausro_get_tables() {
		return array(
			'advausro_custom_roles'          => '
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_slug VARCHAR(255) NOT NULL UNIQUE,
                role_name VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ',
			'advausro_role_capabilities'     => '
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                capability VARCHAR(100) NOT NULL,
                UNIQUE KEY unique_role_capability (role_id, capability)
            ',
			'advausro_oauth2_settings'       => '
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id VARCHAR(255) NOT NULL,
                client_secret VARCHAR(255) NOT NULL,
                auth_url VARCHAR(255) NOT NULL,
                token_url VARCHAR(255) NOT NULL,
                scope VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ',
			'advausro_external_role_map'     => '
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                external_role VARCHAR(191) NOT NULL,
                wp_role VARCHAR(191) NOT NULL,
                UNIQUE KEY external_role (external_role)
            ',
			'advausro_temp_roles'            => '
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                role_slug VARCHAR(191) NOT NULL,
                expires_at DATETIME NOT NULL,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ',
			'advausro_assigned_capabilities' => '
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                capability VARCHAR(191) NOT NULL,
                allowed TINYINT(1) NOT NULL DEFAULT 1,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ',
			'advausro_role_management_logs'  => '
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(100) NOT NULL,
                user_id BIGINT UNSIGNED,
                log_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ',
		);
	}
}
