<?php
/**
 * Plugin Name:      Advanced User Role Manager
 * Plugin URI:       https://www.smackcoders.com
 * Description:      Manage, customize, and assign WordPress user roles with ease. Includes temporary roles, Google OAuth login, and audit logs for complete role control.
 * Version:          1.0.1
 * Requires at least: 6.8
 * Requires PHP:     7.0
 * Author:           Smackcoders
 * Donate link:      https://www.smackcoders.com/contact-us.html
 * Author URI:       https://www.smackcoders.com/
 * Text Domain:      advanced-use-role-management
 * License:          GPL-2.0-or-later
 *
 * @package          Advanced_User_Role_Manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADVAUSRO_PLUGIN_FILE', __FILE__ );
define( 'ADVAUSRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADVAUSRO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

use SmackCoders\AdvancedUserRoleManager\ADVAUSRO_Connector;
use SmackCoders\AdvancedUserRoleManager\ADVAUSRO_Install;

require_once ADVAUSRO_PLUGIN_PATH . 'includes/class-advausro-connector.php';
require_once ADVAUSRO_PLUGIN_PATH . 'includes/installation/class-advausro-install.php';

ADVAUSRO_Connector::advausro_get_instance();
ADVAUSRO_Install::advausro_get_instance();

// Text domain loading is handled automatically by WordPress.org.

register_activation_hook( __FILE__, array( 'SmackCoders\AdvancedUserRoleManager\ADVAUSRO_Install', 'advausro_activate' ) );

register_activation_hook(
	__FILE__,
	function () {
		update_option( 'advausro_timezone_pending', true );
	}
);
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'advausro_check_expired_roles' );
	}
);

// Register uninstall hook.
register_uninstall_hook( __FILE__, array( 'SmackCoders\AdvancedUserRoleManager\ADVAUSRO_Install', 'advausro_uninstall' ) );
