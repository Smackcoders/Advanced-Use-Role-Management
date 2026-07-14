<?php
/**
 * Manager admin view for Advanced User Role Manager.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Safely check REQUEST_METHOD using isset().
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	// Verify nonce for security.
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'advausro_role_action_nonce' ) ) {
		wp_die( esc_html__( 'Security check failed. Please try again.', 'advanced-use-role-management' ) );
	}

	// Apply wp_unslash before sanitizing $_POST data.
	$advausro_user_ids       = isset( $_POST['user_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['user_ids'] ) ) : array();
	$advausro_role_to_add    = isset( $_POST['add_role'] ) ? sanitize_text_field( wp_unslash( $_POST['add_role'] ) ) : '';
	$advausro_role_to_remove = isset( $_POST['remove_role'] ) ? sanitize_text_field( wp_unslash( $_POST['remove_role'] ) ) : '';
	$action                  = isset( $_POST['advausro_action'] ) ? sanitize_text_field( wp_unslash( $_POST['advausro_action'] ) ) : ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	// Check both top and bottom bulk actions.
	$bulk_action_top    = isset( $_POST['bulk_action_top'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action_top'] ) ) : '';
	$bulk_action_bottom = isset( $_POST['bulk_action_bottom'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action_bottom'] ) ) : '';
	$legacy_bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
	$bulk_action        = ! empty( $bulk_action_top ) ? $bulk_action_top : $bulk_action_bottom;
	if ( empty( $bulk_action ) ) {
		$bulk_action = $legacy_bulk_action;
	}

	foreach ( $advausro_user_ids as $advausro_user_id ) {
		$advausro_user = new \WP_User( $advausro_user_id );
		if ( ! $advausro_user->exists() ) {
			continue;
		}

		if ( 'add' === $action && $advausro_role_to_add ) {
			// Fix: Support multi-role assignment by not removing existing roles.
			$advausro_user->add_role( $advausro_role_to_add );
		}

		$role_manager    = ADVAUSRO_RoleManager::advausro_get_instance();
		$protected_roles = $role_manager->advausro_get_protected_roles();

		if ( 'remove' === $action && $advausro_role_to_remove ) {
			if ( ! in_array( $advausro_role_to_remove, $protected_roles, true ) && in_array( $advausro_role_to_remove, $advausro_user->roles, true ) ) {
				$advausro_user->remove_role( $advausro_role_to_remove );
			}
		}

		if ( 'delete' === $bulk_action ) {
			if ( get_current_user_id() !== $advausro_user_id ) {
				// Determine if user has a protected role BEFORE deleting.
				$is_protected = false;
				foreach ( $protected_roles as $advausro_protected_role ) {
					if ( in_array( $advausro_protected_role, $advausro_user->roles, true ) ) {
						$is_protected = true;
						break;
					}
				}

				if ( ! $is_protected ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $advausro_user_id );
				}
			}
		}
	}

	// Bulk export is handled in ADVAUSRO_Connector::advausro_handle_exports().

	add_action(
		'admin_notices',
		function () use ( $action, $bulk_action, $advausro_user_ids ) {
			$count = count( $advausro_user_ids );
			if ( 'delete' === $bulk_action ) {
				echo wp_kses_post( '<div class="notice notice-success is-dismissible"><p>Selected user(s) deleted successfully.</p></div>' );
			} elseif ( 'add' === $action ) {
				echo wp_kses_post( '<div class="notice notice-success is-dismissible"><p>Added role to ' . (int) $count . ' user(s) successfully.</p></div>' );
			} elseif ( 'remove' === $action ) {
				echo wp_kses_post( '<div class="notice notice-success is-dismissible"><p>Removed role from ' . (int) $count . ' user(s) successfully.</p></div>' );
			}
		}
	);
}

global $wpdb, $wp_roles;

// Handle search and filtering.
$search_term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
$role_filter = isset( $_REQUEST['role_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['role_filter'] ) ) : '';
$paged       = isset( $_REQUEST['paged'] ) ? max( 1, absint( wp_unslash( $_REQUEST['paged'] ) ) ) : 1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$per_page    = 20; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$offset      = ( $paged - 1 ) * $per_page;

$query  = "SELECT u.ID, u.user_login, u.user_email, u.display_name FROM {$wpdb->users} u";
$where  = array();
$params = array();

if ( ! empty( $search_term ) ) {
	$where[]  = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
	$like     = '%' . $wpdb->esc_like( $search_term ) . '%';
	$params[] = $like;
	$params[] = $like;
	$params[] = $like;
}

if ( ! empty( $role_filter ) ) {
	$query   .= " INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s";
	$params[] = $wpdb->prefix . 'capabilities';
	$where[]  = 'um.meta_value LIKE %s';
	$params[] = '%' . $wpdb->esc_like( '"' . $role_filter . '"' ) . '%';
}

if ( ! empty( $where ) ) {
	$query .= ' WHERE ' . implode( ' AND ', $where );
}

$count_query = "SELECT COUNT(*) FROM {$wpdb->users} u";
if ( ! empty( $role_filter ) ) {
	$count_query .= " INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s";
}
if ( ! empty( $where ) ) {
	$count_query .= ' WHERE ' . implode( ' AND ', $where );
}

$query     .= ' LIMIT %d OFFSET %d';
$query_args = array_merge( $params, array( $per_page, $offset ) );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query string is prepared immediately here from sanitized parts.
$advausro_users = $wpdb->get_results( $wpdb->prepare( $query, $query_args ), ARRAY_A );
$total_users    = ! empty( $params )
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Count query is prepared immediately here from sanitized parts.
	? (int) $wpdb->get_var( $wpdb->prepare( $count_query, $params ) )
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- No placeholders are present when filters are empty.
	: (int) $wpdb->get_var( $count_query );
$total_pages = ceil( $total_users / $per_page );

$default_expires_at       = new \DateTime( 'now', wp_timezone() );
$default_expires_at_value = $default_expires_at->format( 'Y-m-d\TH:i' );

// Define default WordPress roles.
$default_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

// Get active custom roles from the advausro_custom_roles table - use prepared statement.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
$custom_roles      = $wpdb->get_results( "SELECT role_slug, display_name FROM {$wpdb->prefix}advausro_custom_roles", ARRAY_A );
$custom_role_slugs = wp_list_pluck( $custom_roles, 'role_slug' );
$custom_role_names = array_column( $custom_roles, 'display_name', 'role_slug' );

// Combine default and custom roles for dropdowns.
$filtered_roles = array();
foreach ( $wp_roles->roles as $role_key => $role_data ) {
	$filtered_roles[ $role_key ] = array(
		'name' => in_array( $role_key, $custom_role_slugs, true ) && isset( $custom_role_names[ $role_key ] )
			? $custom_role_names[ $role_key ]
			: $role_data['name'],
	);
}

$role_manager    = ADVAUSRO_RoleManager::advausro_get_instance();
$protected_roles = $role_manager->advausro_get_protected_roles();
?>

	<?php require_once ADVAUSRO_PLUGIN_PATH . 'admin/views/advausro-header.php'; ?>
<div class="wrap">

 
	<div class="advausro-segmented-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Dashboard section', 'advanced-use-role-management' ); ?>">
		<a href="javascript:void(0);" class="advausro-segmented-tab nav-tab-active" id="tab-user-role-management" role="tab" aria-selected="true"><?php esc_html_e( 'Advanced User Role Manager', 'advanced-use-role-management' ); ?></a>
		<a href="javascript:void(0);" class="advausro-segmented-tab" id="tab-assign-temp-role" role="tab" aria-selected="false"><?php esc_html_e( 'Assign Temporary Role', 'advanced-use-role-management' ); ?></a>
	</div>

	<hr class="wp-header-end">

	<div id="user-role-management-content" style="margin-top: 8px;">
		<form id="advausro-user-role-management-form" method="POST">
			<?php wp_nonce_field( 'advausro_role_action_nonce' ); ?>
			<input type="hidden" name="advausro_action" id="advausro_action" value="">

			<div class="crm-box" style="padding: 24px; border-radius: 12px; background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">

			<div class="tablenav top" style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; height: auto; min-height: auto; border-bottom: 1px solid #f3f4f6; padding-bottom: 20px; margin: 0 0 16px 0; width: 100%;">
				
				<div style="position: relative; display: flex; align-items: center; flex: 1; min-width: 120px;">
					<svg style="position: absolute; left: 10px; pointer-events: none; color: #6b7280;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
					<select name="role_filter" id="role_filter" style="height: 40px; padding-left: 30px; padding-right: 28px; width: 100%; margin: 0; border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; font-size: 0.875rem; color: #374151; box-shadow: none; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-image: url('data:image/svg+xml,%3Csvg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;16&quot; height=&quot;16&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;%236b7280&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;%3E%3Cpolyline points=&quot;6 9 12 15 18 9&quot;%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 10px center;">
						<option value="">All Roles</option>
						<?php foreach ( $filtered_roles as $slug => $details ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $role_filter, $slug ); ?>><?php echo esc_html( $details['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<button type="submit" style="flex: 1; min-width: 90px; height: 40px; display: inline-flex; justify-content: center; align-items: center; gap: 6px; margin: 0; border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; font-size: 0.875rem; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif; padding: 0 12px; cursor: pointer;">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
					Filter
				</button>
				<?php if ( ! empty( $search_term ) || ! empty( $role_filter ) ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( array( 's', 'role_filter', 'paged' ) ) ); ?>" style="color: #6b7280; font-size: 0.875rem; text-decoration: underline; margin-left: 0; flex-shrink: 0;">Reset</a>
				<?php endif; ?>

				<div style="width: 1px; height: 24px; background: #e5e7eb; margin: 0 4px; flex-shrink: 0;"></div>

				<div style="position: relative; display: flex; align-items: center; flex: 1; min-width: 120px;">
					<select name="add_role" id="add_role_dropdown" style="height: 40px; padding-left: 12px; padding-right: 28px; width: 100%; margin: 0; border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; font-size: 0.875rem; color: #374151; box-shadow: none; outline: none; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-image: url('data:image/svg+xml,%3Csvg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;16&quot; height=&quot;16&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;%236b7280&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;%3E%3Cpolyline points=&quot;6 9 12 15 18 9&quot;%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 10px center;">
						<option value="" id="add_role_val">Add Role</option>
						<?php foreach ( $filtered_roles as $advausro_role_key => $advausro_role_data ) : ?>
							<option value="<?php echo esc_attr( $advausro_role_key ); ?>"><?php echo esc_html( $advausro_role_data['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" onclick="document.getElementById('advausro_action').value='add';" style="flex: 1; min-width: 80px; height: 40px; display: inline-flex; justify-content: center; align-items: center; margin: 0; border: none; border-radius: 8px; background: #6366f1; color: #fff; font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif; padding: 0 16px; cursor: pointer; transition: background 0.15s ease;">Add</button>

				<div style="position: relative; display: flex; align-items: center; flex: 1; min-width: 120px;">
					<select name="remove_role" id="remove_role_dropdown" style="height: 40px; padding-left: 12px; padding-right: 28px; width: 100%; margin: 0; border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; font-size: 0.875rem; color: #374151; box-shadow: none; outline: none; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-image: url('data:image/svg+xml,%3Csvg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;16&quot; height=&quot;16&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;%236b7280&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;%3E%3Cpolyline points=&quot;6 9 12 15 18 9&quot;%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 10px center;">
						<option value="">Remove Role</option>
						<?php foreach ( $filtered_roles as $advausro_role_key => $advausro_role_data ) : ?>
							<option value="<?php echo esc_attr( $advausro_role_key ); ?>"><?php echo esc_html( $advausro_role_data['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" onclick="document.getElementById('advausro_action').value='remove';" style="flex: 1; min-width: 80px; height: 40px; display: inline-flex; justify-content: center; align-items: center; margin: 0; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #ef4444; font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif; padding: 0 16px; cursor: pointer; transition: background 0.15s ease;">Remove</button>

				<div style="position: relative; display: flex; align-items: center; width: 240px; max-width: 100%; flex: 0 0 auto; margin-left: auto;">
					<input type="search" name="s" id="user_search" placeholder="Search users..." style="border: 1px solid #d1d5db; border-radius: 8px; height: 40px; padding: 0 12px 0 36px; font-size: 0.875rem; color: #374151; width: 100%; box-sizing: border-box;" value="<?php echo esc_attr( $search_term ); ?>">
					<span class="dashicons dashicons-search" style="position: absolute; left: 10px; color: #9ca3af; font-size: 16px;"></span>
				</div>
			</div>



			<?php
			/**
			 * Get avatar gradient style based on character.
			 *
			 * @param string $char The first character of the username.
			 * @return string CSS gradient string.
			 */
			function advausro_get_avatar_gradient( $char ) {
				$char      = strtoupper( substr( $char, 0, 1 ) );
				$gradients = array(
					'A' => 'linear-gradient(135deg, #4ade80, #3b82f6)',
					'B' => 'linear-gradient(135deg, #a3e635, #ecfac4)', // green/yellow.
					'C' => 'linear-gradient(135deg, #f87171, #fbcfe8)',
					'D' => 'linear-gradient(135deg, #a3e635, #4ade80)',
					'E' => 'linear-gradient(135deg, #818cf8, #a78bfa)',
					'F' => 'linear-gradient(135deg, #fbbf24, #fde68a)',
					'G' => 'linear-gradient(135deg, #34d399, #10b981)',
					'H' => 'linear-gradient(135deg, #f472b6, #fb7185)',
					'I' => 'linear-gradient(135deg, #38bdf8, #7dd3fc)',
					'J' => 'linear-gradient(135deg, #fb923c, #818cf8)', // orange/purple.
					'K' => 'linear-gradient(135deg, #a78bfa, #c084fc)',
					'L' => 'linear-gradient(135deg, #f87171, #fca5a5)',
					'M' => 'linear-gradient(135deg, #34d399, #3b82f6)',
					'N' => 'linear-gradient(135deg, #94a3b8, #cbd5e1)',
					'O' => 'linear-gradient(135deg, #fbbf24, #f59e0b)',
					'P' => 'linear-gradient(135deg, #a78bfa, #8b5cf6)',
					'Q' => 'linear-gradient(135deg, #38bdf8, #0ea5e9)',
					'R' => 'linear-gradient(135deg, #f472b6, #ec4899)',
					'S' => 'linear-gradient(135deg, #a78bfa, #34d399)',
					'T' => 'linear-gradient(135deg, #a3e635, #a78bfa)',
					'U' => 'linear-gradient(135deg, #fb923c, #f97316)',
					'V' => 'linear-gradient(135deg, #34d399, #059669)',
					'W' => 'linear-gradient(135deg, #38bdf8, #0284c7)',
					'X' => 'linear-gradient(135deg, #94a3b8, #64748b)',
					'Y' => 'linear-gradient(135deg, #fbbf24, #d97706)',
					'Z' => 'linear-gradient(135deg, #f87171, #dc2626)',
				);
				return isset( $gradients[ $char ] ) ? $gradients[ $char ] : 'linear-gradient(135deg, #9ca3af, #d1d5db)';
			}

			/**
			 * Get role pill style based on role name.
			 *
			 * @param string $role The role slug.
			 * @return string CSS style string.
			 */
			function advausro_get_role_style( $role ) {
				// Predefined styles based on the image.
				if ( strpos( strtolower( $role ), 'administrator' ) !== false ) {
					return 'background: #fee2e2; color: #ef4444; border: 1px solid #fecaca;';
				}
				if ( strpos( strtolower( $role ), 'editor' ) !== false ) {
					return 'background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe;';
				}
				if ( strpos( strtolower( $role ), 'subscriber' ) !== false ) {
					return 'background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;';
				}
				if ( strpos( strtolower( $role ), 'ppress' ) !== false ) {
					return 'background: #f5f3ff; color: #8b5cf6; border: 1px solid #ddd6fe;';
				}
				// Default.
				return 'background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;';
			}
			?>
			<div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-top: 0; background: #ffffff;">
			<table class="advausro-users-table" style="width: 100%; border-collapse: collapse; margin: 0;">
				<thead>
					<tr style="border-bottom: 1px solid #f3f4f6;">
						<th style="padding: 16px 20px; text-align: left; width: 40px;">
							<input type="checkbox" id="advausro-select-all" style="border-radius: 4px; border: 1px solid #9ca3af; width: 16px; height: 16px; cursor: pointer;">
						</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">
							Username
						</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">
							Name
						</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">
							Email
						</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">
							Roles
						</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">
							Posts
						</th>
					</tr>
				</thead>
				<tbody id="users_table_body">
					<?php
					$row_count = 0;
					foreach ( $advausro_users as $advausro_user ) :
						$user_obj          = get_userdata( $advausro_user['ID'] );
						$user_roles        = $user_obj ? $user_obj->roles : array();
						$is_protected_user = ! empty( array_intersect( $user_roles, $protected_roles ) );
						$row_bg            = 0 === $row_count % 2 ? '#fcfcfc' : '#ffffff';
						++$row_count;

						$char        = substr( $advausro_user['user_login'], 0, 1 );
						$bg_gradient = advausro_get_avatar_gradient( $char );
						?>
						<tr style="border-bottom: 1px solid #f3f4f6; background-color: <?php echo esc_attr( $is_protected_user ? '#fff9f0' : $row_bg ); ?>; transition: background 0.15s;">
							<td style="padding: 16px 20px; vertical-align: middle;">
								<input type="checkbox" name="user_ids[]" class="user_checkbox" value="<?php echo esc_attr( $advausro_user['ID'] ); ?>" 
									data-roles="<?php echo esc_attr( implode( ',', $user_roles ) ); ?>"
									style="border-radius: 4px; border: 1px solid #9ca3af; width: 16px; height: 16px; cursor: pointer;"
									<?php
									if ( $is_protected_user ) {
										echo 'disabled style="opacity: 0.5;" title="' . esc_attr__( 'User with protected roles cannot be deleted', 'advanced-use-role-management' ) . '"';} // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attributes with translated title escaped above. 
									?>
									>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle;">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo esc_attr( $bg_gradient ); ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 13px; text-transform: uppercase;">
										<?php echo esc_html( $char ); ?>
									</div>
									<span style="font-weight: 600; color: #4f46e5; font-size: 14px; font-family: 'Inter', sans-serif;">
										<?php echo esc_html( $advausro_user['user_login'] ); ?>
									</span>
								</div>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle; color: #111827; font-weight: 600; font-size: 14px; font-family: 'Inter', sans-serif;">
								<?php echo esc_html( $advausro_user['display_name'] ); ?>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle; color: #6b7280; font-size: 14px; font-family: 'Inter', sans-serif;">
								<?php echo esc_html( $advausro_user['user_email'] ); ?>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle;">
								<div style="display: flex; gap: 8px; flex-wrap: wrap;">
									<?php
									$roles_arr = get_userdata( $advausro_user['ID'] )->roles;
									foreach ( $roles_arr as $r ) {
										$pill_style = advausro_get_role_style( $r );
										echo '<span style="display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; font-family: \'Inter\', sans-serif; ' . esc_attr( $pill_style ) . '">' . esc_html( $r ) . '</span>';
									}
									if ( empty( $roles_arr ) ) {
										echo '<span style="color: #9ca3af; font-size: 13px;">None</span>';
									}
									?>
								</div>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle; color: #111827; font-size: 14px; font-weight: 500; font-family: 'Inter', sans-serif;">
								<?php
								echo esc_html(
									$wpdb->get_var(
										$wpdb->prepare(
											"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_status = 'publish'",
											$advausro_user['ID']
										)
									)
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			
			<div class="tablenav bottom" style="margin-top: 16px;">
				<div class="tablenav-pages">
					<div class="align_left">
						<div class="tablenav top" style="background: transparent; border: none; padding: 0;">
							<div class="alignleft actions bulkactions" id="bulk-action-form">
								<select name="bulk_action_top" id="bulk_action">
									<option value="">Bulk actions</option>
									<option value="add_role">Add Role</option>
									<option value="remove_role">Remove Role</option>
									<option value="delete">Delete</option>
									<option value="export">Export Roles</option>
								</select>
								<select name="bulk_role" id="bulk_role" style="display:none;">
									<option value="">Select Role</option>
									<?php foreach ( $filtered_roles as $slug => $details ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $details['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="datetime-local" id="bulk_expiry" name="bulk_expiry" style="display:none;" value="<?php echo esc_attr( $default_expires_at_value ); ?>">
								<button class="button action" type="submit" id="do-bulk-action">Apply</button>
							</div>
						</div>
					</div>
					<div class="align-right">
						<span class="displaying-num"><?php echo (int) $total_users; ?> items</span>
						<span class="pagination-links">
							<?php
							$first_page_url = add_query_arg( 'paged', 1 );
							$prev_page_url  = add_query_arg( 'paged', max( 1, $paged - 1 ) );
							$next_page_url  = add_query_arg( 'paged', min( $total_pages, $paged + 1 ) );
							$last_page_url  = add_query_arg( 'paged', $total_pages );
							?>
							<a class="first-page button <?php echo esc_attr( $paged <= 1 ? 'disabled' : '' ); ?>" title="Go to the first page" href="<?php echo esc_url( $first_page_url ); ?>">«</a>
							<a class="prev-page button <?php echo esc_attr( $paged <= 1 ? 'disabled' : '' ); ?>" title="Go to the previous page" href="<?php echo esc_url( $prev_page_url ); ?>">‹</a>
							<span class="paging-input">
								<span class="current-page"><?php echo (int) $paged; ?></span> of <span class="total-pages"><?php echo (int) $total_pages; ?></span>
							</span>
							<a class="next-page button <?php echo esc_attr( $paged >= $total_pages ? 'disabled' : '' ); ?>" title="Go to the next page" href="<?php echo esc_url( $next_page_url ); ?>">›</a>
							<a class="last-page button <?php echo esc_attr( $paged >= $total_pages ? 'disabled' : '' ); ?>" title="Go to the last page" href="<?php echo esc_url( $last_page_url ); ?>">»</a>
						</span>
					</div>
				</div>
				<br class="clear">
			</div>
			
			</div> <!-- /crm-box -->
		</form>
	</div>

	<div id="assign-temp-role-content" style="display:none; margin-top: 20px;">
		<div class="crm-box" style="margin-bottom: 30px; min-height: auto; padding: 24px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff;">
			<h2 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 4px 0;">Assign Temporary Role</h2>
			<p style="color: #6b7280; font-size: 14px; margin: 0 0 24px 0;">Temporarily grant user roles with automatic expiration control.</p>
			<form id="advausro-assign-temp-role-form">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
					<div class="label_input" style="margin: 0; display: flex; flex-direction: column;">
						<label for="user_id" style="font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 8px;">User</label>
						<select id="user_id" name="user_id" required style="width: 100% !important; max-width: none !important; height: 42px; border-radius: 8px; border: 1px solid #d1d5db; padding: 0 12px; margin: 0;">
							<option value="">Select a user</option>
							<?php
							$advausro_users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
							foreach ( $advausro_users as $advausro_user ) {
								echo "<option value='" . esc_attr( $advausro_user->ID ) . "'>" . esc_html( $advausro_user->display_name ) . '</option>';
							}
							?>
						</select>
					</div>
					<div class="label_input" style="margin: 0; display: flex; flex-direction: column;">
						<label for="role_slug" style="font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 8px;">Role</label>
						<select id="role_slug" name="role_slug" required style="width: 100% !important; max-width: none !important; height: 42px; border-radius: 8px; border: 1px solid #d1d5db; padding: 0 12px; margin: 0;">
							<option value="">Select a role</option>
							<?php foreach ( $filtered_roles as $slug => $details ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $details['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="label_input" style="margin: 0; display: flex; flex-direction: column;">
						<label for="expires_at" style="font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 8px;">Expires At</label>
						<input type="datetime-local" id="expires_at" name="expires_at" required style="width: 100% !important; max-width: none !important; height: 42px; border-radius: 8px; border: 1px solid #d1d5db; padding: 0 12px; margin: 0; box-sizing: border-box;" value="<?php echo esc_attr( $default_expires_at_value ); ?>">
					</div>
				</div>
				<p class="description" style="color: #6b7280; font-size: 14px; margin: -10px 0 24px 0;">Time is in your local timezone: <strong style="color: #4b5563;"><?php echo esc_html( wp_timezone_string() ? wp_timezone_string() : 'Not set' ); ?></strong></p>
				<div>
					<button type="submit" class="button button-primary crm-btn" style="width: auto; min-width: 150px; margin: 0; background-color: #6366f1; border-color: #6366f1; border-radius: 8px; padding: 0 20px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
							<circle cx="9" cy="7" r="4"></circle>
							<polyline points="16 11 18 13 22 9"></polyline>
						</svg>
						Assign Role
					</button>
				</div>
			</form>
		</div>

		<div class="crm-box" style="min-height: auto;">
			<h2 style="margin-bottom: 20px; font-size: 1.25rem; font-weight: 600; color: #111827;">Current Temporary Roles</h2>
		<?php
		global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the local WordPress prefix.
		$entries = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}advausro_temp_roles" );
		if ( $entries ) :
			?>
			<table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.02); overflow: hidden;">
				<thead>
					<tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">User</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">Role</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">Expires</th>
						<th style="padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">Countdown</th>
						<th style="padding: 16px 20px; text-align: right; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">Action</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$row_count = 0;
					foreach ( $entries as $advausro_entry ) :
						$advausro_user       = get_user_by( 'ID', $advausro_entry->user_id );
						$advausro_expires_dt = new \DateTime( $advausro_entry->expires_at, new \DateTimeZone( wp_timezone_string() ) );
						$expires_iso         = $advausro_expires_dt->format( 'c' );
						$row_bg              = 0 === $row_count % 2 ? '#ffffff' : '#fafafa';
						++$row_count;

						$display_name        = $advausro_user ? $advausro_user->display_name : 'Unknown';
						$advausro_user_email = $advausro_user ? $advausro_user->user_email : '';
						$char                = substr( $display_name, 0, 1 );
						// Use simple light gray for avatar.
						$bg_gradient = '#f8fafc';
						$text_color  = '#374151';
						?>
						<tr id="advausro-row-<?php echo esc_attr( $advausro_entry->id ); ?>" style="border-bottom: 1px solid #e5e7eb; background-color: <?php echo esc_attr( $row_bg ); ?>;">
							<td style="padding: 16px 20px; vertical-align: middle;">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 36px; height: 36px; border-radius: 8px; background: <?php echo esc_attr( $bg_gradient ); ?>; display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr( $text_color ); ?>; font-weight: 600; font-size: 14px; text-transform: uppercase; font-family: 'Inter', sans-serif; border: 1px solid #f1f5f9;">
										<?php echo esc_html( $char ); ?>
									</div>
									<div style="display: flex; flex-direction: column;">
										<span style="font-weight: 600; color: #111827; font-size: 14px; font-family: 'Inter', sans-serif;">
											<?php echo esc_html( $display_name ); ?>
										</span>
										<?php if ( $advausro_user_email ) : ?>
										<span style="color: #9ca3af; font-size: 12px; font-family: 'Inter', sans-serif; margin-top: 2px;">
											<?php echo esc_html( $advausro_user_email ); ?>
										</span>
										<?php endif; ?>
									</div>
								</div>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle;">
								<span style="display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; background: #dbeafe; color: #2563eb;">
									<?php echo esc_html( str_replace( '_', ' ', $advausro_entry->role_slug ) ); ?>
								</span>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle; color: #6b7280; font-size: 13px; font-family: 'Inter', sans-serif;">
								<?php echo esc_html( $advausro_expires_dt->format( 'm/d/Y, h:i A' ) ); ?>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle;">
								<!-- Updated styling based on the image -->
								<div class="expires-countdown-wrapper" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; background: #fef3c7; color: #d97706;">
									<span style="width: 6px; height: 6px; border-radius: 50%; background: #fbbf24;"></span>
									<span class="expires-countdown" data-expires="<?php echo esc_attr( $expires_iso ); ?>"></span>
								</div>
							</td>
							<td style="padding: 16px 20px; vertical-align: middle; text-align: right;">
								<button type="button" class="remove-temp-role" data-role-id="<?php echo (int) $advausro_entry->id; ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: background 0.15s ease;">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<polyline points="3 6 5 6 21 6"></polyline>
										<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
									</svg>
									Remove
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div style="padding: 24px; text-align: center; color: #6b7280; font-family: 'Inter', sans-serif; background: #f9fafb; border-radius: 12px; border: 1px dashed #e5e7eb;">
				No temporary roles assigned.
			</div>
		<?php endif; ?>
		</div> <!-- Close .crm-box -->
	</div> <!-- Close #assign-temp-role-content -->
</div> <!-- Close .wrap -->