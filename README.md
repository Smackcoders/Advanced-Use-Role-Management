# Advanced User Role Manager

[![WordPress](https://img.shields.io/badge/WordPress-6.8+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.0+-green.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress user role plugin for creating custom roles, granting temporary access, and adding secure Google login — with complete audit logging built in.

## Overview

Advanced User Role Manager is a custom user role manager for WordPress that extends the platform's built-in role system with the controls site owners actually need day to day: creating and cloning roles, editing capabilities, granting temporary admin or editor access, adding Google login, and keeping an audit trail of every change.

It exists to solve a common problem — WordPress ships with a fixed set of roles (administrator, editor, contributor, and so on), and most role based access control needs on a real site go beyond that. Teams need to give a contractor temporary admin access without leaving a security hole behind, give a guest editor role to a freelancer for a single project, or simply know who changed a role and when. Advanced User Role Manager acts as a lightweight role expiration plugin, capability manager, and security audit logging plugin in one, wordpress user role plugin.

Who it's for: WordPress agencies managing contractor and freelancer access, membership and e-commerce site owners who need role based permission systems, corporate and intranet sites enforcing access control, and any administrator who wants a searchable audit log of role and capability changes instead of guessing who did what.

## Key Features

- **Custom Role Creation & Cloning**: Create, edit, clone, and delete custom user roles without touching code — a practical alternative to manually editing `wp_roles` or relying solely on `wp_login` filters.
- **Role Capability Editor**: Granular capability editing lets you check or uncheck specific permissions for any role, so you can build a role with exactly the access it needs — no more, no less.
- **Temporary Role Assignment & Expiration**: Assign a role with an expiration date and time; a wp-cron based role expiry process automatically removes access once it's no longer needed, ideal for temporary admin access and time-limited permissions.
- **Google OAuth2 Login**: A built-in Google login integration adds a secure sign-in option to the WordPress login page, with role mapping for users who authenticate through Google as the identity provider.
- **Role Filtering on the Users Dashboard**: Filter and manage users by their custom or native role directly from the WordPress Users screen.
- **Security Audit Logging**: A searchable audit log records every role and capability change with the user and timestamp, giving you a compliance-ready trail for security reviews.
- **Multi-Role Support**: Assign more than one role to a single user where your workflow requires overlapping permissions.
- **Clean Uninstall**: Uninstalling the plugin performs a full database cleanup, removing its custom tables so nothing is left behind.

## Use Cases

- **Contractor and freelancer access management**: Give a contractor temporary admin access or a freelancer guest editor access for the length of a project, then let it expire automatically instead of remembering to revoke it manually.
- **Membership sites**: Support different access levels for different tiers of members.
- **Multi-author blogs and content teams**: Manage varying editorial permissions across contributors, editors, and guest writers.
- **E-commerce sites**: Separate customer-facing accounts from staff roles, with WooCommerce-aware role handling.
- **Corporate and intranet sites**: Apply role based access control across departments and staff levels.
- **Educational platforms**: Distinguish student and teacher roles and the capabilities each should have.
- **Client site management**: Track and review role and capability changes across multiple client logins from a single, searchable audit log.

## Requirements

- **WordPress**: 6.8 or higher (tested up to 7.0)
- **PHP**: 7.0 or higher
- **MySQL**: 5.6 or higher
- **Other**: A Google OAuth2 client ID/secret if you want to enable Google login (optional — the plugin works fully without it)

## Installation

### Install from WordPress

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for "Advanced User Role Manager."
3. Click **Install Now** and then **Activate**.

### Manual Installation

1. Download or clone the repository.
2. Upload the plugin folder to `/wp-content/plugins/advanced-user-role-manager/`.
3. Activate the plugin from **WordPress Admin → Plugins**.
4. Configure the plugin via the **Advanced User Role Manager** menu.

## Configuration / Setup

### Initial Setup

1. Navigate to **Advanced User Role Manager** in your admin menu.
2. Configure basic settings and timezone preferences.
3. Set up Google OAuth2 login (optional).
4. Create your first custom role.

### Google OAuth2 Setup

1. Go to **OAuth2 Settings** in the plugin menu.
2. Enter your Google OAuth2 client credentials.
3. Configure role mapping for users who log in through Google.
4. Test the login integration from the WordPress login page.

## Usage

### Creating Custom Roles

1. Go to **Roles** in the plugin menu.
2. Click **Add New Role**.
3. Enter a role name and display name.
4. Select the capabilities the role should have.
5. Save the role.

### Assigning Temporary Roles

1. Navigate to **User Manager**.
2. Select a user.
3. Choose **Assign Temporary Role**.
4. Set an expiration date and time — the wp-cron based expiry engine removes the role automatically once it's reached.
5. Confirm the assignment.

### Managing Capabilities

1. Go to the **Roles** section.
2. Select a role to edit.
3. Check or uncheck individual capabilities.
4. Save changes.

### Reviewing the Audit Log

1. Open the **Audit Log** screen in the plugin menu.
2. Search or filter by user, role, or date to review who changed what and when.

## Supported Integrations

- WooCommerce and other e-commerce plugins
- Popular membership plugins
- Custom post types
- Google (as an OAuth2 / SSO identity provider for login)

## Screenshots / Demo

1. Role management dashboard
   ![Role management dashboard](screenshot-1.png)
2. Temporary role assignment interface
   ![Temporary role assignment interface](Screenshot-2.png)
3. User filtering by role
   ![User filtering by role](screenshot-3.png)
4. Audit log view
5. Google OAuth2 configuration settings
   ![Google OAuth2 configuration settings](screenshot-5.png)

## Documentation

Full setup and configuration guide: [Plugin Documentation](https://www.smackcoders.com/documentation/user-role-management/getting-started)

## Frequently Asked Questions

### Can I set a role to expire automatically?

Yes. Temporary roles can be assigned with an automatic expiration date and time, handled by a wp-cron based role expiry process, so access is removed without manual follow-up.

### Does this replace User Role Editor or the Members plugin?

Advanced User Role Manager covers the same core ground as tools like User Role Editor — custom role creation, capability editing, and role cloning — while adding temporary/expiring roles, Google login, and built-in audit logging as native features rather than add-ons.

### Can users log in with their Google account?

Yes. The plugin includes a Google OAuth2 login integration with role mapping, so users authenticated through Google are assigned the correct WordPress role automatically.

### Is there a log of who changed a role or capability?

Yes. Every role and capability change is written to a searchable, filterable audit log along with the user and timestamp, giving you a full audit trail for security or compliance review.

### Can I clone an existing role?

Yes. Role cloning lets you duplicate an existing role — including a custom copy of the administrator role with reduced capabilities — as a starting point for a new one, rather than building capabilities from scratch.

### Does uninstalling remove the plugin's database tables?

Yes. Uninstalling performs a clean database cleanup, removing the plugin's custom tables so no leftover data remains.

### Can I assign multiple roles to a single user?

Yes, the plugin supports assigning multiple roles to individual users.

### Is this plugin compatible with WooCommerce?

Yes, it's fully compatible with WooCommerce and supports WooCommerce-specific roles.

### Does the Google OAuth2 integration require coding knowledge?

No. OAuth2 setup is handled entirely through the admin interface with step-by-step guidance.

### Will this conflict with other role management plugins?

It's recommended to run only one role management plugin at a time to avoid conflicting capability changes.

### Troubleshooting

**Plugin not activating**
- Check your PHP version (requires 7.0+).
- Verify your WordPress version (requires 6.8+).
- Check for plugin conflicts.

**Google OAuth2 not working**
- Verify your OAuth2 provider credentials.
- Check the redirect URI configuration.
- Ensure role mapping is set up correctly.

**Roles not appearing**
- Clear your WordPress cache.
- Check database permissions.
- Verify the plugin is activated.

**Debug Mode**
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Roadmap

The following improvements are being explored for upcoming releases. They are not guaranteed and may change before release:

- Additional security hardening
- Improved error handling and admin-facing messaging
- Continued alignment with the latest WordPress coding standards
- General performance improvements

## Changelog

### 1.0.1
- Tested for compatibility with WordPress 7.0

### 1.0.0
- Initial release
- Custom role creation and management
- Temporary role assignments with automatic expiration
- Google OAuth2 login integration
- Comprehensive audit logging
- Role-based user filtering
- Multi-role support

## Security

Advanced User Role Manager is built with a security-first approach to role based access control:

- **SQL Injection Protection**: All database queries use prepared statements.
- **Nonce Verification**: All forms and AJAX requests are secured with WordPress nonces.
- **Capability Checks**: Every action is validated against proper WordPress capability checks before it runs.
- **Input Sanitization**: All user input is sanitized and validated.
- **Audit Logging**: A complete, searchable trail of every role and capability change supports security review and compliance.

### Reporting a Vulnerability

If you discover a security vulnerability, please do not open a public GitHub issue. Instead, email **security@smackcoders.com** with details of the issue and allow time for it to be investigated and addressed before any public disclosure.

## Contributing

Contributions are welcome. To contribute:

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/amazing-feature`).
3. Commit your changes (`git commit -m 'feat: add amazing feature'`).
4. Push to the branch (`git push origin feature/amazing-feature`).
5. Open a Pull Request.

Please follow WordPress coding standards, include capability checks and nonce verification for any new admin actions, and test against the WordPress/PHP versions listed under Requirements. See [CONTRIBUTING.md](CONTRIBUTING.md) for full development setup, coding standards, and the bug/feature request templates.

## Support

- **Documentation**: [Plugin Documentation](https://www.smackcoders.com/documentation/user-role-management/getting-started)
- **Support**: [Contact Support](https://www.smackcoders.com/contact-us.html)
- **Issues**: [GitHub Issues](https://github.com/smackcoders/advanced-user-role-manager/issues)

## License

This project is licensed under the GPL v2 or later — see the [LICENSE](LICENSE) file for details.

## Disclaimer

WordPress, WooCommerce, and Google are trademarks of their respective owners. Advanced User Role Manager is an independent plugin and is not officially affiliated with or endorsed by WordPress.org, Automattic, WooCommerce, or Google. Google OAuth2 login relies on Google's identity platform as a third-party service; use of that integration is subject to Google's own terms.

## Author / Maintainer

Developed and maintained by [Smackcoders](https://www.smackcoders.com/).
