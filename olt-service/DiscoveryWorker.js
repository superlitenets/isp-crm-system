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

    start(schedule = '*/5 * * * *') {
        console.log(`[Discovery] Starting scheduled discovery worker (${schedule})`);
        this.cronJob = cron.schedule(schedule, () => {
            this.runDiscovery();
        });
        setTimeout(() => this.runDiscovery(), 10000);
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
        const output = await this.sessionManager.execute(olt.id.toString(), command, { timeout: 30000 });

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
            const keyHash = crypto.createHash('sha256').update(key).digest();
            const parts = encrypted.split(':');
            if (parts.length !== 2) return encrypted;
            const iv = Buffer.from(parts[0], 'hex');
            const encryptedText = Buffer.from(parts[1], 'hex');
            const decipher = crypto.createDecipheriv('aes-256-cbc', keyHash, iv);
            let decrypted = decipher.update(encryptedText);
            decrypted = Buffer.concat([decrypted, decipher.final()]);
            return decrypted.toString();
        } catch (e) {
            return encrypted;
        }
    }

    parseAutofindOutput(output) {
        const onus = [];
        const lines = output.split('\n');
        let currentOnu = null;

        for (const line of lines) {
            const fspMatch = line.match(/^\s*(\d+)\s+(\d+\/\s*\d+\/\s*\d+)/);
            if (fspMatch) {
                if (currentOnu) onus.push(currentOnu);
                currentOnu = { 
                    index: fspMatch[1],
                    fsp: fspMatch[2].replace(/\s/g, '')
                };
            }

            if (currentOnu) {
                const snMatch = line.match(/SN\s*:\s*(\S+)/i);
                if (snMatch) currentOnu.sn = snMatch[1];

                const eqidMatch = line.match(/EQID\s*:\s*(\S+)/i);
                if (eqidMatch) currentOnu.eqid = eqidMatch[1];
            }
        }
        if (currentOnu && currentOnu.sn) onus.push(currentOnu);

        return onus;
    }

    async recordDiscovery(olt, onu) {
        try {
            await this.pool.query(`
                INSERT INTO onu_discovery_log (olt_id, serial_number, frame_slot_port, last_seen_at)
                VALUES ($1, $2, $3, CURRENT_TIMESTAMP)
                ON CONFLICT (olt_id, serial_number) 
                DO UPDATE SET last_seen_at = CURRENT_TIMESTAMP, frame_slot_port = $3
            `, [olt.id, onu.sn, onu.fsp]);
        } catch (error) {
            console.error(`[Discovery] Error recording discovery:`, error.message);
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

        console.log(`[Discovery] Sending ${result.rows.length} notifications...`);

        const grouped = {};
        for (const row of result.rows) {
            const key = row.whatsapp_group || 'default';
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(row);
        }

        for (const [groupId, discoveries] of Object.entries(grouped)) {
            if (groupId === 'default' || !groupId) {
                console.log(`[Discovery] Skipping ${discoveries.length} ONUs - no WhatsApp group configured (will retry when branch is linked)`);
                continue;
            }

            try {
                const response = await axios.post(`${this.phpApiUrl}/api/oms-notify.php`, {
                    type: 'new_onu_discovery',
                    group_id: groupId,
                    discoveries: discoveries.map(d => ({
                        id: d.id,
                        olt_name: d.olt_name,
                        olt_ip: d.olt_ip,
                        branch_name: d.branch_name,
                        branch_code: d.branch_code,
                        serial_number: d.serial_number,
                        frame_slot_port: d.frame_slot_port,
                        first_seen_at: d.first_seen_at
                    }))
                });

                if (response.data && response.data.success) {
                    for (const d of discoveries) {
                        await this.markNotified(d.id, true);
                    }
                    console.log(`[Discovery] Notified group ${groupId} about ${discoveries.length} ONUs`);
                } else {
                    console.error(`[Discovery] PHP API returned failure for group ${groupId}:`, response.data?.error || 'Unknown error');
                }
            } catch (error) {
                console.error(`[Discovery] Failed to notify group ${groupId} (will retry next cycle):`, error.message);
            }
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
