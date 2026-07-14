<?php
/**
 * Uninstall Advanced User Role Manager
 *
 * @package AdvancedUserRoleManager
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/installation/class-advausro-install.php';

\SmackCoders\AdvancedUserRoleManager\ADVAUSRO_Install::advausro_uninstall();
