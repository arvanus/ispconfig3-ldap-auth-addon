# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-20

### Added
- Initial release
- LDAP enable/disable checkbox for mail domains
- LDAP enable/disable checkbox for mailboxes
- Automatic database schema creation (ALTER TABLE)
- Plugin system integration using `on_after_formdef` event
- New "LDAP Auth" tab in Email Domain configuration
- New "LDAP Auth" tab in Email Mailbox configuration
- English language support
- Portuguese language support
- Comprehensive README with installation instructions
- INTEGRATION.md with SQL queries and integration guide
- BSD License
- Zero modification of ISPConfig core files

### Features
- `mail_domain.ldap_enabled` column (ENUM 'n','y')
- `mail_user.ldap_enabled` column (ENUM 'n','y')
- Automatic column creation on plugin load
- Support for ISPConfig 3.2+
- PHP 5.4+ compatibility

### Documentation
- Installation guide
- Update guide
- Uninstallation guide
- Integration guide for external LDAP servers
- SQL query examples
- Security considerations
- Troubleshooting guide

[1.0.0]: https://github.com/seu-usuario/ispconfig3-ldap-auth-addon/releases/tag/v1.0.0
