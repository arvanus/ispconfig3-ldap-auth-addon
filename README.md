# ISPConfig LDAP Authentication Addon

Adds LDAP authentication capability to ISPConfig 3 mail domains and mailboxes. This plugin allows you to mark which domains and users can authenticate via an external LDAP server.

**Important:** This plugin only adds the control fields to ISPConfig. The actual LDAP authentication is handled by a separate server component: [ispconfig_ldap_auth_server](https://github.com/seu-usuario/ispconfig_ldap_auth_server)

## Features

- ✅ Adds "LDAP Auth" tab to Email Domain configuration
- ✅ Adds "LDAP Auth" tab to Email Mailbox configuration
- ✅ Enable/disable LDAP authentication per domain
- ✅ Enable/disable LDAP authentication per mailbox user
- ✅ Automatic database schema updates (no manual SQL required)
- ✅ Zero modification of ISPConfig core files
- ✅ Easy installation and update
- ✅ Multilingual support (English and Portuguese included)

## Requirements

- ISPConfig 3.2 or higher
- PHP 5.4 or higher
- MySQL/MariaDB with ALTER privilege on ISPConfig database

## Installation

### Step 1: Grant ALTER Privilege (Temporary)

The plugin needs to modify the database schema. By default, ISPConfig's database user only has SELECT, INSERT, UPDATE, and DELETE privileges.

**Before installation**, grant ALTER privilege:

```bash
# Connect to MySQL as root
mysql -u root -p

# Grant ALTER privilege
GRANT ALTER ON dbispconfig.* TO 'ispconfig'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Note:** You can revoke this privilege after installation if desired.

### Step 2: Install the Plugin

```bash
# Clone the repository
cd /tmp
git clone https://github.com/seu-usuario/ispconfig3-ldap-auth-addon.git
cd ispconfig3-ldap-auth-addon

# Copy files to ISPConfig
cp -R interface /usr/local/ispconfig

# Set correct permissions
chown -R ispconfig:ispconfig /usr/local/ispconfig/interface/lib

# Clean up
cd /tmp
rm -rf ispconfig3-ldap-auth-addon
```

### Step 3: Reload ISPConfig Interface

**Important:** You MUST logout and login again to ISPConfig for the plugin to load!

The plugin will automatically:
- Create `ldap_enabled` column in `mail_domain` table
- Create `ldap_enabled` column in `mail_user` table
- Register itself with ISPConfig's plugin system

### Step 4: Verify Installation

1. Login to ISPConfig
2. Go to **Email → Domain → [Select a domain]**
3. You should see a new tab called **"LDAP Auth"**
4. Go to **Email → Email Mailbox → [Select a mailbox]**
5. You should see a new tab called **"LDAP Auth"**

## Usage

### Enable LDAP for a Domain

1. Navigate to **Email → Domain → [Your Domain]**
2. Click the **"LDAP Auth"** tab
3. Check the **"Enable LDAP Authentication"** checkbox
4. Save

### Enable LDAP for a User

1. Navigate to **Email → Email Mailbox → [Your Mailbox]**
2. Click the **"LDAP Auth"** tab
3. Check the **"Enable LDAP Authentication"** checkbox
4. Save

**Note:** For a user to authenticate via LDAP, BOTH the domain AND the user must have LDAP enabled.

## Update

Updating is the same as installation:

```bash
cd /tmp
git clone https://github.com/seu-usuario/ispconfig3-ldap-auth-addon.git
cd ispconfig3-ldap-auth-addon
cp -R interface /usr/local/ispconfig
chown -R ispconfig:ispconfig /usr/local/ispconfig/interface/lib
cd /tmp
rm -rf ispconfig3-ldap-auth-addon
```

Then logout and login to ISPConfig.

## Uninstallation

To remove the plugin:

```bash
rm -rf /usr/local/ispconfig/interface/lib/plugins/ldap_auth_plugin.inc.php
rm -rf /usr/local/ispconfig/interface/lib/plugins/ldap_auth_plugin/
```

**Note:** The database columns (`ldap_enabled`) will remain in the database. To remove them:

```sql
ALTER TABLE mail_domain DROP COLUMN ldap_enabled;
ALTER TABLE mail_user DROP COLUMN ldap_enabled;
```

## Integration with External LDAP Server

This plugin only manages the configuration fields in ISPConfig. To actually authenticate users via LDAP, you need to set up the companion server:

👉 **[ispconfig_ldap_auth_server](https://github.com/seu-usuario/ispconfig_ldap_auth_server)**

See [INTEGRATION.md](docs/INTEGRATION.md) for SQL queries and integration details.

## Database Schema

The plugin adds the following columns:

### `mail_domain` table
```sql
ldap_enabled ENUM('n','y') NOT NULL DEFAULT 'n'
```

### `mail_user` table
```sql
ldap_enabled ENUM('n','y') NOT NULL DEFAULT 'n'
```

## File Structure

```
interface/
└── lib/
    └── plugins/
        ├── ldap_auth_plugin.inc.php        # Main plugin file
        └── ldap_auth_plugin/
            ├── VERSION                      # Version file
            ├── sql/
            │   ├── mail_domain.sql         # Domain table schema
            │   └── mail_user.sql           # User table schema
            ├── templates/
            │   ├── mail_domain_edit.htm    # Domain tab template
            │   └── mail_user_edit.htm      # User tab template
            └── lib/
                └── lang/
                    ├── en.lng              # English translations
                    └── pt.lng              # Portuguese translations
```

## Troubleshooting

### Plugin tab not appearing

1. Make sure you logged out and logged back in after installation
2. Check file permissions: `chown -R ispconfig:ispconfig /usr/local/ispconfig/interface/lib`
3. Check ISPConfig logs: `/var/log/ispconfig/ispconfig.log`

### Database error during installation

Make sure the ISPConfig database user has ALTER privilege:

```sql
SHOW GRANTS FOR 'ispconfig'@'localhost';
```

Should include: `GRANT ... ALTER ... ON dbispconfig.*`

### Changes not saving

1. Check browser console for JavaScript errors
2. Verify database columns exist:
   ```sql
   SHOW COLUMNS FROM mail_domain LIKE 'ldap_enabled';
   SHOW COLUMNS FROM mail_user LIKE 'ldap_enabled';
   ```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Follow ISPConfig coding standards (tabs, camelCase methods, lowercase variables)
4. Test thoroughly
5. Submit a pull request

## License

BSD License - See [LICENSE](LICENSE) file for details.

## Credits

- Inspired by [ISPConfig Nextcloud Plugin](https://github.com/mediabox-cl/ispconfig-nextcloud-plugin)
- Built for integration with [ispconfig_ldap_auth_server](https://github.com/seu-usuario/ispconfig_ldap_auth_server)

## Support

- Issues: https://github.com/seu-usuario/ispconfig3-ldap-auth-addon/issues
- ISPConfig Forum: https://www.howtoforge.com/community/forums/ispconfig-3-development.77/

## Version History

### 1.0.0 (2025-01-20)
- Initial release
- LDAP enable/disable for domains
- LDAP enable/disable for mailboxes
- Automatic database schema updates
- English and Portuguese translations
