<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.NotHyphenatedLowercase -- Intentional: module file name reflects the feature, not the class name.
/**
 * Timezone manager module for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX-based timezone detection and update for the plugin.
 */
class ADVAUSRO_TimezoneManager {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?ADVAUSRO_TimezoneManager $advausro_instance = null;

	/**
	 * Constructor — registers the AJAX timezone update handler.
	 */
	private function __construct() {
		add_action( 'wp_ajax_advausro_update_timezone', array( $this, 'advausro_update_timezone' ) );
	}

	/**
	 * Get or create the singleton instance.
	 *
	 * @return self
	 */
	public static function advausro_get_instance(): ADVAUSRO_TimezoneManager {
		if ( null === self::$advausro_instance ) {
			self::$advausro_instance = new self();
		}
		return self::$advausro_instance;
	}

	/**
	 * Handle AJAX request to update the site timezone setting.
	 */
	public function advausro_update_timezone() {
		check_ajax_referer( 'advausro_timezone_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access', 'advanced-use-role-management' ) ) );
			wp_die();
		}

		$advausro_timezone = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : '';

		if ( empty( $advausro_timezone ) ) {
			update_option( 'advausro_timezone_pending', false );
			wp_send_json_success(
				array(
					'message'  => 'Timezone setting skipped',
					'redirect' => admin_url( 'admin.php?page=advausro-role-manager' ),
				)
			);
		}

		$advausro_valid_timezones = timezone_identifiers_list();
		if ( ! in_array( $advausro_timezone, $advausro_valid_timezones, true ) && 'UTC' !== $advausro_timezone ) {
			wp_send_json_error( array( 'message' => 'Invalid timezone selected' ) );
		}

		update_option( 'advausro_timezone_string', $advausro_timezone );
		update_option( 'advausro_gmt_offset', '' );

		update_option( 'advausro_timezone_pending', false );
		wp_send_json_success(
			array(
				'message'  => 'Timezone updated successfully',
				'redirect' => admin_url( 'admin.php?page=advausro-role-manager' ),
			)
		);
	}
}
