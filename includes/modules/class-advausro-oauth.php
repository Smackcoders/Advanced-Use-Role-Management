<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.NotHyphenatedLowercase -- Intentional: module file name reflects the feature, not the class name.
/**
 * OAuth2 login module for Advanced User Role Manager plugin.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic

/**
 * Handles OAuth2 login integration for WordPress.
 */
class ADVAUSRO_OAuth2Login {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?ADVAUSRO_OAuth2Login $advausro_instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return self
	 */
	/**
	 * Get or create the singleton instance.
	 *
	 * @return ADVAUSRO_OAuth2Login
	 */
	public static function advausro_get_instance(): ADVAUSRO_OAuth2Login {
		if ( null === self::$advausro_instance ) {
			self::$advausro_instance = new self();
		}
		return self::$advausro_instance;
	}

	/**
	 * Constructor — registers WordPress action hooks.
	 */
	/**
	 * Constructor: registers plugin hooks.
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'advausro_register_oauth2_settings' ) );
		add_action( 'login_form', array( $this, 'advausro_render_oauth2_login_button_on_login' ) );
		add_action( 'init', array( $this, 'advausro_handle_oauth2_callback' ) );
		add_action( 'manage_users_extra_tablenav', array( $this, 'advausro_add_oauth2_login_button' ) );
	}

	/**
	 * Register OAuth2 settings group in WordPress.
	 */
	/**
	 * Register OAuth2 settings with WordPress.
	 */
	public function advausro_register_oauth2_settings(): void {
		// Only allow administrators to register OAuth2 settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		register_setting( 'advausro_oauth2_settings_group', 'advausro_oauth2_settings', array( $this, 'advausro_sanitize_oauth2_settings' ) );
	}

	/**
	 * Sanitize and validate OAuth2 settings input.
	 *
	 * @param array $advausroinput Raw input array from the settings form.
	 * @return array Sanitized settings array.
	 */
	/**
	 * Sanitize OAuth2 settings before saving.
	 *
	 * @param array $advausroinput Raw input data.
	 * @return array Sanitized settings.
	 */
	public function advausro_sanitize_oauth2_settings( $advausroinput ): array {
		$advausro_sanitized                      = array();
		$advausro_sanitized['provider']          = isset( $advausroinput['provider'] ) ? sanitize_text_field( $advausroinput['provider'] ) : 'custom';
		$advausro_sanitized['authorization_url'] = isset( $advausroinput['authorization_url'] ) ? esc_url_raw( $advausroinput['authorization_url'] ) : '';
		$advausro_sanitized['token_url']         = isset( $advausroinput['token_url'] ) ? esc_url_raw( $advausroinput['token_url'] ) : '';
		$advausro_sanitized['user_info_url']     = isset( $advausroinput['user_info_url'] ) ? esc_url_raw( $advausroinput['user_info_url'] ) : '';
		$advausro_sanitized['client_id']         = isset( $advausroinput['client_id'] ) ? sanitize_text_field( $advausroinput['client_id'] ) : '';
		$advausro_sanitized['client_secret']     = isset( $advausroinput['client_secret'] ) ? sanitize_text_field( $advausroinput['client_secret'] ) : '';
		$advausro_sanitized['redirect_url']      = isset( $advausroinput['redirect_url'] ) ? esc_url_raw( $advausroinput['redirect_url'] ) : '';
		$advausro_sanitized['role_map']          = isset( $advausroinput['role_map'] ) ? wp_json_encode( array_map( 'sanitize_text_field', (array) $advausroinput['role_map'] ) ) : '{}';
		return $advausro_sanitized;
	}

	/**
	 * Render OAuth2 login button on the WordPress login form.
	 */
	/**
	 * Render OAuth2 login button on the login page.
	 */
	public function advausro_render_oauth2_login_button_on_login(): void {
		$this->advausro_render_oauth2_login_button_markup();
	}

	/**
	 * Add OAuth2 login button to the users list table navigation.
	 *
	 * @param string $advausro_which Position ('top' or 'bottom').
	 */
	/**
	 * Add OAuth2 login button to the user management table nav.
	 *
	 * @param string $advausro_which Which tablenav section (top/bottom).
	 */
	public function advausro_add_oauth2_login_button( string $advausro_which = 'top' ): void {
		if ( 'top' !== $advausro_which ) {
			return;
		}

		$this->advausro_render_oauth2_login_button_markup();
	}

	/**
	 * Output the HTML markup for the OAuth2 login button.
	 */
	/**
	 * Output the OAuth2 login button HTML markup.
	 */
	private function advausro_render_oauth2_login_button_markup(): void {
		$advausro_url = $this->advausro_get_authorize_url();

		// Only show the button if we have a valid authorize URL.
		if ( empty( $advausro_url ) ) {
			return;
		}
		?>
		<div class="oauth2-login-button-wrapper">
			<a href="<?php echo esc_url( $advausro_url ); ?>" class="button button-primary"><?php esc_html_e( 'Login with OAuth2', 'advanced-use-role-management' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Build the OAuth2 authorization URL including CSRF state token.
	 *
	 * @return string Authorization URL or empty string if settings are incomplete.
	 */
	/**
	 * Get the OAuth2 authorization URL.
	 *
	 * @return string Authorization URL or empty string.
	 */
	private function advausro_get_authorize_url(): string {
		$advausro_settings = get_option( 'advausro_oauth2_settings', array() );

		// Check if settings exist and are properly configured.
		if ( empty( $advausro_settings ) || ! is_array( $advausro_settings ) ) {
			return '';
		}

		$advausro_redirect_uri = ! empty( $advausro_settings['redirect_url'] ) ? esc_url_raw( $advausro_settings['redirect_url'] ) : home_url( '/?oauth2=callback' );

		$advausro_state = wp_generate_password( 12, false );
		set_transient( 'advausro_oauth2_state_' . $advausro_state, true, 300 );

		// Safely access array keys with fallbacks.
		$authorization_url = $advausro_settings['authorization_url'] ?? '';
		$client_id         = $advausro_settings['client_id'] ?? '';

		if ( empty( $authorization_url ) || empty( $client_id ) ) {
			return '';
		}

		return $authorization_url . '?' . http_build_query(
			array(
				'response_type' => 'code',
				'client_id'     => $client_id,
				'redirect_uri'  => $advausro_redirect_uri,
				'scope'         => 'openid profile email',
				'state'         => $advausro_state,
				'prompt'        => 'select_account',
			)
		);
	}

	/**
	 * Handle the OAuth2 callback and exchange authorization code for access token.
	 */
	/**
	 * Handle the OAuth2 callback.
	 */
	public function advausro_handle_oauth2_callback(): void {
		// This is an OAuth2 callback endpoint, which does not originate from within WordPress.
		// Instead of using nonce verification, we use the `state` parameter for CSRF protection.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		$advausro_oauth2_action = sanitize_text_field( wp_unslash( $_GET['oauth2'] ?? '' ) );

		if ( 'callback' !== $advausro_oauth2_action ) {
			return;
		}

		$advausro_state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		$advausro_code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );

		// Verify state parameter (OAuth2 security mechanism).
		if ( empty( $advausro_code ) || empty( $advausro_state ) || ! get_transient( 'advausro_oauth2_state_' . $advausro_state ) ) {
			wp_die( esc_html__( 'OAuth2 callback failed: Invalid state parameter.', 'advanced-use-role-management' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		delete_transient( 'advausro_oauth2_state_' . $advausro_state );

		$advausro_settings = get_option( 'advausro_oauth2_settings', array() );

		// Check if settings exist and are properly configured.
		if ( empty( $advausro_settings ) || ! is_array( $advausro_settings ) ) {
			wp_die( esc_html__( 'OAuth2 settings not configured.', 'advanced-use-role-management' ) );
		}

		$advausro_redirect_uri = ! empty( $advausro_settings['redirect_url'] ) ? esc_url_raw( $advausro_settings['redirect_url'] ) : home_url( '/?oauth2=callback' );

		// Safely access required settings.
		$token_url     = $advausro_settings['token_url'] ?? '';
		$client_id     = $advausro_settings['client_id'] ?? '';
		$client_secret = $advausro_settings['client_secret'] ?? '';

		if ( empty( $token_url ) || empty( $client_id ) || empty( $client_secret ) ) {
			wp_die( esc_html__( 'OAuth2 settings incomplete. Please configure all required fields.', 'advanced-use-role-management' ) );
		}

		$advausro_response = wp_remote_post(
			$token_url,
			array(
				'body'      => array(
					'grant_type'    => 'authorization_code',
					'code'          => $advausro_code,
					'redirect_uri'  => $advausro_redirect_uri,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
				),
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $advausro_response ) ) {
			/* translators: %s: Error message returned during the OAuth token exchange. */
			wp_die( esc_html( sprintf( __( 'OAuth token exchange failed: %s', 'advanced-use-role-management' ), $advausro_response->get_error_message() ) ) );
		}

		$advausro_body = json_decode( wp_remote_retrieve_body( $advausro_response ), true );
		if ( empty( $advausro_body['access_token'] ) ) {
			wp_die( esc_html__( 'OAuth access token missing from response.', 'advanced-use-role-management' ) );
		}

		$this->advausro_fetch_user_info( $advausro_body['access_token'] );
	}

	/**
	 * Fetch user info from the OAuth2 provider using the access token.
	 *
	 * @param string $advausro_access_token Valid OAuth2 access token.
	 */
	/**
	 * Fetch user info from the OAuth2 provider.
	 *
	 * @param string $advausro_access_token Access token.
	 */
	private function advausro_fetch_user_info( string $advausro_access_token ): void {
		$advausro_settings = get_option( 'advausro_oauth2_settings', array() );

		// Check if settings exist and are properly configured.
		if ( empty( $advausro_settings ) || ! is_array( $advausro_settings ) ) {
			wp_die( esc_html__( 'OAuth2 settings not configured.', 'advanced-use-role-management' ) );
		}

		$user_info_url = $advausro_settings['user_info_url'] ?? '';
		if ( empty( $user_info_url ) ) {
			wp_die( esc_html__( 'User info URL not configured in OAuth2 settings.', 'advanced-use-role-management' ) );
		}

		$advausro_response = wp_remote_get(
			$user_info_url,
			array(
				'headers'   => array( 'Authorization' => 'Bearer ' . $advausro_access_token ),
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $advausro_response ) ) {
			/* translators: %s: Error message returned while fetching user information. */
			wp_die( esc_html( sprintf( __( 'Failed to fetch user info: %s', 'advanced-use-role-management' ), $advausro_response->get_error_message() ) ) );
		}

		$advausro_user_info = json_decode( wp_remote_retrieve_body( $advausro_response ), true );
		if ( empty( $advausro_user_info['email'] ) ) {
			wp_die( esc_html__( 'Email not provided by OAuth2 provider.', 'advanced-use-role-management' ) );
		}

		$this->advausro_login_user( $advausro_user_info );
	}

	/**
	 * Log the user in after successful OAuth2 authentication.
	 *
	 * @param array $advausro_user_info User info array from the OAuth2 provider.
	 */
	/**
	 * Log in or create a WordPress user from OAuth2 user info.
	 *
	 * @param array $advausro_user_info User info from OAuth2 provider.
	 */
	private function advausro_login_user( array $advausro_user_info ): void {
		$advausro_email          = sanitize_email( $advausro_user_info['email'] );
		$advausro_external_roles = $advausro_user_info['roles'] ?? array();
		$advausro_settings       = get_option( 'advausro_oauth2_settings', array() );
		$advausro_role_map       = json_decode( $advausro_settings['role_map'] ?? '{}', true );
		if ( ! is_array( $advausro_role_map ) ) {
			$advausro_role_map = array();
		}

		if ( empty( $advausro_email ) ) {
			wp_die( 'Email is required.' );
		}

		$advausro_user = get_user_by( 'email', $advausro_email );

		if ( ! $advausro_user ) {
			$advausro_password = wp_generate_password();
			$advausro_user_id  = wp_create_user( $advausro_email, $advausro_password, $advausro_email );

			if ( is_wp_error( $advausro_user_id ) ) {
				wp_die( 'User creation failed: ' . esc_html( $advausro_user_id->get_error_message() ) );
			}
		} else {
			$advausro_user_id = $advausro_user->ID;
		}

		$advausro_user_obj = new \WP_User( $advausro_user_id );

		$advausro_wp_role = 'subscriber';
		$advausro_mapped  = false;

		if ( isset( $advausro_role_map[ $advausro_email ] ) && get_role( $advausro_role_map[ $advausro_email ] ) ) {
			$advausro_wp_role = sanitize_text_field( $advausro_role_map[ $advausro_email ] );
			$advausro_mapped  = true;
		}

		if ( ! $advausro_mapped && is_array( $advausro_external_roles ) ) {
			foreach ( $advausro_external_roles as $advausro_ext_role ) {
				if ( isset( $advausro_role_map[ $advausro_ext_role ] ) && get_role( $advausro_role_map[ $advausro_ext_role ] ) ) {
					$advausro_wp_role = sanitize_text_field( $advausro_role_map[ $advausro_ext_role ] );
					break;
				}
			}
		}

		$advausro_user_obj->set_role( $advausro_wp_role );
		wp_set_auth_cookie( $advausro_user_id );
		wp_set_current_user( $advausro_user_id );

		if ( user_can( $advausro_user_id, 'list_users' ) ) {
			wp_safe_redirect( admin_url( 'users.php' ) );
		} else {
			wp_safe_redirect( admin_url( 'profile.php' ) );
		}

		exit;
	}
}
