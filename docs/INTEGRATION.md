# Integration Guide for ispconfig_ldap_auth_server

This document describes how to integrate the ISPConfig LDAP Auth Addon with an external LDAP authentication server.

## Overview

The ISPConfig LDAP Auth Addon adds configuration fields to ISPConfig that indicate which domains and users are allowed to authenticate via LDAP. The actual LDAP authentication is handled by a separate server component (e.g., `ispconfig_ldap_auth_server`).

## Database Schema

The plugin adds the following columns to the ISPConfig database:

### `mail_domain` table
```sql
ldap_enabled ENUM('n','y') NOT NULL DEFAULT 'n'
```

### `mail_user` table
```sql
ldap_enabled ENUM('n','y') NOT NULL DEFAULT 'y'
```

**Note:** As of version 1.0.1, new mailboxes have LDAP authentication enabled by default. This provides a better user experience where admins only need to enable LDAP at the domain level to allow all users to authenticate.

## Authentication Logic

For a user to be allowed to authenticate via LDAP, **BOTH** of the following must be true:

1. The user's domain has `ldap_enabled = 'y'`
2. The user's mailbox has `ldap_enabled = 'y'`

Additionally, standard ISPConfig checks apply:
- Domain must be `active = 'y'`
- User must have `postfix = 'y'` (mail account enabled)

## SQL Queries

### Check if Domain is LDAP-enabled

```sql
SELECT ldap_enabled
FROM mail_domain
WHERE domain = ?
  AND active = 'y'
  AND ldap_enabled = 'y';
```

**Parameters:**
- `?` = domain name (e.g., `example.com`)

**Returns:**
- `ldap_enabled = 'y'` if domain allows LDAP
- Empty result set if domain not found or LDAP not enabled

### Check if User is LDAP-enabled

```sql
SELECT
    mu.mailuser_id,
    mu.email,
    mu.login,
    mu.password,
    mu.ldap_enabled AS user_ldap_enabled,
    md.domain,
    md.ldap_enabled AS domain_ldap_enabled
FROM mail_user mu
JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
WHERE mu.email = ?
  AND mu.postfix = 'y'
  AND md.active = 'y'
  AND md.ldap_enabled = 'y'
  AND mu.ldap_enabled = 'y';
```

**Parameters:**
- `?` = email address (e.g., `user@example.com`)

**Returns:**
- User record if LDAP authentication is allowed
- Empty result set if user not found or LDAP not enabled

**Fields returned:**
- `mailuser_id` - ISPConfig user ID
- `email` - User's email address
- `login` - User's login name (may differ from email)
- `password` - Hashed password (for password verification)
- `user_ldap_enabled` - User's LDAP flag ('y' or 'n')
- `domain` - Domain name
- `domain_ldap_enabled` - Domain's LDAP flag ('y' or 'n')

### Get All LDAP-enabled Domains

```sql
SELECT
    domain_id,
    domain,
    server_id,
    active,
    ldap_enabled
FROM mail_domain
WHERE active = 'y'
  AND ldap_enabled = 'y'
ORDER BY domain;
```

**Use case:** Syncing domain list to LDAP server

### Get All LDAP-enabled Users for a Domain

```sql
SELECT
    mu.mailuser_id,
    mu.email,
    mu.login,
    mu.name,
    mu.quota,
    mu.ldap_enabled
FROM mail_user mu
JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
WHERE md.domain = ?
  AND md.active = 'y'
  AND md.ldap_enabled = 'y'
  AND mu.postfix = 'y'
  AND mu.ldap_enabled = 'y'
ORDER BY mu.email;
```

**Parameters:**
- `?` = domain name (e.g., `example.com`)

**Use case:** Syncing user list to LDAP server for a specific domain

### Get All LDAP-enabled Users (All Domains)

```sql
SELECT
    mu.mailuser_id,
    mu.email,
    mu.login,
    mu.name,
    mu.quota,
    mu.ldap_enabled,
    md.domain
FROM mail_user mu
JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
WHERE md.active = 'y'
  AND md.ldap_enabled = 'y'
  AND mu.postfix = 'y'
  AND mu.ldap_enabled = 'y'
ORDER BY md.domain, mu.email;
```

**Use case:** Full sync of all LDAP users across all domains

## Database Connection

### Connection Parameters

The external LDAP server should connect to the ISPConfig database using:

- **Host:** Usually `localhost` (same server as ISPConfig)
- **Database:** `dbispconfig` (default ISPConfig database name)
- **User:** `ispconfig` (default ISPConfig database user)
- **Password:** Found in `/usr/local/ispconfig/server/lib/config.inc.php`

**Example from config.inc.php:**
```php
$conf['db_database'] = 'dbispconfig';
$conf['db_user'] = 'ispconfig';
$conf['db_password'] = 'your_password_here';
```

### Read-Only Access Recommended

The LDAP authentication server should only need **READ** access to the database. It's recommended to create a separate read-only user:

```sql
-- Create read-only user for LDAP server
CREATE USER 'ispconfig_ldap_ro'@'localhost' IDENTIFIED BY 'secure_password';

-- Grant SELECT only on required tables
GRANT SELECT ON dbispconfig.mail_domain TO 'ispconfig_ldap_ro'@'localhost';
GRANT SELECT ON dbispconfig.mail_user TO 'ispconfig_ldap_ro'@'localhost';

FLUSH PRIVILEGES;
```

Then use `ispconfig_ldap_ro` user in your LDAP server configuration.

## Authentication Flow Example

```
1. User attempts LDAP authentication with: user@example.com + password

2. LDAP server queries ISPConfig database:

   SELECT mu.email, mu.password, mu.ldap_enabled, md.ldap_enabled
   FROM mail_user mu
   JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
   WHERE mu.email = 'user@example.com'
     AND mu.postfix = 'y'
     AND md.active = 'y'
     AND md.ldap_enabled = 'y'
     AND mu.ldap_enabled = 'y';

3. If query returns result:
   - User is allowed for LDAP auth
   - Verify password hash matches
   - Return authentication success

4. If query returns empty:
   - User not allowed for LDAP auth
   - Return authentication failure
```

## Password Verification

ISPConfig stores passwords hashed using various methods. The hash format is stored in the password field.

**Common hash formats:**
- `$1$...` - MD5 crypt
- `$5$...` - SHA-256 crypt
- `$6$...` - SHA-512 crypt (most common)

**Verification:**
```php
// PHP example
$stored_hash = $row['password'];
$input_password = $_POST['password'];

if(crypt($input_password, $stored_hash) === $stored_hash) {
    // Password correct
} else {
    // Password incorrect
}
```

**Python example:**
```python
import crypt

stored_hash = row['password']
input_password = user_input

if crypt.crypt(input_password, stored_hash) == stored_hash:
    # Password correct
else:
    # Password incorrect
```

## Security Considerations

1. **Database Access:**
   - Use read-only database user for LDAP server
   - Limit access to required tables only
   - Use strong passwords
   - Consider using Unix socket instead of TCP

2. **Network:**
   - If LDAP server is remote, use encrypted connection (TLS/SSL)
   - Use firewall to restrict database access
   - Consider VPN for database connections

3. **LDAP Server:**
   - Use LDAPS (LDAP over TLS) for client connections
   - Validate and sanitize all inputs
   - Implement rate limiting to prevent brute force
   - Log authentication attempts

4. **ISPConfig Integration:**
   - Don't modify ISPConfig database directly from LDAP server
   - Query caching recommended (but cache should be short-lived)
   - Handle database connection errors gracefully

## Example Integration: Python LDAP Server

Here's a minimal example of how ispconfig_ldap_auth_server might query ISPConfig:

```python
import mysql.connector
import crypt

def check_ldap_auth(email, password):
    """Check if user can authenticate via LDAP"""

    # Connect to ISPConfig database
    conn = mysql.connector.connect(
        host='localhost',
        database='dbispconfig',
        user='ispconfig_ldap_ro',
        password='your_password'
    )

    cursor = conn.cursor(dictionary=True)

    # Query for LDAP-enabled user
    query = """
        SELECT mu.email, mu.password, mu.name
        FROM mail_user mu
        JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
        WHERE mu.email = %s
          AND mu.postfix = 'y'
          AND md.active = 'y'
          AND md.ldap_enabled = 'y'
          AND mu.ldap_enabled = 'y'
    """

    cursor.execute(query, (email,))
    user = cursor.fetchone()

    cursor.close()
    conn.close()

    if not user:
        return False, "User not found or LDAP not enabled"

    # Verify password
    if crypt.crypt(password, user['password']) == user['password']:
        return True, user
    else:
        return False, "Invalid password"
```

## Monitoring and Logging

### Recommended Logging

The LDAP server should log:
- All authentication attempts (success/failure)
- Database connection errors
- Configuration changes
- Rate limiting events

### Metrics to Monitor

- Authentication success rate
- Authentication latency
- Database query performance
- Number of LDAP-enabled domains/users
- Failed authentication attempts per IP

## Testing

### Test Queries

```sql
-- Count LDAP-enabled domains
SELECT COUNT(*) FROM mail_domain
WHERE active = 'y' AND ldap_enabled = 'y';

-- Count LDAP-enabled users
SELECT COUNT(*) FROM mail_user mu
JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
WHERE md.active = 'y'
  AND md.ldap_enabled = 'y'
  AND mu.postfix = 'y'
  AND mu.ldap_enabled = 'y';

-- Test specific user
SELECT mu.email, mu.ldap_enabled, md.domain, md.ldap_enabled
FROM mail_user mu
JOIN mail_domain md ON mu.email LIKE CONCAT('%@', md.domain)
WHERE mu.email = 'test@example.com';
```

## Troubleshooting

### User can't authenticate but LDAP is enabled

1. Check domain is enabled:
   ```sql
   SELECT domain, active, ldap_enabled FROM mail_domain WHERE domain = 'example.com';
   ```

2. Check user is enabled:
   ```sql
   SELECT email, postfix, ldap_enabled FROM mail_user WHERE email = 'user@example.com';
   ```

3. Check password hash is valid
4. Check LDAP server logs for errors

### Database connection issues

1. Verify credentials in `/usr/local/ispconfig/server/lib/config.inc.php`
2. Check MySQL user permissions: `SHOW GRANTS FOR 'ispconfig_ldap_ro'@'localhost';`
3. Test connection: `mysql -u ispconfig_ldap_ro -p dbispconfig`

## Support

For issues with:
- **ISPConfig LDAP Auth Addon:** https://github.com/arvanus/ispconfig3-ldap-auth-addon/issues
- **ispconfig_ldap_auth_server:** https://github.com/arvanus/ispconfig_ldap_auth_server/issues
- **ISPConfig itself:** https://www.howtoforge.com/community/forums/ispconfig-3-support.60/
