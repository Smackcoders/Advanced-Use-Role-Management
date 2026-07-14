<?php
/**
 * OAuth2 Settings admin view for Advanced User Role Manager.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$options = get_option( 'advausro_oauth2_settings', array() );
?>
<?php require_once ADVAUSRO_PLUGIN_PATH . 'admin/views/advausro-header.php'; ?>
<div class="wrap crm-container">

	<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
		<a href="#" id="open-help-panel" style="font-size: 0.875rem; font-weight: 500; color: #4f46e5; text-decoration: none; display: flex; align-items: center; gap: 6px; padding: 6px 16px; background: #eef2ff; border-radius: 50px;">
			<span class="dashicons dashicons-editor-help" style="font-size: 16px; width: 16px; height: 16px;"></span> Help?
		</a>
	</div>

	<?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe usage, WordPress sets this on successful settings update.
	if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) :
		?>
		<div style="background: #ecfdf5; border: 1px solid #10b981; border-left: 4px solid #059669; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500;">
			Settings saved successfully.
		</div>
	<?php endif; ?>

	<div class="crm-box" style="padding: 40px; max-width: 800px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
		<h2 style="font-size: 1.25rem; color: #111827; font-weight: 700; margin: 0 0 32px 0;">OAuth2 Configuration</h2>
		
		<form method="post" action="options.php" style="display: flex; flex-direction: column; gap: 24px;">
			<?php
			settings_fields( 'advausro_oauth2_settings_group' );
			do_settings_sections( 'advausro_oauth2_settings_page' );
			?>
			
			<div style="display: flex; flex-direction: column; gap: 8px;">
				<label for="advausro_provider" style="font-weight: 600; font-size: 0.875rem; color: #374151;">Select Provider</label>
				<select name="advausro_oauth2_settings[provider]" id="advausro_provider" style="border: 1px solid #d1d5db; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; color: #111827; transition: border-color 0.2s;">
					<option value="custom" <?php selected( $options['provider'] ?? '', 'custom' ); ?>>Generic OAuth2 (Custom)</option>
					<option value="google" <?php selected( $options['provider'] ?? '', 'google' ); ?>>Google</option>
					<option value="microsoft" <?php selected( $options['provider'] ?? '', 'microsoft' ); ?>>Microsoft (Azure AD)</option>
				</select>
				<span style="font-size: 0.75rem; color: #6b7280;">Select a provider to pre-fill known endpoints.</span>
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;">
				<label for="client_id" style="font-weight: 600; font-size: 0.875rem; color: #374151;">Client ID</label>
				<input type="text" name="advausro_oauth2_settings[client_id]" id="client_id" value="<?php echo esc_attr( $options['client_id'] ?? '' ); ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; color: #111827; transition: border-color 0.2s;" placeholder="Enter client ID">
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;">
				<label for="client_secret" style="font-weight: 600; font-size: 0.875rem; color: #374151;">Client Secret</label>
				<input type="password" name="advausro_oauth2_settings[client_secret]" id="client_secret" value="<?php echo esc_attr( $options['client_secret'] ?? '' ); ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; color: #111827; transition: border-color 0.2s;" placeholder="Enter client secret">
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;" class="oauth-endpoint-row">
				<label for="authorization_url" style="font-weight: 600; font-size: 0.875rem; color: #374151;">Auth URL</label>
				<input type="text" name="advausro_oauth2_settings[authorization_url]" id="authorization_url" value="<?php echo esc_attr( $options['authorization_url'] ?? '' ); ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; color: #111827; transition: border-color 0.2s;" placeholder="https://provider.com/oauth/authorize">
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;" class="oauth-endpoint-row">
				<label for="token_url" style="font-weight: 600; font-size: 0.875rem; color: #374151;">Token URL</label>
				<input type="text" name="advausro_oauth2_settings[token_url]" id="token_url" value="<?php echo esc_attr( $options['token_url'] ?? '' ); ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; color: #111827; transition: border-color 0.2s;" placeholder="https://provider.com/oauth/token">
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;" class="oauth-endpoint-row">
				<label for="user_info_url" style="font-weight: 600; font-size: 0.875rem; color: #374151;">User Info URL</label>
				<input type="text" name="advausro_oauth2_settings[user_info_url]" id="user_info_url" value="<?php echo esc_attr( $options['user_info_url'] ?? '' ); ?>" style="border: 1px solid #d1d5db; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; color: #111827; transition: border-color 0.2s;" placeholder="https://provider.com/oauth/userinfo">
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;">
				<label for="redirect_url" style="font-weight: 600; font-size: 0.875rem; color: #374151;">Redirect URL</label>
				<input type="text" name="advausro_oauth2_settings[redirect_url]" id="redirect_url" value="<?php echo esc_attr( home_url( '/?oauth2=callback' ) ); ?>" style="border: 1px solid #e5e7eb; border-radius: 8px; height: 44px; padding: 0 12px; font-size: 0.875rem; width: 100%; max-width: none; box-sizing: border-box; background: #f9fafb; color: #6b7280; outline: none;" readonly>
				<span style="font-size: 0.75rem; color: #6b7280;">Your OAuth callback URL. Ensure this is added to your provider's authorized redirect URIs.</span>
			</div>

			<div style="margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 24px; display: flex; justify-content: flex-end;">
				<button type="submit" name="submit" id="submit" class="crm-btn crm-btn-save" style="height: 48px; display: inline-flex; align-items: center; justify-content: center; padding: 0 24px;">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
					Save Changes
				</button>
			</div>
		</form>
	</div>
</div>

<div id="help-overlay"></div>
<div id="help-panel">
	<div class="help-header">
		<h2>OAuth2 Settings Help</h2>
		<span id="close-help-panel" aria-label="Close help panel" role="button" tabindex="0">×</span>
	</div>
	<div class="help-content">
		<p><strong>Configuring OAuth2 for your website</strong></p>

		<h3>1. Google OAuth 2.0 Setup</h3>
		<ol>
			<li>Go to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
			<li>Create a new project or select an existing one.</li>
			<li>Navigate to <strong>APIs & Services > Credentials</strong>.</li>
			<li>Click <strong>Create Credentials > OAuth client ID</strong>.</li>
			<li>Select <strong>Web application</strong> as the Application type.</li>
			<li>Under <strong>Authorized redirect URIs</strong>, add the Redirect URL shown on this page:
				<code><?php echo esc_html( home_url( '/?oauth2=callback' ) ); ?></code>
			</li>
			<li>Click <strong>Create</strong> and copy your <strong>Client ID</strong> and <strong>Client Secret</strong>.</li>
		</ol>

		<h3>2. Microsoft (Azure AD) Setup</h3>
		<ol>
			<li>Sign in to the <a href="https://portal.azure.com/" target="_blank">Azure Portal</a>.</li>
			<li>Search for and select <strong>App registrations</strong>.</li>
			<li>Click <strong>New registration</strong>.</li>
			<li>Enter a name and select <strong>Accounts in any organizational directory (Any Microsoft Entra ID tenant - Multitenant)</strong>.</li>
			<li>Under <strong>Redirect URI</strong>, select <strong>Web</strong> and paste the Redirect URL from this page.</li>
			<li>After registering, copy the <strong>Application (client) ID</strong>.</li>
			<li>Navigate to <strong>Certificates & secrets > New client secret</strong>, then copy the secret <strong>Value</strong>.</li>
		</ol>

		<h3>Field Definitions</h3>
		<ul>
			<li><strong>Client ID & Client Secret:</strong> Unique credentials assigned to your app by the provider.</li>
			<li><strong>Auth URL:</strong> Where users go to sign in (usually pre-filled for Google/Microsoft).</li>
			<li><strong>Token URL:</strong> Used behind the scenes to exchange codes for access (usually pre-filled).</li>
			<li><strong>User Info URL:</strong> Endpoint to fetch user details like email/name (usually pre-filled).</li>
			<li><strong>Redirect URL:</strong> The destination URL after login. Must exactly match your provider settings.</li>
		</ul>
	</div>
</div>


<?php
add_action(
	'admin_footer',
	function () {
		?>

		<?php
	}
);
?>
