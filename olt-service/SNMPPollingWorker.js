const cron = require('node-cron');
const { Pool } = require('pg');
const snmp = require('net-snmp');
const axios = require('axios');

class SNMPPollingWorker {
    constructor() {
        this.pool = new Pool({
            connectionString: process.env.DATABASE_URL
        });
        this.isRunning = false;
        this.cronJob = null;
        this.pollInterval = parseInt(process.env.SNMP_POLL_INTERVAL) || 30;
        this.phpApiUrl = process.env.PHP_API_URL || 'http://localhost:5000';

        this.OIDs = {
            sysUpTime: '1.3.6.1.2.1.1.3.0',
            sysDescr: '1.3.6.1.2.1.1.1.0',
            sysName: '1.3.6.1.2.1.1.5.0',
            sysLocation: '1.3.6.1.2.1.1.6.0',
            onuStatusBase: '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15',
            onuSerialBase: '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',
            onuRxPowerBase: '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4',
            onuTxPowerBase: '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.6'
        };

        this.STATUS_MAP = {
            1: 'online',
            2: 'offline', 
            3: 'los',
            4: 'dying-gasp',
            5: 'auth-fail',
            6: 'offline'
        };
    }

    start(intervalSeconds = 30) {
        console.log(`[SNMP] Starting polling worker (every ${intervalSeconds}s)`);
        const cronSchedule = `*/${Math.max(1, Math.floor(intervalSeconds / 60)) || 1} * * * *`;
        
        if (intervalSeconds < 60) {
            this.intervalId = setInterval(() => this.runPolling(), intervalSeconds * 1000);
        } else {
            this.cronJob = cron.schedule(cronSchedule, () => this.runPolling());
        }
        
        setTimeout(() => this.runPolling(), 5000);
    }

    stop() {
        if (this.cronJob) {
            this.cronJob.stop();
            console.log('[SNMP] Stopped cron job');
        }
        if (this.intervalId) {
            clearInterval(this.intervalId);
            console.log('[SNMP] Stopped interval polling');
        }
    }

    async runPolling() {
        if (this.isRunning) {
            console.log('[SNMP] Already running, skipping...');
            return;
        }

        this.isRunning = true;
        const startTime = Date.now();

        try {
            const olts = await this.getActiveOlts();
            console.log(`[SNMP] Polling ${olts.length} active OLTs...`);

            const results = await Promise.allSettled(
                olts.map(olt => this.pollOlt(olt))
            );

            let success = 0, failed = 0;
            results.forEach((r, i) => {
                if (r.status === 'fulfilled') success++;
                else {
                    failed++;
                    console.error(`[SNMP] OLT ${olts[i].name} failed:`, r.reason?.message || r.reason);
                }
            });

            console.log(`[SNMP] Completed in ${Date.now() - startTime}ms - Success: ${success}, Failed: ${failed}`);
        } catch (error) {
            console.error('[SNMP] Polling cycle error:', error.message);
        } finally {
            this.isRunning = false;
        }
    }

    async getActiveOlts() {
        const result = await this.pool.query(`
            SELECT id, name, ip_address, 
                   COALESCE(snmp_read_community, 'public') as snmp_community,
                   COALESCE(snmp_version, 'v2c') as snmp_version,
                   COALESCE(snmp_port, 161) as snmp_port
            FROM huawei_olts 
            WHERE is_active = true
        `);
        return result.rows;
    }

    async pollOlt(olt) {
        return new Promise(async (resolve, reject) => {
            const session = snmp.createSession(olt.ip_address, olt.snmp_community, {
                port: olt.snmp_port,
                version: olt.snmp_version === 'v1' ? snmp.Version1 : snmp.Version2c,
                timeout: 5000,
                retries: 1
            });

            try {
                const sysInfo = await this.getSysInfo(session);
                const onuStatuses = await this.getONUStatuses(session, olt.id);
                
                await this.updateOltInfo(olt.id, sysInfo);
                await this.updateONUStatuses(olt.id, onuStatuses);
                await this.updatePollingStats(olt.id, true);
                
                session.close();
                resolve({ olt: olt.name, ...sysInfo, onuCount: onuStatuses.length });
            } catch (error) {
                session.close();
                await this.updatePollingStats(olt.id, false, error.message);
                reject(error);
            }
        });
    }

    getSysInfo(session) {
        return new Promise((resolve, reject) => {
            const oids = [
                this.OIDs.sysUpTime,
                this.OIDs.sysDescr,
                this.OIDs.sysName,
                this.OIDs.sysLocation
            ];

            session.get(oids, (error, varbinds) => {
                if (error) {
                    reject(error);
                    return;
                }

                const info = {};
                varbinds.forEach(vb => {
                    if (snmp.isVarbindError(vb)) return;
                    
                    if (vb.oid === this.OIDs.sysUpTime) {
                        const ticks = vb.value;
                        const seconds = Math.floor(ticks / 100);
                        const days = Math.floor(seconds / 86400);
                        const hours = Math.floor((seconds % 86400) / 3600);
                        const mins = Math.floor((seconds % 3600) / 60);
                        info.uptime = `${days}d ${hours}h ${mins}m`;
                        info.uptimeTicks = ticks;
                    }
                    if (vb.oid === this.OIDs.sysDescr) info.description = vb.value.toString();
                    if (vb.oid === this.OIDs.sysName) info.name = vb.value.toString();
                    if (vb.oid === this.OIDs.sysLocation) info.location = vb.value.toString();
                });

                resolve(info);
            });
        });
    }

    getONUStatuses(session, oltId) {
        return new Promise((resolve, reject) => {
            const statuses = [];
            const maxOid = this.OIDs.onuStatusBase + '.4294967295';

            session.subtree(this.OIDs.onuStatusBase, maxOid, (varbinds) => {
                varbinds.forEach(vb => {
                    if (snmp.isVarbindError(vb)) return;
                    
                    const oidParts = vb.oid.split('.');
                    const onuIndex = oidParts.slice(-2).join('.');
                    const statusCode = parseInt(vb.value);
                    const status = this.STATUS_MAP[statusCode] || 'unknown';
                    
                    statuses.push({
                        index: onuIndex,
                        statusCode,
                        status
                    });
                });
            }, (error) => {
                if (error && error.message !== 'No more OIDs') {
                    console.log(`[SNMP] ONU status walk completed for OLT ${oltId}: ${statuses.length} ONUs`);
                }
                resolve(statuses);
            });
        });
    }

    async updateOltInfo(oltId, sysInfo) {
        try {
            await this.pool.query(`
                UPDATE huawei_olts SET
                    snmp_last_poll = CURRENT_TIMESTAMP,
                    snmp_status = 'online',
                    snmp_sys_uptime = $1,
                    snmp_sys_descr = $2,
                    snmp_sys_name = $3,
                    snmp_sys_location = $4
                WHERE id = $5
            `, [
                sysInfo.uptime || null,
                sysInfo.description || null,
                sysInfo.name || null,
                sysInfo.location || null,
                oltId
            ]);
        } catch (error) {
            console.error(`[SNMP] Failed to update OLT ${oltId} info:`, error.message);
        }
    }

    async updateONUStatuses(oltId, statuses) {
        if (statuses.length === 0) return;

        const client = await this.pool.connect();
        const faults = [];
        try {
            await client.query('BEGIN');
            
            let updated = 0;
            for (const s of statuses) {
                const parts = s.index.split('.');
                if (parts.length < 2) continue;
                
                const slotPortId = parseInt(parts[0]);
                const onuId = parseInt(parts[1]);
                
                const slot = Math.floor((slotPortId - 4294901760) / 256);
                const port = (slotPortId - 4294901760) % 256;
                
                // Check previous status to detect faults
                const prevResult = await client.query(`
                    SELECT o.id, o.sn, o.status as prev_status, o.description, o.name,
                           c.name as customer_name, c.phone as customer_phone, c.id as customer_id
                    FROM huawei_onus o
                    LEFT JOIN customers c ON o.customer_id = c.id
                    WHERE o.olt_id = $1 AND o.slot = $2 AND o.port = $3 AND o.onu_id = $4
                `, [oltId, slot, port, onuId]);
                
                const prevOnu = prevResult.rows[0];
                
                const result = await client.query(`
                    UPDATE huawei_onus 
                    SET status = $1, updated_at = CURRENT_TIMESTAMP
                    WHERE olt_id = $2 AND slot = $3 AND port = $4 AND onu_id = $5
                `, [s.status, oltId, slot, port, onuId]);
                
                if (result.rowCount > 0) {
                    updated++;
                    // Detect fault: was online, now offline/los/dying-gasp
                    if (prevOnu && prevOnu.prev_status === 'online' && 
                        ['offline', 'los', 'dying-gasp'].includes(s.status)) {
                        faults.push({
                            onu_id: prevOnu.id,
                            sn: prevOnu.sn,
                            name: prevOnu.name || prevOnu.description || prevOnu.sn,
                            slot, port, onu_id: onuId,
                            prev_status: prevOnu.prev_status,
                            new_status: s.status,
                            customer_name: prevOnu.customer_name,
                            customer_phone: prevOnu.customer_phone,
                            customer_id: prevOnu.customer_id
                        });
                    }
                }
            }

            await client.query('COMMIT');
            if (updated > 0) {
                console.log(`[SNMP] Updated ${updated} ONU statuses for OLT ${oltId}`);
            }
            
            // Send fault notifications
            if (faults.length > 0) {
                await this.sendFaultNotifications(oltId, faults);
            }
        } catch (error) {
            await client.query('ROLLBACK');
            console.error(`[SNMP] Failed to update ONU statuses for OLT ${oltId}:`, error.message);
        } finally {
            client.release();
        }
    }
    
    async sendFaultNotifications(oltId, faults) {
        try {
            const provisioningGroup = await this.getProvisioningGroup();
            if (!provisioningGroup) {
                console.log(`[SNMP] No provisioning group configured, skipping fault notifications`);
                return;
            }
            
            // Get OLT info
            const oltResult = await this.pool.query(`
                SELECT o.name, o.ip_address, b.name as branch_name, b.code as branch_code
                FROM huawei_olts o
                LEFT JOIN branches b ON o.branch_id = b.id
                WHERE o.id = $1
            `, [oltId]);
            const oltInfo = oltResult.rows[0];
            
            console.log(`[SNMP] Sending ${faults.length} fault notifications...`);
            
            const response = await axios.post(`${this.phpApiUrl}/api/oms-notify.php`, {
                type: 'onu_fault',
                group_id: provisioningGroup,
                olt_name: oltInfo?.name || 'Unknown OLT',
                olt_ip: oltInfo?.ip_address || '',
                branch_name: oltInfo?.branch_name || 'Unassigned',
                branch_code: oltInfo?.branch_code || '',
                faults: faults
            });
            
            if (response.data && response.data.success) {
                console.log(`[SNMP] Sent fault notifications for ${faults.length} ONUs`);
                
                // Log faults to database
                for (const fault of faults) {
                    await this.logFault(oltId, fault);
                }
            } else {
                console.error(`[SNMP] Fault notification failed:`, response.data?.error || 'Unknown error');
            }
        } catch (error) {
            console.error(`[SNMP] Error sending fault notifications:`, error.message);
        }
    }
    
    async getProvisioningGroup() {
        try {
            const result = await this.pool.query(`
                SELECT setting_value FROM settings WHERE setting_key = 'wa_provisioning_group'
            `);
            return result.rows[0]?.setting_value || null;
        } catch (error) {
            return null;
        }
    }
    
    async logFault(oltId, fault) {
        try {
            await this.pool.query(`
                INSERT INTO onu_fault_log (olt_id, onu_id, serial_number, prev_status, new_status, customer_id, detected_at)
                VALUES ($1, $2, $3, $4, $5, $6, CURRENT_TIMESTAMP)
            `, [oltId, fault.onu_id, fault.sn, fault.prev_status, fault.new_status, fault.customer_id]);
        } catch (error) {
            // Table might not exist, ignore
        }
    }

    async updatePollingStats(oltId, success, errorMsg = null) {
        try {
            if (success) {
                await this.pool.query(`
                    UPDATE huawei_olts SET 
                        snmp_last_poll = CURRENT_TIMESTAMP,
                        snmp_status = 'online'
                    WHERE id = $1
                `, [oltId]);
            } else {
                await this.pool.query(`
                    UPDATE huawei_olts SET 
                        snmp_last_poll = CURRENT_TIMESTAMP,
                        snmp_status = CASE WHEN snmp_status = 'simulated' THEN 'simulated' ELSE 'offline' END
                    WHERE id = $1
                `, [oltId]);
            }
        } catch (error) {
            console.error(`[SNMP] Failed to update polling stats for OLT ${oltId}:`, error.message);
        }
    }

    async getPollingStatus() {
        const result = await this.pool.query(`
            SELECT id, name, snmp_status, snmp_last_poll,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND status = 'online') as online_count,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND status = 'offline') as offline_count,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND status = 'los') as los_count,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND is_authorized = false) as unconfigured_count
            FROM huawei_olts WHERE is_active = true
        `);
        return result.rows;
    }
}

module.exports = SNMPPollingWorker;
