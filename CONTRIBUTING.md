# Contributing to Advanced User Role Manager

Thank you for your interest in contributing to Advanced User Role Manager! This document provides guidelines and information for contributors. This plugin is designed and developed by human developers, and we welcome contributions from the community.

## 🚀 Getting Started

### Prerequisites
- WordPress 6.8 or higher
- PHP 7.0 or higher
- Git
- Basic knowledge of WordPress plugin development

### Development Environment Setup
1. Clone the repository:
   ```bash
   git clone https://github.com/smackcoders/advanced-user-role-manager.git
   cd advanced-user-role-manager
   ```

2. Set up a local WordPress development environment
3. Activate the plugin in your WordPress installation
4. Make sure you have debugging enabled for development

## 📝 Code Style Guidelines

### PHP Code Standards
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use meaningful variable names with the `advausro_` prefix for plugin-specific variables
- Include proper PHPDoc comments for all functions and classes
- Use proper indentation (4 spaces, no tabs)

### JavaScript Code Standards
- Follow WordPress JavaScript coding standards
- Use meaningful variable and function names
- Include proper JSDoc comments
- Use jQuery for DOM manipulation (WordPress standard)

### CSS Code Standards
- Follow WordPress CSS coding standards
- Use meaningful class names with the `crm-` prefix
- Organize CSS logically with comments
- Use responsive design principles

## 🔧 Development Workflow

### 1. Create a Feature Branch
```bash
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes
- Write clean, well-documented code
- Include proper error handling
- Add security measures (nonces, capability checks, sanitization)
- Test thoroughly

### 3. Test Your Changes
- Test on different WordPress versions
- Test with different PHP versions
- Test with popular plugins (WooCommerce, etc.)
- Test security features

### 4. Commit Your Changes
```bash
git add .
git commit -m "feat: add new feature description"
```

### 5. Push and Create Pull Request
```bash
git push origin feature/your-feature-name
```

## 🐛 Bug Reports

### Before Submitting a Bug Report
1. Check if the issue has already been reported
2. Test with a default WordPress theme
3. Disable other plugins to check for conflicts
4. Check the WordPress debug log

### Bug Report Template
```
**Description**
Brief description of the issue

**Steps to Reproduce**
1. Step 1
2. Step 2
3. Step 3

**Expected Behavior**
What should happen

**Actual Behavior**
What actually happens

**Environment**
- WordPress Version: X.X.X
- PHP Version: X.X.X
- Plugin Version: X.X.X
- Theme: [Theme Name]
- Other Plugins: [List if relevant]

**Additional Information**
Screenshots, error logs, etc.
```

## 💡 Feature Requests

### Before Submitting a Feature Request
1. Check if the feature has already been requested
2. Consider if it aligns with the plugin's purpose
3. Think about implementation complexity

### Feature Request Template
```
**Feature Description**
Brief description of the requested feature

**Use Case**
Why this feature would be useful

**Proposed Implementation**
How you think it could be implemented (optional)

**Additional Information**
Any other relevant details
```

## 🔒 Security

### Security Guidelines
- Always validate and sanitize user input
- Use WordPress nonces for form submissions
- Check user capabilities before performing actions
- Use prepared statements for database queries
- Never trust user input

### Reporting Security Issues
If you discover a security vulnerability, please:
1. **DO NOT** create a public issue
2. Email security@smackcoders.com
3. Include detailed information about the vulnerability
4. Allow time for the issue to be addressed

## 📚 Documentation

### Code Documentation
- Include PHPDoc comments for all functions
- Document parameters, return values, and exceptions
- Use clear, concise descriptions

### User Documentation
- Update readme.txt for user-facing changes
- Include screenshots for UI changes
- Provide clear installation and usage instructions

## 🧪 Testing

### Testing Requirements
- Test on WordPress 6.8+
- Test with PHP 7.0+
- Test with popular themes
- Test with WooCommerce and other popular plugins
- Test security features thoroughly

### Testing Checklist
- [ ] Plugin activates without errors
- [ ] All features work as expected
- [ ] No PHP errors or warnings
- [ ] No JavaScript errors
- [ ] Security features work properly
- [ ] Database operations work correctly
- [ ] UI is responsive and accessible

## 📦 Release Process

### Before Release
1. Update version numbers in all files
2. Update changelog
3. Test thoroughly
4. Update documentation
5. Create release notes

### Version Numbering
- Follow semantic versioning (MAJOR.MINOR.PATCH)
- Update version in:
  - `advanced-user-role-manager.php`
  - `readme.txt`
  - `README.md`

## 🤝 Code Review Process

### Pull Request Guidelines
1. Provide a clear description of changes
2. Include screenshots for UI changes
3. Reference related issues
4. Ensure all tests pass
5. Follow coding standards

### Review Checklist
- [ ] Code follows WordPress standards
- [ ] Security measures are in place
- [ ] Error handling is adequate
- [ ] Documentation is updated
- [ ] Tests are included (if applicable)

## 📞 Getting Help

### Resources
- [WordPress Developer Documentation](https://developer.wordpress.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Development Handbook](https://developer.wordpress.org/plugins/)

### Contact
- **Issues**: [GitHub Issues](https://github.com/smackcoders/advanced-user-role-manager/issues)
- **Support**: [Contact Support](https://www.smackcoders.com/contact-us.html)
- **Documentation**: [Plugin Documentation](https://www.smackcoders.com/documentation/user-role-management/getting-started)

## 🙏 Acknowledgments

Thank you for contributing to Advanced User Role Manager! Your contributions help make the plugin better for everyone. This project is built by humans, for humans, with a focus on real-world usability and security.

---

**Happy Coding!**
