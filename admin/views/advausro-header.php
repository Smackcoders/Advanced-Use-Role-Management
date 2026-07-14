<?php
/**
 * Header template for Advanced User Role Manager admin pages.
 *
 * @package Advanced_User_Role_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug used to highlight the active admin tab.
$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
	:root {
		--advausro-primary: #5a4af4;
		--advausro-primary-hover: #4a38df;
		--advausro-bg: #f0f0f1;
		--advausro-card-bg: #ffffff;
		--advausro-text-main: #1e293b;
		--advausro-text-muted: #64748b;
		--advausro-border: #e2e8f0;
	}

	/* Override WordPress overflow that breaks sticky */
	#wpwrap, #wpcontent, #wpbody, #wpbody-content {
		overflow: visible !important;
	}

	#wpcontent {
		padding-left: 20px;
	}
	
	/* Apply body background */
	body {
		background: var(--advausro-bg) !important;
	}

	#wpbody-content {
		position: relative;
	}

	/* Sidebar Layout */
	.advausro-sidebar {
		position: absolute;
		top: 0; 
		left: -20px; 
		bottom: 0;
		width: 250px;
		min-height: calc(100vh - 32px);
		background: #ffffff;
		border-right: 1px solid var(--advausro-border);
		padding: 24px 16px;
		z-index: 99;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	/* Sidebar Brand */
	.advausro-sidebar-brand { 
		display: flex; 
		align-items: center; 
		gap: 12px; 
		margin-bottom: 28px; 
		padding-left: 8px; 
		flex-shrink: 0; 
	}
	.advausro-sidebar-logo { 
		display: flex; 
		align-items: center; 
		justify-content: center; 
		width: 36px; 
		height: 36px; 
		background: var(--advausro-primary); 
		color: #ffffff; 
		border-radius: 8px; 
		font-size: 16px; 
		font-weight: 800; 
		flex-shrink: 0; 
		box-shadow: 0 4px 12px rgba(90, 74, 244, 0.25); 
	}
	.advausro-sidebar-logo .dashicons { 
		width: 20px; 
		height: 20px; 
		display: flex; 
		align-items: center; 
		justify-content: center; 
		margin: 0; 
	}
	.advausro-sidebar-title-wrap { 
		display: flex; 
		flex-direction: column; 
		line-height: 1.1; 
		min-width: 0; 
		font-family: 'Inter', sans-serif; 
	}
	.advausro-sidebar-title { 
		font-size: 15px; 
		font-weight: 700; 
		color: #0f172a; 
		letter-spacing: -0.01em; 
	}
	.advausro-sidebar-subtitle { 
		font-size: 11px; 
		color: #64748b; 
		font-weight: 500; 
		margin-top: 2px; 
	}

	/* Tabs Navigation */
	.advausro-tabs-nav {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}
	.advausro-tab-btn {
		display: flex;
		align-items: center;
		justify-content: flex-start;
		width: 100%;
		padding: 10px 14px;
		text-decoration: none;
		color: #475569;
		border: none;
		border-radius: 8px;
		font-size: 14px;
		font-weight: 500;
		transition: background 0.15s ease, color 0.15s ease;
		background: transparent;
		line-height: 1.4;
		box-sizing: border-box;
		font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	}
	.advausro-tab-btn .dashicons {
		width: 18px;
		height: 18px;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 8px;
		flex-shrink: 0;
		font-size: 18px;
	}
	.advausro-tab-btn:hover {
		color: #0f172a;
		background: #f1f5f9;
	}
	.advausro-tab-btn:focus {
		outline: none !important;
		box-shadow: none !important;
	}
	.advausro-tab-btn.active {
		color: var(--advausro-primary);
		background: #eff6ff;
		font-weight: 600;
	}

	/* Top Header & Main Content Area */
	.advausro-top-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		background: transparent;
		padding: 16px 28px 16px 28px;
		margin-left: 230px;
		width: calc(100% - 230px);
		box-sizing: border-box;
		border-bottom: none;
		margin-bottom: 0px;
		position: static;
	}
	.wrap, .crm-container {
		margin-left: 230px !important;
		padding: 22px 28px 60px !important;
		width: calc(100% - 230px) !important;
		box-sizing: border-box;
	}
	
	.advausro-title-group h2 {
		margin: 0 !important;
		font-size: 24px !important;
		font-weight: 800 !important;
		color: #0f172a !important;
		letter-spacing: -0.02em !important;
		font-family: 'Inter', sans-serif !important;
		padding: 0 !important;
		line-height: 1.2 !important;
	}
	.advausro-title-group p {
		margin: 4px 0 0 0 !important;
		font-size: 14px !important;
		color: #64748b !important;
		font-weight: 400 !important;
		font-family: 'Inter', sans-serif !important;
	}
	.advausro-user-actions {
		display: flex;
		align-items: center;
		gap: 16px;
	}
	.advausro-icon-btn {
		background: #ffffff;
		border: 1px solid #e3e8ee;
		color: #4b5563;
		cursor: pointer;
		width: 40px;
		height: 40px;
		padding: 0;
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
	}
	.advausro-icon-btn:hover {
		background: #f8fafc;
		color: #111827;
		border-color: #cbd5e1;
	}
	.advausro-profile {
		display: flex;
		align-items: center;
		gap: 12px;
		border-left: 1px solid #e5e7eb;
		padding-left: 16px;
		cursor: pointer;
	}
	.advausro-profile-avatar {
		width: 36px;
		height: 36px;
		border-radius: 50%;
		background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		color: #ffffff;
		font-weight: 600;
		letter-spacing: 0.5px;
		font-family: 'Inter', sans-serif;
	}
	.advausro-profile-info {
		display: flex;
		flex-direction: column;
	}
	.advausro-profile-name {
		font-size: 0.875rem;
		font-weight: 600;
		color: #111827;
		font-family: 'Inter', sans-serif;
	}
	.advausro-profile-role {
		font-size: 0.75rem;
		color: #6b7280;
		font-family: 'Inter', sans-serif;
	}

	@media screen and (max-width: 782px) {
		.advausro-sidebar {
			position: relative;
			top: 0;
			left: 0;
			width: 100%;
			height: auto;
			border-right: none;
			border-bottom: 1px solid var(--advausro-border);
		}
		.advausro-top-header {
			margin-left: 0;
			width: 100%;
			padding: 16px;
		}
		.wrap, .crm-container {
			margin-left: 0 !important;
			width: 100% !important;
			padding: 0 16px 16px 16px !important;
		}
	}
</style>

<!-- Left Sidebar -->
<div class="advausro-sidebar">
	<div class="advausro-sidebar-brand">
		<div class="advausro-sidebar-logo">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin: 0; display: flex; align-items: center; justify-content: center;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
		</div>
		<div class="advausro-sidebar-title-wrap">
			<div class="advausro-sidebar-title">Role Management</div>
			<div class="advausro-sidebar-subtitle">Advanced Capabilities</div>
		</div>
	</div>

	<div class="advausro-tabs-nav">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=advausro-role-manager' ) ); ?>" class="advausro-tab-btn <?php echo esc_attr( 'advausro-role-manager' === $current_page ? 'active' : '' ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right: 8px; flex-shrink: 0;"><rect x="3" y="3" width="7" height="9" rx="1.5" /><rect x="14" y="3" width="7" height="5" rx="1.5" /><rect x="14" y="12" width="7" height="9" rx="1.5" /><rect x="3" y="16" width="7" height="5" rx="1.5" /></svg> Dashboard
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=advausro-add-role' ) ); ?>" class="advausro-tab-btn <?php echo esc_attr( 'advausro-add-role' === $current_page ? 'active' : '' ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right: 8px; flex-shrink: 0;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Add Role
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=advausro-oauth2-settings' ) ); ?>" class="advausro-tab-btn <?php echo esc_attr( 'advausro-oauth2-settings' === $current_page ? 'active' : '' ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right: 8px; flex-shrink: 0;"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 1 1 8 0v4" /></svg> OAuth2 Settings
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=advausro-audit-log' ) ); ?>" class="advausro-tab-btn <?php echo esc_attr( 'advausro-audit-log' === $current_page ? 'active' : '' ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right: 8px; flex-shrink: 0;"><path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" /><path d="M12 8v4l3 2" /></svg> Audit Logs
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=advausro-import-export' ) ); ?>" class="advausro-tab-btn <?php echo esc_attr( 'advausro-import-export' === $current_page ? 'active' : '' ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right: 8px; flex-shrink: 0;"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg> Import/Export
		</a>
	</div>

	<div class="advausro-sidebar-footer" style="position: sticky; bottom: 0; background: #ffffff; padding: 16px 0 24px 0; margin-top: auto; border-top: 1px solid var(--advausro-border); z-index: 10;">
		<div class="advausro-profile" style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
			<div class="advausro-profile-avatar" style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 600; font-family: 'Inter', sans-serif;">
				<?php
				$advausro_current_user = wp_get_current_user(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$display_name          = ! empty( $advausro_current_user->display_name ) ? $advausro_current_user->display_name : 'Admin';
				$initials   = '';
				$name_parts = explode( ' ', $display_name );
				if ( count( $name_parts ) >= 2 ) {
					$initials = strtoupper( substr( $name_parts[0], 0, 1 ) . substr( $name_parts[ count( $name_parts ) - 1 ], 0, 1 ) );
				} else {
					$initials = strtoupper( substr( $display_name, 0, 2 ) );
				}
				echo esc_html( $initials );
				?>
			</div>
			<div class="advausro-profile-info" style="display: flex; flex-direction: column; align-items: flex-start; gap: 2px;">
				<span class="advausro-profile-name" style="font-weight: 600; color: #0f172a; font-family: 'Inter', sans-serif; font-size: 14px; line-height: 1.2;"><?php echo esc_html( $display_name ); ?></span>
				<span class="advausro-profile-role" style="font-size: 12px; color: #64748b; font-family: 'Inter', sans-serif; line-height: 1.2;">Administrator</span>
			</div>
		</div>
	</div>
</div>

<!-- Top Header (in main content area) -->
<div class="advausro-top-header">
	<div class="advausro-title-group">
		<?php 
		$page_titles = array(
			'advausro-role-manager' => 'Advanced User Role Manager',
			'advausro-add-role' => 'Add Role',
			'advausro-oauth2-settings' => 'OAuth2 Settings',
			'advausro-audit-log' => 'Audit Logs',
			'advausro-import-export' => 'Import/Export Roles',
		);
		$page_descriptions = array(
			'advausro-role-manager' => 'Manage user roles and capabilities across your WordPress site.',
			'advausro-add-role' => 'Create new custom roles and assign capabilities.',
			'advausro-oauth2-settings' => 'Configure OAuth2 authentication and API access.',
			'advausro-audit-log' => 'Track role changes, user modifications and system events.',
			'advausro-import-export' => 'Backup and restore your roles configuration.',
		);
		
		$current_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Role Management';
		$current_desc = isset($page_descriptions[$current_page]) ? $page_descriptions[$current_page] : 'Configure your settings and preferences';
		?>
		<h2><?php echo esc_html($current_title); ?></h2>
		<p><?php echo esc_html($current_desc); ?></p>
	</div>

	<div class="advausro-user-actions" style="display: none;"></div>
</div>
