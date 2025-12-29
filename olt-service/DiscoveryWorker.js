const cron = require('node-cron');
const { Pool } = require('pg');
const axios = require('axios');

class DiscoveryWorker {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.pool = new Pool({
            connectionString: process.env.DATABASE_URL
        });
        this.isRunning = false;
        this.phpApiUrl = process.env.PHP_API_URL || 'http://localhost:5000';
        this.cronJob = null;
    }

    start(schedule = '*/30 * * * * *') {
        console.log(`[Discovery] Starting scheduled discovery worker (${schedule})`);
        
        if (schedule.split(' ').length === 6) {
            this.cronJob = cron.schedule(schedule, () => {
                this.runDiscovery();
            }, { scheduled: true });
        } else {
            this.cronJob = cron.schedule(schedule, () => {
                this.runDiscovery();
            });
        }
        
        setTimeout(() => this.runDiscovery(), 5000);
    }

    stop() {
        if (this.cronJob) {
            this.cronJob.stop();
            console.log('[Discovery] Stopped discovery worker');
        }
    }

    async runDiscovery() {
        if (this.isRunning) {
            console.log('[Discovery] Already running, skipping...');
            return;
        }

        this.isRunning = true;
        console.log('[Discovery] Starting discovery cycle...');

        try {
            const olts = await this.getActiveOlts();
            console.log(`[Discovery] Found ${olts.length} active OLTs`);

            for (const olt of olts) {
                try {
                    await this.discoverOlt(olt);
                } catch (error) {
                    console.error(`[Discovery] Error discovering OLT ${olt.name}:`, error.message);
                }
            }

            await this.sendPendingNotifications();
        } catch (error) {
            console.error('[Discovery] Error in discovery cycle:', error.message);
        } finally {
            this.isRunning = false;
            console.log('[Discovery] Discovery cycle complete');
        }
    }

    async getActiveOlts() {
        const result = await this.pool.query(`
            SELECT o.*, b.name as branch_name, b.code as branch_code, b.whatsapp_group
            FROM huawei_olts o
            LEFT JOIN branches b ON o.branch_id = b.id
            WHERE o.is_active = true
        `);
        return result.rows;
    }

    async discoverOlt(olt) {
        console.log(`[Discovery] Checking OLT: ${olt.name} (${olt.ip_address})`);

        const sessionStatus = this.sessionManager.getSessionStatus(olt.id.toString());
        if (!sessionStatus.connected) {
            console.log(`[Discovery] Connecting to ${olt.name}...`);
            const password = this.decryptPassword(olt.password_encrypted);
            await this.sessionManager.connect(olt.id.toString(), {
                host: olt.ip_address,
                port: olt.port || 23,
                username: olt.username,
                password: password
            });
        }

        const command = 'display ont autofind all';
        const output = await this.sessionManager.execute(olt.id.toString(), command, { timeout: 60000 });

        const unconfiguredOnus = this.parseAutofindOutput(output);
        console.log(`[Discovery] Found ${unconfiguredOnus.length} unconfigured ONUs on ${olt.name}`);

        for (const onu of unconfiguredOnus) {
            await this.recordDiscovery(olt, onu);
        }
    }

    decryptPassword(encrypted) {
        if (!encrypted) return '';
        try {
            const crypto = require('crypto');
            const key = process.env.SESSION_SECRET || 'default-secret-key-change-me';
            
            // PHP format: base64(16-byte-raw-iv + base64-ciphertext)
            // First decode the outer base64
            const combined = Buffer.from(encrypted, 'base64');
            
            // Extract 16-byte IV and the rest is base64-encoded ciphertext
            const iv = combined.slice(0, 16);
            const ciphertextBase64 = combined.slice(16).toString('utf8');
            
            // Decode the base64 ciphertext
            const ciphertext = Buffer.from(ciphertextBase64, 'base64');
            
            // PHP openssl_encrypt uses the key directly (not hashed), padded/truncated to 32 bytes
            // For AES-256-CBC, key must be exactly 32 bytes
            const keyBuffer = Buffer.alloc(32);
            Buffer.from(key).copy(keyBuffer);
            
            const decipher = crypto.createDecipheriv('aes-256-cbc', keyBuffer, iv);
            let decrypted = decipher.update(ciphertext);
            decrypted = Buffer.concat([decrypted, decipher.final()]);
            const password = decrypted.toString('utf8');
            console.log(`[Discovery] Decrypted password: ${password.substring(0, 2)}***`);
            return password;
        } catch (e) {
            console.error(`[Discovery] Password decryption failed:`, e.message);
            // Fallback: try as plain text
            return encrypted;
        }
    }

    parseAutofindOutput(output) {
        const onus = [];
        const lines = output.split('\n');
        let currentOnu = null;

        // Debug: log raw output length
        console.log(`[Discovery] Parsing autofind output (${output.length} chars)`);

        for (const line of lines) {
            // Huawei format: "Number : 1" starts a new ONU record
            const numMatch = line.match(/Number\s*:\s*(\d+)/i);
            if (numMatch) {
                if (currentOnu && currentOnu.sn) onus.push(currentOnu);
                currentOnu = { 
                    index: numMatch[1]
                };
                continue;
            }

            // Alternative format: table row like "1   0/0/5   48575443-12345678"
            const tableMatch = line.match(/^\s*(\d+)\s+(\d+\/\s*\d+\/\s*\d+)\s+(\S+)/);
            if (tableMatch) {
                if (currentOnu && currentOnu.sn) onus.push(currentOnu);
                currentOnu = { 
                    index: tableMatch[1],
                    fsp: tableMatch[2].replace(/\s/g, ''),
                    sn: tableMatch[3]
                };
                continue;
            }

            if (currentOnu) {
                // F/S/P format: "F/S/P  : 0/0/5"
                const fspMatch = line.match(/F\/S\/P\s*:\s*(\d+\/\d+\/\d+)/i);
                if (fspMatch) {
                    currentOnu.fsp = fspMatch[1];
                }

                // Serial Number: "Ont SN : 48575443-12345678" or just "SN : xxx"
                const snMatch = line.match(/(?:Ont\s+)?SN\s*:\s*(\S+)/i);
                if (snMatch) {
                    currentOnu.sn = snMatch[1];
                }

                // Equipment ID: "Ont EquipmentID : HG8546M"
                const eqidMatch = line.match(/EquipmentID\s*:\s*(\S+)/i);
                if (eqidMatch) {
                    currentOnu.eqid = eqidMatch[1];
                }
                
                // Software Version: "Ont SoftwareVersion : V5R019C10S125"
                const softwareMatch = line.match(/SoftwareVersion\s*:\s*(\S+)/i);
                if (softwareMatch) {
                    currentOnu.softwareVer = softwareMatch[1];
                }
                
                // Version: "Ont Version : 10C7.A"
                const versionMatch = line.match(/(?:Ont\s+)?Version\s*:\s*(\S+)/i);
                if (versionMatch && !currentOnu.version) {
                    currentOnu.version = versionMatch[1];
                }

                // VendorID: "VendorID : HWTC"
                const vendorMatch = line.match(/VendorID\s*:\s*(\S+)/i);
                if (vendorMatch) {
                    currentOnu.vendorId = vendorMatch[1];
                }
            }
        }
        
        // Don't forget the last ONU
        if (currentOnu && currentOnu.sn) onus.push(currentOnu);

        console.log(`[Discovery] Parsed ${onus.length} ONUs from autofind output`);
        if (onus.length > 0) {
            console.log(`[Discovery] First ONU: ${JSON.stringify(onus[0])}`);
        }

        return onus;
    }

    async matchOnuType(eqid) {
        if (!eqid) return null;
        
        const normalizedEqid = eqid.toUpperCase().replace(/[^A-Z0-9]/g, '');
        
        const result = await this.pool.query(`
            SELECT id, model, model_aliases 
            FROM huawei_onu_types 
            WHERE is_active = true
        `);
        
        for (const type of result.rows) {
            const modelNorm = (type.model || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (normalizedEqid.includes(modelNorm) && modelNorm.length >= 5) {
                return type.id;
            }
            
            if (type.model_aliases) {
                const aliases = type.model_aliases.split(',').map(a => a.trim().toUpperCase().replace(/[^A-Z0-9]/g, ''));
                for (const alias of aliases) {
                    if (alias.length >= 5 && normalizedEqid.includes(alias)) {
                        return type.id;
                    }
                }
            }
        }
        
        return null;
    }

    async recordDiscovery(olt, onu) {
        try {
            // Check if ONU is already authorized in huawei_onus
            const existingOnu = await this.pool.query(`
                SELECT id, is_authorized FROM huawei_onus 
                WHERE sn = $1 AND olt_id = $2
            `, [onu.sn, olt.id]);
            
            const isAlreadyAuthorized = existingOnu.rows.length > 0 && existingOnu.rows[0].is_authorized;
            
            if (isAlreadyAuthorized) {
                // ONU is already authorized - update discovery log to mark as authorized
                await this.pool.query(`
                    UPDATE onu_discovery_log 
                    SET authorized = true, authorized_at = COALESCE(authorized_at, CURRENT_TIMESTAMP)
                    WHERE serial_number = $1 AND olt_id = $2
                `, [onu.sn, olt.id]);
                return; // Don't add to discovery list
            }
            
            const onuTypeId = await this.matchOnuType(onu.eqid);
            
            if (onuTypeId) {
                console.log(`[Discovery] Matched ${onu.sn} (${onu.eqid}) to ONU type ID ${onuTypeId}`);
            } else if (onu.eqid) {
                console.log(`[Discovery] No match for ${onu.sn} (${onu.eqid}) - add ONU type first`);
            }
            
            await this.pool.query(`
                INSERT INTO onu_discovery_log (olt_id, serial_number, frame_slot_port, equipment_id, onu_type_id, last_seen_at)
                VALUES ($1, $2, $3, $4, $5, CURRENT_TIMESTAMP)
                ON CONFLICT (olt_id, serial_number) 
                DO UPDATE SET 
                    last_seen_at = CURRENT_TIMESTAMP, 
                    frame_slot_port = $3,
                    equipment_id = COALESCE($4, onu_discovery_log.equipment_id),
                    onu_type_id = COALESCE($5, onu_discovery_log.onu_type_id)
            `, [olt.id, onu.sn, onu.fsp, onu.eqid || null, onuTypeId]);
        } catch (error) {
            console.error(`[Discovery] Error recording discovery:`, error.message);
        }
    }

    async getProvisioningGroup() {
        try {
            const result = await this.pool.query(`
                SELECT setting_value FROM settings WHERE setting_key = 'wa_provisioning_group'
            `);
            return result.rows[0]?.setting_value || null;
        } catch (error) {
            console.error('[Discovery] Error getting provisioning group:', error.message);
            return null;
        }
    }

    async sendPendingNotifications() {
        const result = await this.pool.query(`
            SELECT d.*, o.name as olt_name, o.ip_address as olt_ip, 
                   b.name as branch_name, b.code as branch_code, b.whatsapp_group
            FROM onu_discovery_log d
            JOIN huawei_olts o ON d.olt_id = o.id
            LEFT JOIN branches b ON o.branch_id = b.id
            WHERE d.notified = false AND d.authorized = false
        `);

        if (result.rows.length === 0) {
            console.log('[Discovery] No pending notifications');
            return;
        }

        console.log(`[Discovery] Sending ${result.rows.length} new ONU notifications...`);

        const provisioningGroup = await this.getProvisioningGroup();
        
        if (!provisioningGroup) {
            console.log('[Discovery] No provisioning group configured (set wa_provisioning_group in settings)');
            return;
        }

        const discoveries = result.rows.map(d => ({
            id: d.id,
            olt_name: d.olt_name,
            olt_ip: d.olt_ip,
            branch_name: d.branch_name || 'Unassigned',
            branch_code: d.branch_code || '',
            serial_number: d.serial_number,
            frame_slot_port: d.frame_slot_port,
            equipment_id: d.equipment_id,
            first_seen_at: d.first_seen_at
        }));

        try {
            const response = await axios.post(`${this.phpApiUrl}/api/oms-notify.php`, {
                type: 'new_onu_discovery',
                group_id: provisioningGroup,
                discoveries: discoveries
            });

            if (response.data && response.data.success) {
                for (const d of result.rows) {
                    await this.markNotified(d.id, true);
                }
                console.log(`[Discovery] Notified provisioning group about ${discoveries.length} new ONUs`);
            } else {
                console.error(`[Discovery] PHP API returned failure:`, response.data?.error || 'Unknown error');
            }
        } catch (error) {
            console.error(`[Discovery] Failed to notify provisioning group (will retry next cycle):`, error.message);
        }
    }

    async markNotified(discoveryId, notified) {
        await this.pool.query(`
            UPDATE onu_discovery_log 
            SET notified = $1, notified_at = CURRENT_TIMESTAMP 
            WHERE id = $2
        `, [notified, discoveryId]);
    }
}

module.exports = DiscoveryWorker;
