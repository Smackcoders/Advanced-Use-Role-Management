<?php
/**
 * Role management admin view for Advanced User Role Manager.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
global $wpdb, $advausro_wp_roles;

// Define default WordPress roles.
$default_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

// Fetch custom roles from the advausro_custom_roles table.
$advausro_custom_roles_table = $wpdb->prefix . 'advausro_custom_roles';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from the local WordPress prefix.
$advausro_custom_roles = $wpdb->get_results( "SELECT role_slug, display_name FROM {$advausro_custom_roles_table}", ARRAY_A );
$custom_role_slugs     = wp_list_pluck( $advausro_custom_roles, 'role_slug' );
$custom_role_names     = array_column( $advausro_custom_roles, 'display_name', 'role_slug' );

// Get all editable roles.
$advausro_roles = get_editable_roles();

// Get protected roles.
$role_manager    = ADVAUSRO_RoleManager::advausro_get_instance();
$protected_roles = $role_manager->advausro_get_protected_roles();

// Filter roles to include only default and active custom roles.
$filtered_roles = array();
foreach ( $advausro_roles as $role_key => $role_data ) {
	$filtered_roles[ $role_key ] = array(
		'name' => in_array( $role_key, $custom_role_slugs, true ) && isset( $custom_role_names[ $role_key ] )
			? $custom_role_names[ $role_key ]
			: $role_data['name'],
	);
}

$advausro_custom_capabilities = get_option( 'advausro_custom_capabilities', array() );
$advausro_capability_sections = array(
	'All'        => array(), // Will be populated with all capabilities.
	'Posts'      => array( 'publish_posts', 'create_posts', 'delete_posts', 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts', 'delete_published_posts', 'delete_private_posts', 'delete_others_posts', 'manage_categories' ),
	'Pages'      => array( 'publish_pages', 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'edit_private_pages', 'delete_pages', 'delete_published_pages', 'delete_others_pages', 'delete_private_pages' ),
	'Dashboard'  => array( 'view_dashboard', 'edit_dashboard' ),
	'Media'      => array( 'upload_files', 'delete_files', 'manage_links' ),
	'Comments'   => array( 'moderate_comments', 'delete_comments' ),
	'Themes'     => array( 'edit_theme_options', 'install_themes', 'switch_themes', 'edit_themes', 'delete_themes' ),
	'Plugins'    => array( 'activate_plugins', 'install_plugins', 'edit_plugins', 'delete_plugins' ),
	'Users'      => array( 'list_users', 'edit_users', 'delete_users', 'create_users', 'promote_users', 'remove_users' ),
	'Roles'      => array( 'manage_roles' ),
	'Tools'      => array( 'use_tools' ),
	'Admin'      => array( 'read', 'manage_options', 'unfiltered_html' ),
	'Deprecated' => array( 'edit_files' ),
	'Products'   => array( 'manage_woocommerce', 'publish_products', 'delete_products', 'edit_published_products', 'assign_product_terms' ),
	'Variation'  => array( 'edit_product_variations' ),
);

// Populate 'All' section with capabilities from other sections and custom capabilities.
$all_capabilities = array();
foreach ( $advausro_capability_sections as $section => $advausro_caps ) {
	if ( 'All' !== $section ) {
		$all_capabilities = array_merge( $all_capabilities, $advausro_caps );
	}
}
$all_capabilities                    = array_merge( $all_capabilities, $advausro_custom_capabilities );
$advausro_capability_sections['All'] = array_unique( $all_capabilities );

if ( ! empty( $advausro_custom_capabilities ) ) {
	$advausro_capability_sections['Custom'] = $advausro_custom_capabilities;
}

$section_icons = array(
	'All'        => '',
	'Posts'      => 'dashicons-admin-post',
	'Pages'      => 'dashicons-admin-page',
	'Dashboard'  => 'dashicons-dashboard',
	'Media'      => 'dashicons-admin-media',
	'Comments'   => 'dashicons-admin-comments',
	'Themes'     => 'dashicons-admin-appearance',
	'Plugins'    => 'dashicons-admin-plugins',
	'Users'      => 'dashicons-admin-users',
	'Roles'      => 'dashicons-shield',
	'Tools'      => 'dashicons-admin-tools',
	'Admin'      => 'dashicons-admin-generic',
	'Deprecated' => 'dashicons-trash',
	'Products'   => 'dashicons-cart',
	'Variation'  => 'dashicons-image-filter',
	'Custom'     => 'dashicons-plus-alt',
);

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core users table name comes from wpdb.
$users = $wpdb->get_results( "SELECT ID, user_login, user_email, display_name FROM {$wpdb->users}", ARRAY_A );
?>

<?php require_once ADVAUSRO_PLUGIN_PATH . 'admin/views/advausro-header.php'; ?>
<div class="wrap crm-container">

	<?php if ( ! empty( $protected_roles ) ) : ?>
		<div class="crm-notice crm-notice-warning" style="background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px; border-radius: 4px;">
			<p style="margin: 0; color: #856404;">
				<strong><span class="dashicons dashicons-shield" style="vertical-align: middle;"></span> <?php esc_html_e( 'Shielded Roles Active:', 'advanced-use-role-management' ); ?></strong>
				<?php echo esc_html( implode( ', ', $protected_roles ) ); ?>. 
				<?php esc_html_e( 'These roles are critical for system stability and cannot be deleted.', 'advanced-use-role-management' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="crm_overall_layout">
		<div class="crm-grid">
			<div class="crm-box">
				<h2 style="display: flex; align-items: flex-start; font-size: 1rem;"><span class="dashicons dashicons-groups" style="color: #8b5cf6; margin-right: 8px; margin-top: 2px;"></span> Select Role & <br>Change Capabilities</h2>
				<select class="crm-role-selector">
					<option value="">Select a role</option>
					<?php foreach ( $filtered_roles as $advausro_role_key => $advausro_role ) : ?>
						<option value="<?php echo esc_attr( $advausro_role_key ); ?>">
							<?php echo esc_html( $advausro_role['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button class="crm-btn clone-role-btn crm-clone-role-btn"><span class="dashicons dashicons-admin-page"></span> Clone Selected Role</button>
			</div>

			<div class="crm-box">
				<h2 style="display: flex; align-items: center; font-size: 1rem;"><span class="dashicons dashicons-plus" style="color: #10b981; margin-right: 8px;"></span> Add Capability</h2>
				<input type="text" placeholder="e.g. publish_posts" id="new-capability" />
				<button class="crm-btn crm-add-capablity-btn" id="add-capability-btn">+ Add New</button>
			</div>

			<div class="crm-box">
				<h2 class="add_role_text" style="display: flex; align-items: center; font-size: 1rem;"><span class="dashicons dashicons-admin-users" style="color: #3b82f6; margin-right: 8px;"></span> Add Role</h2>
				<form id="add-role-form">
					<div class="label_input">
						<label for="role_display">Display Name</label>
						<input class="input_txt" type="text" name="role_display" required />
					</div>
					<div class="label_input">
						<label>Role Slug</label>
						<input class="input_txt" type="text" name="role_name" required />
					</div>
				</form>
				<button class="crm-btn role" type="button" id="add-new-role-btn">+ Add New Role</button>
			</div>

			<div class="crm-box crm-delete-capability">
				<h2 style="display: flex; align-items: center; font-size: 1rem;"><span class="dashicons dashicons-trash" style="color: #ef4444; margin-right: 8px;"></span> Delete Capability</h2>
				<input type="text" placeholder="Search" class="crm-search" id="delete-search" />
				<ul id="delete-capabilities-list">
					<?php
					$advausro_safe_caps = is_array( $advausro_custom_capabilities ) ? array_filter( $advausro_custom_capabilities ) : array();
					if ( ! empty( $advausro_safe_caps ) ) :
						?>
						<?php foreach ( $advausro_safe_caps as $advausro_capability ) : ?>
							<li>
								<input type="checkbox" class="delete-checkbox" value="<?php echo esc_attr( $advausro_capability ); ?>">
								<?php echo esc_html( $advausro_capability ); ?>
							</li>
						<?php endforeach; ?>
					<?php else : ?>
						<li>No custom capabilities found.</li>
					<?php endif; ?>
				</ul>
				<button class="crm-btn delete-btn"><span class="dashicons dashicons-trash"></span> Delete</button>
			</div>

			<div class="crm-box crm-delete-role">
				<h2 style="display: flex; align-items: center; font-size: 1rem;"><span class="dashicons dashicons-trash" style="color: #ef4444; margin-right: 8px;"></span> Delete Role</h2>
				<input type="text" placeholder="Search" class="crm-search" id="delete-role-search" />
				<ul id="delete-roles-list">
					<?php if ( ! empty( $advausro_custom_roles ) ) : ?>
						<?php
						foreach ( $advausro_custom_roles as $advausro_role ) :
							$is_protected = in_array( $advausro_role['role_slug'], $protected_roles, true );
							?>
							<li 
							<?php
							if ( $is_protected ) {
								echo 'style="opacity: 0.6;"';} // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline style. 
							?>
							>
								<input type="checkbox" class="delete-checkbox" value="<?php echo esc_attr( $advausro_role['role_slug'] ); ?>" 
									<?php
									if ( $is_protected ) {
										echo 'disabled title="' . esc_attr__( 'Protected system role', 'advanced-use-role-management' ) . '"';} // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute output with escaped title. 
									?>
									>
								<?php echo esc_html( $advausro_role['display_name'] ) . ' (' . esc_html( $advausro_role['role_slug'] ) . ')'; ?>
								<?php if ( $is_protected ) : ?>
									<span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; color: #999;"></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					<?php else : ?>
						<li>No custom roles found.</li>
					<?php endif; ?>
				</ul>
				<button id="delete-role-btn" class="crm-btn"><span class="dashicons dashicons-trash"></span> Delete</button>
			</div>
		</div>

		<div class="crm-layout">
			<div class="crm-layout-inner">
				<div class="crm-sidebar">
				<div class="crm-sidebar-header">
					<span class="filter-by-text">FILTER BY</span>
				</div>
				<ul>
					<?php foreach ( $advausro_capability_sections as $section => $advausro_caps ) : ?>
						<li class="cap-tab" data-section="<?php echo esc_attr( $section ); ?>" style="display: flex; align-items: center;">
							<?php if ( isset( $section_icons[ $section ] ) && '' !== $section_icons[ $section ] ) : ?>
								<span class="dashicons <?php echo esc_attr( $section_icons[ $section ] ); ?>" style="margin-right: 8px; font-size: 18px; width: 18px; height: 18px;"></span>
							<?php endif; ?>
							<?php echo esc_html( $section ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="crm-main">
				<div class="crm-main-header">
					<div class="crm-capabilities-search-wrapper" style="display: flex; justify-content: flex-end; width: 100%;">
						<div style="position: relative; width: 100%; max-width: 320px;">
							<span class="dashicons dashicons-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;"></span>
							<input type="text" id="crm-cap-search" placeholder="Search capabilities..." style="padding-left: 36px; margin: 0; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);" />
						</div>
					</div>
				</div>
				<div class="capabilities-wrapper">
					<!-- Special "All" section with headers -->
					<div class="crm-capabilities" id="cap-All">
						<?php foreach ( $advausro_capability_sections as $section => $advausro_caps ) : ?>
							<?php if ( 'All' !== $section ) : ?>
								<div class="cap-section">
									<h3 style="display: flex; align-items: center;">
										<?php if ( isset( $section_icons[ $section ] ) && '' !== $section_icons[ $section ] ) : ?>
											<span class="dashicons <?php echo esc_attr( $section_icons[ $section ] ); ?>" style="margin-right: 8px; color: #64748b; font-size: 20px; width: 20px; height: 20px;"></span>
										<?php endif; ?>
										<?php echo esc_html( $section ); ?>
									</h3>
									<div class="cap-list">
										<?php if ( empty( $advausro_caps ) ) : ?>
											<p>No capabilities available.</p>
										<?php else : ?>
											<?php foreach ( $advausro_caps as $advausro_capability ) : ?>
												<div class="cap-item">
													<label>
														<input type="checkbox" class="capability-checkbox" value="<?php echo esc_attr( $advausro_capability ); ?>"
															<?php
															$defaults = array( 'read', 'edit_posts', 'publish_posts', 'upload_files' );
															if ( in_array( $advausro_capability, $defaults, true ) ) {
																echo 'checked';
															}
															?>
															/>
														<?php echo esc_html( $advausro_capability ); ?>
													</label>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>

					<!-- Individual sections -->
					<?php foreach ( $advausro_capability_sections as $section => $advausro_caps ) : ?>
						<?php if ( 'All' !== $section ) : ?>
							<div class="crm-capabilities" id="cap-<?php echo esc_attr( $section ); ?>">
								<h3 style="display: flex; align-items: center;">
									<?php if ( isset( $section_icons[ $section ] ) && '' !== $section_icons[ $section ] ) : ?>
										<span class="dashicons <?php echo esc_attr( $section_icons[ $section ] ); ?>" style="margin-right: 8px; color: #64748b; font-size: 20px; width: 20px; height: 20px;"></span>
									<?php endif; ?>
									<?php echo esc_html( $section ); ?>
								</h3>
								<div class="cap-list">
									<?php if ( empty( $advausro_caps ) ) : ?>
										<p>No capabilities available.</p>
									<?php else : ?>
										<?php foreach ( $advausro_caps as $advausro_capability ) : ?>
											<div class="cap-item">
												<label>
													<input type="checkbox" class="capability-checkbox" value="<?php echo esc_attr( $advausro_capability ); ?>"
														<?php
														if ( 'publish_posts' === $advausro_capability || 'manage_categories' === $advausro_capability || 'edit_dashboard' === $advausro_capability ) {
															echo 'checked';
														}
														?>
														/>
													<?php echo esc_html( $advausro_capability ); ?>
												</label>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>

				<div class="crm-button-wrapper">
					<button class="crm-btn update-btn">Update</button>
				</div>
			</div>
			</div>
		</div>
	</div>
</div>
