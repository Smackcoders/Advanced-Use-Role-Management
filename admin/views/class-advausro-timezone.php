<?php
/**
 * Timezone settings admin view for Advanced User Role Manager.
 *
 * @package Advanced_User_Role_Manager
 */

namespace SmackCoders\AdvancedUserRoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Only render if timezone is pending and user has permissions.
if ( ! get_option( 'advausro_timezone_pending' ) || ! current_user_can( 'read' ) ) {
	return;
}

// List of timezones (subset for brevity, matching WordPress timezone options).
$timezones = array(
	'UTC'                            => 'UTC',
	'Pacific/Kwajalein'              => 'UTC-12:00 (Kwajalein)',
	'Pacific/Samoa'                  => 'UTC-11:00 (Samoa)',
	'Pacific/Honolulu'               => 'UTC-10:00 (Honolulu)',
	'America/Anchorage'              => 'UTC-09:00 (Anchorage)',
	'America/Los_Angeles'            => 'UTC-08:00 (Los Angeles)',
	'America/Denver'                 => 'UTC-07:00 (Denver)',
	'America/Chicago'                => 'UTC-06:00 (Chicago)',
	'America/New_York'               => 'UTC-05:00 (New York)',
	'America/Caracas'                => 'UTC-04:00 (Caracas)',
	'America/Argentina/Buenos_Aires' => 'UTC-03:00 (Buenos Aires)',
	'Atlantic/South_Georgia'         => 'UTC-02:00 (South Georgia)',
	'Atlantic/Azores'                => 'UTC-01:00 (Azores)',
	'Europe/London'                  => 'UTC+00:00 (London)',
	'Europe/Paris'                   => 'UTC+01:00 (Paris)',
	'Europe/Helsinki'                => 'UTC+02:00 (Helsinki)',
	'Europe/Moscow'                  => 'UTC+03:00 (Moscow)',
	'Asia/Dubai'                     => 'UTC+04:00 (Dubai)',
	'Asia/Karachi'                   => 'UTC+05:00 (Karachi)',
	'Asia/Kolkata'                   => 'UTC+05:30 (Kolkata)',
	'Asia/Dhaka'                     => 'UTC+06:00 (Dhaka)',
	'Asia/Bangkok'                   => 'UTC+07:00 (Bangkok)',
	'Asia/Shanghai'                  => 'UTC+08:00 (Shanghai)',
	'Asia/Tokyo'                     => 'UTC+09:00 (Tokyo)',
	'Australia/Sydney'               => 'UTC+10:00 (Sydney)',
	'Pacific/Guadalcanal'            => 'UTC+11:00 (Guadalcanal)',
	'Pacific/Auckland'               => 'UTC+12:00 (Auckland)',
);
?>

<div id="advausro-timezone-modal" class="advausro-timezone-modal">
	<div class="advausro-timezone-backdrop"></div>
	<div class="advausro-timezone-content">
		<h2>Set Your Local Timezone</h2>
		<p>To ensure the AURM plugin works with your local timezone, please select your timezone from the list below.</p>
		<form id="advausro-timezone-form">
			<div class="advausro-timezone-field">
				<label for="advausro-timezone">Your Timezone:</label>
				<select id="advausro-timezone" name="timezone" required>
					<option value="">Select a timezone</option>
					<?php foreach ( $timezones as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="advausro-timezone-buttons">
				<button type="button" id="advausro-skip-timezone" class="button">Skip</button>
				<button type="submit" class="button advausro-set-button">Set Timezone</button>
			</div>
		</form>
	</div>
</div>
