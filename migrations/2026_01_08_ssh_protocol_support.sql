-- SSH Protocol Support for Huawei OLT
-- Adds SSH as an alternative to Telnet to fix space-stripping issues in CLI commands
-- Date: 2026-01-08

ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS cli_protocol VARCHAR(20) DEFAULT 'ssh';
ALTER TABLE huawei_olts ADD COLUMN IF NOT EXISTS ssh_port INTEGER DEFAULT 22;

COMMENT ON COLUMN huawei_olts.cli_protocol IS 'CLI protocol: telnet or ssh. SSH recommended to avoid space-stripping issues.';
COMMENT ON COLUMN huawei_olts.ssh_port IS 'SSH port (default 22) when using SSH protocol';
