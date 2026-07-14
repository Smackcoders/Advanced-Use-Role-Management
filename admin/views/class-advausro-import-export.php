<?php
/**
 * Import/Export admin view for Advanced User Role Manager.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$advausro_import_notice      = '';
$advausro_import_notice_type = 'success';
$advausro_role_manager       = ADVAUSRO_RoleManager::advausro_get_instance();

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Role export is handled in ADVAUSRO_Connector::advausro_handle_exports().

// Handle Import.
if (
	isset( $_POST['advausro_import_roles_action'], $_FILES['import_file']['tmp_name'] ) &&
	! empty( $_FILES['import_file']['tmp_name'] )
) {
	if ( ! isset( $_POST['advausro_import_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['advausro_import_export_nonce'] ) ), 'advausro_import_export_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'advanced-use-role-management' ) );
	}

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual file fields are sanitized immediately below.
	$advausro_import_file = wp_unslash( $_FILES['import_file'] );
	$file                 = isset( $advausro_import_file['tmp_name'] ) ? sanitize_text_field( $advausro_import_file['tmp_name'] ) : '';
	$file_type            = isset( $advausro_import_file['type'] ) ? sanitize_mime_type( $advausro_import_file['type'] ) : '';
	$file_name            = isset( $advausro_import_file['name'] ) ? sanitize_file_name( $advausro_import_file['name'] ) : '';

	if ( empty( $file ) || ! is_uploaded_file( $file ) || ( 'application/json' !== $file_type && ! str_ends_with( $file_name, '.json' ) ) ) {
		wp_die( esc_html__( 'Invalid import file.', 'advanced-use-role-management' ) );
	}

	$json_content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local uploaded temp file.
	$data         = json_decode( $json_content, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
		$advausro_import_notice      = __( 'Invalid JSON file or unsupported structure.', 'advanced-use-role-management' );
		$advausro_import_notice_type = 'error';
	} elseif ( empty( $data ) ) {
		$advausro_import_notice      = __( 'The import file is empty.', 'advanced-use-role-management' );
		$advausro_import_notice_type = 'warning';
	} else {
		$count         = 0;
		$skipped       = 0;
		$invalid       = 0;
		$skipped_roles = array();
		$invalid_roles = array();
		$roles_table   = $wpdb->prefix . 'advausro_custom_roles';
		$caps_table    = $wpdb->prefix . 'advausro_role_capabilities';

		foreach ( $data as $role_data ) {
			if ( ! is_array( $role_data ) ) {
				++$invalid;
				$invalid_roles[] = __( 'Unknown role entry', 'advanced-use-role-management' );
				continue;
			}

			$slug          = sanitize_title( $role_data['role_slug'] ?? '' );
			$name          = sanitize_text_field( $role_data['role_name'] ?? '' );
			$display       = sanitize_text_field( $role_data['display_name'] ?? '' );
			$advausro_type = isset( $role_data['type'] ) ? sanitize_key( $role_data['type'] ) : 'default';
			$caps          = isset( $role_data['capabilities'] ) && is_array( $role_data['capabilities'] )
				? $advausro_role_manager->advausro_normalize_capabilities( $role_data['capabilities'] )
				: array();

			if ( empty( $slug ) || empty( $display ) ) {
				++$invalid;
				$invalid_roles[] = ! empty( $display ) ? $display : __( 'Unknown role entry', 'advanced-use-role-management' );
				continue;
			}

			// Sync with WordPress (works for both default and custom).
			$existing_role = get_role( $slug );
			if ( $existing_role ) {
				++$skipped;
				$skipped_roles[] = $display;
				continue;
			} else {
				$wp_caps_array         = array_fill_keys( $caps, true );
				$wp_caps_array['read'] = true;
				$created_role          = add_role( $slug, $display, $wp_caps_array );

				if ( ! $created_role ) {
					++$invalid;
					$invalid_roles[] = $display;
					continue;
				}

				// For custom roles, insert in our tables.
				if ( 'custom' === $advausro_type ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is built from the local WordPress prefix.
					$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$roles_table} WHERE role_slug = %s", $slug ) );

					if ( ! $existing_id ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required write for role import.
						$wpdb->insert(
							$roles_table,
							array(
								'role_slug'    => $slug,
								'role_name'    => $name,
								'display_name' => $display,
								'created_at'   => current_time( 'mysql' ),
							)
						);
						$role_id = $wpdb->insert_id;

						foreach ( $caps as $cap ) {
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required write for capability import.
							$wpdb->insert(
								$caps_table,
								array(
									'role_id'    => (int) $role_id,
									'capability' => $cap,
								)
							);
						}
					}
				}
			}

			++$count;
		}

		$notice_parts = array();

		if ( $count > 0 ) {
			$notice_parts[] = sprintf(
				/* translators: %d: Imported role count. */
				_n( '%d role imported successfully.', '%d roles imported successfully.', $count, 'advanced-use-role-management' ),
				$count
			);
		}

		if ( $skipped > 0 ) {
			$notice_parts[] = sprintf(
				/* translators: 1: Skipped role count. 2: Role names. */
				__( '%1$d existing roles were skipped: %2$s', 'advanced-use-role-management' ),
				$skipped,
				implode( ', ', array_map( 'sanitize_text_field', $skipped_roles ) )
			);
		}

		if ( $invalid > 0 ) {
			$notice_parts[] = sprintf(
				/* translators: 1: Invalid role count. 2: Role names. */
				__( '%1$d invalid entries were ignored: %2$s', 'advanced-use-role-management' ),
				$invalid,
				implode( ', ', array_map( 'sanitize_text_field', $invalid_roles ) )
			);
		}

		if ( empty( $notice_parts ) ) {
			$notice_parts[] = __( 'No roles were imported.', 'advanced-use-role-management' );
		}

		$advausro_import_notice      = implode( ' ', $notice_parts );
		$advausro_import_notice_type = $count > 0 ? 'success' : ( $invalid > 0 ? 'error' : 'warning' );
	}
}

$wp_roles_obj = get_editable_roles();
?>

<?php require_once ADVAUSRO_PLUGIN_PATH . 'admin/views/advausro-header.php'; ?>
<div class="wrap crm-container">

	<?php if ( ! empty( $advausro_import_notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $advausro_import_notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $advausro_import_notice ); ?></p>
		</div>
	<?php endif; ?>
	
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-top: 5px;">
		
		<!-- Export Section -->
		<div class="crm-box" style="padding: 32px;">
			<div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
				<div style="background: #eef2ff; color: #4f46e5; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
					<span class="dashicons dashicons-upload"></span>
				</div>
				<div>
					<h2 style="margin: 0 0 4px 0; font-size: 1rem; color: #111827; font-weight: 600;">Export Roles</h2>
					<p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Select roles to export as JSON</p>
				</div>
			</div>

			<form method="POST" style="flex: 1; display: flex; flex-direction: column;">
				<?php wp_nonce_field( 'advausro_import_export_action', 'advausro_import_export_nonce' ); ?>
				
				<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px 8px 0 0; border-bottom: none;">
					<label style="display: flex; align-items: center; gap: 8px; font-weight: 500; font-size: 0.875rem; color: #374151; margin: 0; cursor: pointer;">
						<input type="checkbox" id="advausro-select-all-export" style="border-radius: 4px; border-color: #d1d5db;"> Select All
					</label>
					<span id="advausro-selected-count" style="background: #e5e7eb; color: #4b5563; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">0 selected</span>
				</div>
				
				<div style="border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; max-height: 280px; overflow-y: auto; background: #ffffff; margin-bottom: 24px;">
					<?php
					foreach ( $wp_roles_obj as $slug => $details ) :
						$cap_count = count( $details['capabilities'] );
						?>
						<label style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; cursor: pointer; margin: 0; transition: background 0.1s;">
							<input type="checkbox" class="advausro-export-checkbox" name="export_roles[]" value="<?php echo esc_attr( $slug ); ?>" style="border-radius: 4px; border-color: #d1d5db;">
							<div style="display: flex; flex-direction: column;">
								<strong style="color: #111827; font-size: 0.875rem; font-weight: 600;"><?php echo esc_html( $details['name'] ); ?></strong>
								<span style="color: #6b7280; font-size: 0.75rem;"><?php echo esc_html( $slug ); ?> &middot; <?php echo esc_html( (string) $cap_count ); ?> capabilities</span>
							</div>
						</label>
					<?php endforeach; ?>
				</div>

				<button type="submit" name="advausro_export_roles_action" class="crm-btn" style="align-self: flex-end; width: auto !important; margin-top: auto;">
					<span class="dashicons dashicons-download" style="margin-right: 8px;"></span> Export Selected Roles
				</button>
			</form>
		</div>

		<!-- Import Section -->
		<div class="crm-box" style="padding: 32px;">
			<div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
				<div style="background: #dcfce7; color: #10b981; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
					<span class="dashicons dashicons-download"></span>
				</div>
				<div>
					<h2 style="margin: 0 0 4px 0; font-size: 1rem; color: #111827; font-weight: 600;">Import Roles</h2>
					<p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Upload a JSON file to import roles and capabilities</p>
				</div>
			</div>

			<form method="POST" enctype="multipart/form-data" id="advausro-import-roles-form" style="flex: 1; display: flex; flex-direction: column;">
				<?php wp_nonce_field( 'advausro_import_export_action', 'advausro_import_export_nonce' ); ?>
				
				<div style="border: 2px dashed #d1d5db; border-radius: 12px; padding: 40px 20px; text-align: center; background: #f9fafb; margin-bottom: 20px; position: relative; transition: border-color 0.2s, background 0.2s;" id="advausro-dropzone">
					<input type="file" name="import_file" accept=".json" required style="opacity: 0; position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; z-index: 10;" id="advausro-import-file">
					<span class="dashicons dashicons-media-document" style="font-size: 40px; width: 40px; height: 40px; color: #9ca3af; margin-bottom: 12px;"></span>
					<p style="margin: 0 0 4px 0; color: #374151; font-weight: 500; font-size: 0.875rem;">Drag & drop JSON file or <span style="color: #4f46e5;">click to upload</span></p>
					<p style="margin: 0; color: #6b7280; font-size: 0.75rem;">Only .json files accepted</p>
					<p id="advausro-file-name" style="margin: 12px 0 0 0; color: #10b981; font-weight: 600; font-size: 0.875rem; display: none;"></p>
				</div>

				<div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 24px;">
					<span class="dashicons dashicons-info" style="color: #f59e0b; margin-top: 2px;"></span>
					<p style="margin: 0; color: #92400e; font-size: 0.875rem; line-height: 1.4;">Existing roles are skipped during import. Only new roles from the JSON file will be created.</p>
				</div>

				<button type="submit" name="advausro_import_roles_action" class="crm-btn" style="align-self: flex-end; width: auto !important; margin-top: auto;">
					<span class="dashicons dashicons-upload" style="margin-right: 8px;"></span> Import Roles
				</button>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Checkbox logic
	const selectAll = document.getElementById('advausro-select-all-export');
	const checkboxes = document.querySelectorAll('.advausro-export-checkbox');
	const countDisplay = document.getElementById('advausro-selected-count');

	function updateCount() {
		const checked = document.querySelectorAll('.advausro-export-checkbox:checked').length;
		countDisplay.textContent = checked + ' selected';
		if (selectAll) {
			selectAll.checked = checked === checkboxes.length && checkboxes.length > 0;
		}
	}

	if (selectAll) {
		selectAll.addEventListener('change', function() {
			checkboxes.forEach(cb => cb.checked = selectAll.checked);
			updateCount();
		});
	}

	checkboxes.forEach(cb => cb.addEventListener('change', updateCount));

	// File input logic
	const fileInput = document.getElementById('advausro-import-file');
	const fileNameDisplay = document.getElementById('advausro-file-name');
	const dropzone = document.getElementById('advausro-dropzone');

	if (fileInput) {
		fileInput.addEventListener('change', function() {
			if (this.files && this.files.length > 0) {
				fileNameDisplay.textContent = 'Selected: ' + this.files[0].name;
				fileNameDisplay.style.display = 'block';
				dropzone.style.borderColor = '#10b981';
				dropzone.style.background = '#f0fdf4';
			} else {
				fileNameDisplay.style.display = 'none';
				dropzone.style.borderColor = '#d1d5db';
				dropzone.style.background = '#f9fafb';
			}
		});

		dropzone.addEventListener('dragover', function() {
			dropzone.style.borderColor = '#4f46e5';
			dropzone.style.background = '#eff6ff';
		});
		dropzone.addEventListener('dragleave', function() {
			if (!fileInput.files || fileInput.files.length === 0) {
				dropzone.style.borderColor = '#d1d5db';
				dropzone.style.background = '#f9fafb';
			} else {
				dropzone.style.borderColor = '#10b981';
				dropzone.style.background = '#f0fdf4';
			}
		});
		dropzone.addEventListener('drop', function() {
			setTimeout(function() {
				if (fileInput.files && fileInput.files.length > 0) {
					fileNameDisplay.textContent = 'Selected: ' + fileInput.files[0].name;
					fileNameDisplay.style.display = 'block';
					dropzone.style.borderColor = '#10b981';
					dropzone.style.background = '#f0fdf4';
				}
			}, 100);
		});
	}
});
</script>
