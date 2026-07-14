# Advanced User Role Manager

[![WordPress](https://img.shields.io/badge/WordPress-6.8+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.0+-green.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A powerful and intuitive WordPress plugin for managing user roles and capabilities, designed and developed by human developers. Create, assign, and control user access with ease through our carefully crafted interface.

## 🚀 Features

### Core Functionality
- **Custom Role Management**: Create, edit, clone, and delete custom user roles
- **Temporary Role Assignments**: Assign roles with automatic expiration
- **Granular Capability Control**: Manage specific permissions for each role
- **OAuth2 Integration**: Secure login with external providers
- **Role-Based User Filtering**: Filter and manage users by their roles
- **Audit Logging**: Track all role-related changes for security
- **Multi-Role Support**: Assign multiple roles to individual users

### Use Cases
- Membership sites requiring different access levels
- Multi-author blogs with varying permissions
- E-commerce sites with customer and staff roles
- Corporate websites with role-based access control
- Educational platforms with student and teacher roles

### Compatibility
- Works with WooCommerce and other e-commerce plugins
- Compatible with membership plugins
- Supports custom post types
- Built following WordPress coding standards

## 📋 Requirements

- **WordPress**: 6.8 or higher
- **PHP**: 7.0 or higher
- **MySQL**: 5.6 or higher

## 🛠️ Installation

### From WordPress Admin
1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Advanced User Role Manager"
3. Click **Install Now** and then **Activate**

### Manual Installation
1. Download the plugin files
2. Upload to `/wp-content/plugins/advanced-user-role-manager/`
3. Activate the plugin through the **Plugins** screen
4. Configure via **Advanced User Role Manager** menu

## 🔧 Configuration

### Initial Setup
1. Navigate to **Advanced User Role Manager** in your admin menu
2. Configure basic settings and timezone preferences
3. Set up OAuth2 integration (optional)
4. Create your first custom role

### OAuth2 Setup
1. Go to **OAuth2 Settings** in the plugin menu
2. Enter your OAuth2 provider credentials
3. Configure role mapping for external users
4. Test the integration

## 📖 Usage

### Creating Custom Roles
1. Go to **Roles** in the plugin menu
2. Click **Add New Role**
3. Enter role name and display name
4. Select capabilities for the role
5. Save the role

### Assigning Temporary Roles
1. Navigate to **User Manager**
2. Select a user
3. Choose **Assign Temporary Role**
4. Set expiration date and time
5. Confirm assignment

### Managing Capabilities
1. Go to **Roles** section
2. Select a role to edit
3. Check/uncheck capabilities
4. Save changes

## 🔒 Security Features

- **SQL Injection Protection**: All database queries use prepared statements
- **Nonce Verification**: All forms and AJAX requests are secured
- **Capability Checks**: Proper permission validation throughout
- **Input Sanitization**: All user input is properly sanitized
- **Audit Logging**: Complete trail of all role-related changes

## 🐛 Troubleshooting

### Common Issues

**Plugin not activating**
- Check PHP version (requires 7.0+)
- Verify WordPress version (requires 6.8+)
- Check for plugin conflicts

**OAuth2 not working**
- Verify OAuth2 provider credentials
- Check redirect URI configuration
- Ensure proper role mapping

**Roles not appearing**
- Clear WordPress cache
- Check database permissions
- Verify plugin activation

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup
1. Clone the repository
2. Install dependencies (if any)
3. Set up a local WordPress development environment
4. Activate the plugin in development mode

## 📝 Changelog

### 1.0.1
- wordpress 7.0 compatibility

### 1.0
- Initial release
- Custom role creation and management
- Temporary role assignments with expiration
- OAuth2 login integration
- Comprehensive audit logging
- Role-based user filtering
- Multi-role support

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Plugin Documentation](https://www.smackcoders.com/documentation/user-role-management/getting-started)
- **Support**: [Contact Support](https://www.smackcoders.com/contact-us.html)
- **Issues**: [GitHub Issues](https://github.com/smackcoders/advanced-user-role-manager/issues)

## 🙏 Acknowledgments

- WordPress community for the excellent platform
- Contributors and beta testers
- Open source projects that made this possible
- Human developers and designers who crafted this solution

---

**Designed and developed at [Smackcoders](https://www.smackcoders.com/)**
