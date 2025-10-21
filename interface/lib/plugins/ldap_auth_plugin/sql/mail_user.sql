ALTER TABLE mail_user
ADD COLUMN IF NOT EXISTS ldap_enabled ENUM('n','y') NOT NULL DEFAULT 'n';
