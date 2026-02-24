const cron = require('node-cron');
const { Pool } = require('pg');
const snmp = require('net-snmp');
const axios = require('axios');

class SNMPPollingWorker {
    constructor(sessionManager = null, discoveryWorker = null) {
        this.pool = new Pool({
            connectionString: process.env.DATABASE_URL
        });
        // Set timezone on each connection to match PHP (Africa/Nairobi)
        this.pool.on('connect', (client) => {
            client.query("SET timezone TO 'Africa/Nairobi'");
        });
        this.sessionManager = sessionManager;
        this.discoveryWorker = discoveryWorker;
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
            onuTxPowerBase: '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.6',
            onuDistanceBase: '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20'
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
        
        this.signalHistoryInterval = setInterval(() => this.recordSignalHistory(), 5 * 60 * 1000);
        this.mgmtIpInterval = setInterval(() => this.refreshManagementIPs(), 5 * 60 * 1000);
        this.losBackfillInterval = setInterval(() => this.backfillOfflineDownCause(), 10 * 60 * 1000);
        this.signalCleanupInterval = setInterval(() => this.cleanupSignalHistory(), 6 * 60 * 60 * 1000);
        setTimeout(() => this.cleanupSignalHistory(), 30000);
        
        setTimeout(() => this.runPolling(), 5000);
        setTimeout(() => this.refreshManagementIPs(), 15000);
        setTimeout(() => this.backfillOfflineDownCause(), 60000);
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
        if (this.signalHistoryInterval) {
            clearInterval(this.signalHistoryInterval);
            console.log('[SNMP] Stopped signal history recording');
        }
        if (this.mgmtIpInterval) {
            clearInterval(this.mgmtIpInterval);
            console.log('[SNMP] Stopped Management IP refresh');
        }
        if (this.signalCleanupInterval) {
            clearInterval(this.signalCleanupInterval);
            console.log('[SNMP] Stopped signal history cleanup');
        }
        if (this.losBackfillInterval) {
            clearInterval(this.losBackfillInterval);
            console.log('[SNMP] Stopped LOS backfill');
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

    isOltPaused(oltId) {
        return this.discoveryWorker && this.discoveryWorker.isOltPaused(oltId);
    }

    decryptPassword(encrypted) {
        if (!encrypted) return '';
        try {
            const crypto = require('crypto');
            const key = process.env.SESSION_SECRET || 'default-secret-key-change-me';
            const combined = Buffer.from(encrypted, 'base64');
            const iv = combined.slice(0, 16);
            const ciphertextBase64 = combined.slice(16).toString('utf8');
            const ciphertext = Buffer.from(ciphertextBase64, 'base64');
            const keyBuffer = Buffer.alloc(32);
            Buffer.from(key).copy(keyBuffer);
            const decipher = crypto.createDecipheriv('aes-256-cbc', keyBuffer, iv);
            let decrypted = decipher.update(ciphertext);
            decrypted = Buffer.concat([decrypted, decipher.final()]);
            return decrypted.toString('utf8');
        } catch (e) {
            console.error(`[SNMP] Password decryption failed:`, e.message);
            return encrypted;
        }
    }

    async pollOlt(olt) {
        return new Promise(async (resolve, reject) => {
            const paused = this.isOltPaused(olt.id);
            
            const session = snmp.createSession(olt.ip_address, olt.snmp_community, {
                port: olt.snmp_port,
                version: olt.snmp_version === 'v1' ? snmp.Version1 : snmp.Version2c,
                timeout: 5000,
                retries: 1
            });

            try {
                const sysInfo = await this.getSysInfo(session);
                const onuStatuses = await this.getONUStatuses(session, olt.id);
                const opticalData = await this.getONUOpticalPower(session, olt.id);
                
                await this.updateOltInfo(olt.id, sysInfo);
                await this.updateONUStatuses(olt.id, onuStatuses);
                if (Object.keys(opticalData).length > 0) {
                    await this.updateONUOpticalPower(olt.id, opticalData, onuStatuses);
                }
                await this.updatePollingStats(olt.id, true, null, 'snmp');
                
                session.close();
                
                if (onuStatuses.length === 0 && !paused) {
                    const dbOnuCount = await this.pool.query(
                        'SELECT COUNT(*) as cnt FROM huawei_onus WHERE olt_id = $1 AND is_authorized = true',
                        [olt.id]
                    );
                    const hasDbOnus = parseInt(dbOnuCount.rows[0]?.cnt || 0) > 0;
                    
                    if (hasDbOnus) {
                        console.log(`[SNMP] OLT ${olt.name} returned 0 ONUs via SNMP but has ONUs in DB - triggering CLI refresh`);
                        this.pollOltViaCli(olt).catch(e => {
                            console.log(`[SNMP] CLI fallback for ${olt.name} failed: ${e.message}`);
                        });
                    }
                } else if (onuStatuses.length === 0 && paused) {
                    console.log(`[SNMP] OLT ${olt.name} paused for authorization - skipping all CLI activity`);
                }
                
                resolve({ olt: olt.name, ...sysInfo, onuCount: onuStatuses.length, method: 'snmp' });
            } catch (snmpError) {
                session.close();
                
                if (paused) {
                    console.log(`[SNMP] OLT ${olt.name} SNMP failed but paused for authorization - skipping CLI fallback`);
                    await this.updatePollingStats(olt.id, false, 'Paused for authorization');
                    resolve({ olt: olt.name, onuCount: 0, method: 'skipped-paused' });
                    return;
                }
                
                console.log(`[SNMP] OLT ${olt.name} SNMP failed, trying CLI fallback...`);
                
                try {
                    const cliResult = await this.pollOltViaCli(olt);
                    await this.updatePollingStats(olt.id, true, null, 'cli');
                    resolve({ olt: olt.name, ...cliResult, method: 'cli' });
                } catch (cliError) {
                    await this.updatePollingStats(olt.id, false, snmpError.message);
                    reject(snmpError);
                }
            }
        });
    }
    
    async pollOltViaCli(olt) {
        const isPaused = this.discoveryWorker && this.discoveryWorker.isOltPaused(olt.id);
        if (isPaused) {
            console.log(`[CLI] OLT ${olt.name} paused for authorization - skipping CLI poll`);
            return { onuCount: 0, method: 'cli-skipped' };
        }
        
        const onuResult = await this.pool.query(`
            SELECT o.id, o.frame, o.slot, o.port, o.onu_id, o.status, o.sn, o.name, o.description,
                   o.last_down_cause,
                   c.name as customer_name, c.phone as customer_phone, c.id as customer_id
            FROM huawei_onus o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.olt_id = $1 AND o.is_authorized = true
            ORDER BY o.frame, o.slot, o.port, o.onu_id
        `, [olt.id]);
        
        if (onuResult.rows.length === 0) {
            return { onuCount: 0, method: 'cli' };
        }
        
        if (!this.sessionManager) {
            console.log(`[CLI] Session manager not available for CLI polling`);
            return { onuCount: 0, method: 'cli', error: 'No session manager' };
        }
        
        console.log(`[CLI] Polling ${onuResult.rows.length} ONUs for OLT ${olt.name} via CLI...`);
        
        const oltKey = olt.id.toString();
        const existingSession = this.sessionManager.sessions?.get(oltKey);
        if (!existingSession || !existingSession.connected) {
            try {
                const configResult = await this.pool.query(`
                    SELECT ip_address, port, ssh_port, username, password_encrypted, cli_protocol, connection_type
                    FROM huawei_olts WHERE id = $1
                `, [olt.id]);
                if (configResult.rows.length > 0) {
                    const cfg = configResult.rows[0];
                    const proto = cfg.connection_type || cfg.cli_protocol || 'telnet';
                    const password = this.decryptPassword(cfg.password_encrypted);
                    const connectPort = proto === 'ssh' ? (cfg.ssh_port || cfg.port || 22) : (cfg.port || 23);
                    await this.sessionManager.connect(oltKey, {
                        host: cfg.ip_address,
                        port: connectPort,
                        sshPort: connectPort,
                        username: cfg.username,
                        password: password,
                        enablePassword: password,
                        protocol: proto
                    });
                    console.log(`[CLI] Connected to OLT ${olt.name} for polling`);
                }
            } catch (connErr) {
                console.log(`[CLI] Cannot connect to OLT ${olt.name}: ${connErr.message} - preserving current status`);
                return { onuCount: 0, method: 'cli', error: connErr.message };
            }
        }

        // Group by frame/slot/port for efficient batch querying
        const groupedByPort = {};
        for (const onu of onuResult.rows) {
            const key = `${onu.frame}/${onu.slot}/${onu.port}`;
            if (!groupedByPort[key]) groupedByPort[key] = [];
            groupedByPort[key].push(onu);
        }
        
        let updated = 0;
        const faults = [];
        
        try {
            for (const [portKey, onus] of Object.entries(groupedByPort)) {
                const [frame, slot, port] = portKey.split('/').map(Number);
                
                try {
                    const cmd = `display ont info ${frame} ${slot} ${port} all`;
                    const result = await this.sessionManager.execute(oltKey, cmd, { timeout: 30000 });
                    
                    if (!result) continue;
                    
                    const offlineOnus = [];
                    
                    for (const onu of onus) {
                        let status = 'offline';
                        
                        let found = false;
                        const patterns = [
                            new RegExp(`^\\s*${onu.onu_id}\\s+\\S+\\s+(online|offline|los)`, 'im'),
                            new RegExp(`\\b${onu.onu_id}\\s+\\S+\\s+\\S+\\s+(online|offline|los)`, 'im'),
                            new RegExp(`ONT\\s+${onu.onu_id}[^\\n]*(online|offline|los)`, 'i')
                        ];
                        
                        for (const pattern of patterns) {
                            const match = result.match(pattern);
                            if (match) {
                                const state = match[1].toLowerCase();
                                if (state === 'online') status = 'online';
                                else if (state.includes('los')) status = 'los';
                                else if (state === 'offline') status = 'offline';
                                found = true;
                                break;
                            }
                        }
                        
                        if (!found && updated < 5) {
                            console.log(`[CLI] ONU ${portKey}/${onu.onu_id} not matched in output, defaulting to offline`);
                        }
                        
                        if (status === 'offline') {
                            offlineOnus.push({ onu, found });
                            continue;
                        }
                        
                        if (status === 'online' && onu.status !== 'online') {
                            await this.pool.query(`
                                UPDATE huawei_onus SET status = $1, snmp_status = $1, online_since = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = $2
                            `, [status, onu.id]);
                        } else {
                            await this.pool.query(`
                                UPDATE huawei_onus SET status = $1, snmp_status = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2
                            `, [status, onu.id]);
                        }
                        updated++;
                    }
                    
                    // For offline ONUs, check last_down_cause to distinguish LOS vs normal offline
                    if (offlineOnus.length > 0) {
                        // For ONUs newly going offline (were online), query OLT for down cause per-ONU
                        const newlyOffline = offlineOnus.filter(o => o.onu.status === 'online').slice(0, 10);
                        for (const { onu } of newlyOffline) {
                            try {
                                const script = `config\ninterface gpon ${frame}/${slot}\ndisplay ont info ${port} ${onu.onu_id}\nquit\nquit\n`;
                                const infoResult = await this.sessionManager.executeRaw(oltKey, script, { timeout: 15000 });
                                if (infoResult) {
                                    const causeMatch = infoResult.match(/Last down cause\s*:\s*(.+)/i);
                                    if (causeMatch) {
                                        const cause = causeMatch[1].trim();
                                        await this.pool.query(`UPDATE huawei_onus SET last_down_cause = $1 WHERE id = $2`, [cause, onu.id]);
                                        onu.last_down_cause = cause;
                                        console.log(`[CLI] ONU ${frame}/${slot}/${port}/${onu.onu_id} (${onu.sn}) down cause: ${cause}`);
                                    }
                                }
                            } catch (e) { /* ignore individual query failures */ }
                            await new Promise(r => setTimeout(r, 200));
                        }
                        
                        // For already offline ONUs, last_down_cause is already loaded from the initial query
                        
                        // Now determine final status based on last_down_cause
                        for (const { onu } of offlineOnus) {
                            let finalStatus = 'offline';
                            const cause = (onu.last_down_cause || '').toLowerCase();
                            if (cause.includes('los') || cause.includes('lob') || cause.includes('losi') || cause.includes('lobi') || cause.includes('lofi')) {
                                finalStatus = 'los';
                            } else if (cause.includes('dying') || cause.includes('power')) {
                                finalStatus = 'dying-gasp';
                            }
                            
                            if (onu.status === 'online') {
                                await this.pool.query(`
                                    UPDATE huawei_onus SET status = $1, snmp_status = $1, online_since = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = $2
                                `, [finalStatus, onu.id]);
                                
                                if (finalStatus === 'los') {
                                    faults.push({
                                        onu_id: onu.id,
                                        sn: onu.sn,
                                        name: onu.name || onu.description || onu.sn,
                                        slot: onu.slot, port: onu.port, onu_id: onu.onu_id,
                                        prev_status: onu.status,
                                        new_status: finalStatus,
                                        customer_name: onu.customer_name,
                                        customer_phone: onu.customer_phone,
                                        customer_id: onu.customer_id
                                    });
                                }
                            } else {
                                await this.pool.query(`
                                    UPDATE huawei_onus SET status = $1, snmp_status = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2
                                `, [finalStatus, onu.id]);
                            }
                            updated++;
                        }
                    }
                } catch (e) {
                    console.log(`[CLI] Error polling port ${portKey}: ${e.message} - preserving current status for ${onus.length} ONUs`);
                }
            }
        } catch (e) {
            console.log(`[CLI] Error during CLI polling: ${e.message}`);
        }
        
        console.log(`[CLI] Updated ${updated} ONUs for OLT ${olt.name}`);
        
        if (faults.length > 0) {
            console.log(`[CLI] Detected ${faults.length} fault(s) on OLT ${olt.name} - sending notifications`);
            await this.sendFaultNotifications(olt.id, faults);
        }
        
        return { onuCount: updated, method: 'cli' };
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
            const oidsToTry = [
                '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15',  // hwGponDeviceOntRunStatus (primary)
                '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',   // hwGponDeviceOntRunState (alternate)
            ];

            const tryWalk = (oid) => {
                const baseLen = oid.split('.').length;
                return new Promise((res) => {
                    const results = [];
                    let logCount = 0;
                    session.subtree(oid, 20, (varbinds) => {
                        varbinds.forEach(vb => {
                            if (snmp.isVarbindError(vb)) return;
                            
                            const oidParts = vb.oid.split('.');
                            const indexParts = oidParts.slice(baseLen);
                            const statusCode = parseInt(vb.value);
                            const status = this.STATUS_MAP[statusCode] || 'unknown';
                            
                            let frame = 0, slot = 0, port = 0, onuId = 0;
                            
                            if (indexParts.length >= 4) {
                                frame = parseInt(indexParts[0]);
                                slot = parseInt(indexParts[1]);
                                port = parseInt(indexParts[2]);
                                onuId = parseInt(indexParts[3]);
                            } else if (indexParts.length >= 2) {
                                const ifIndex = parseInt(indexParts[0]);
                                onuId = parseInt(indexParts[1]);
                                const ponIndex = ifIndex > 0xFFFFFF ? (ifIndex & 0xFFFFFF) : ifIndex;
                                slot = (ponIndex >> 13) & 0x1F;
                                port = (ponIndex >> 8) & 0x1F;
                            }
                            
                            if (logCount < 3) {
                                console.log(`[SNMP] ONU sample: index=${indexParts.join('.')}, decoded=f${frame}/s${slot}/p${port}/o${onuId}, status=${status}`);
                                logCount++;
                            }
                            
                            results.push({
                                index: indexParts.join('.'),
                                frame, slot, port, onuId,
                                statusCode,
                                status
                            });
                        });
                    }, (error) => {
                        if (error) {
                            console.log(`[SNMP] Walk ${oid} for OLT ${oltId} error: ${error.message || error}`);
                        }
                        res(results);
                    });
                });
            };

            const tryNextOid = async () => {
                for (const oid of oidsToTry) {
                    const results = await tryWalk(oid);
                    console.log(`[SNMP] Walk OID ${oid.split('.').slice(-3).join('.')} for OLT ${oltId}: ${results.length} ONUs`);
                    if (results.length > 0) {
                        return results;
                    }
                }
                return [];
            };

            tryNextOid().then(statuses => {
                console.log(`[SNMP] ONU status walk completed for OLT ${oltId}: ${statuses.length} ONUs`);
                resolve(statuses);
            }).catch(err => {
                console.error(`[SNMP] All OID walks failed for OLT ${oltId}: ${err.message}`);
                resolve([]);
            });
        });
    }

    getONUOpticalPower(session, oltId) {
        return new Promise((resolve) => {
            const powerData = {};
            const rxOid = this.OIDs.onuRxPowerBase;
            const txOid = this.OIDs.onuTxPowerBase;
            const distOid = this.OIDs.onuDistanceBase;
            const totalWalks = 3;
            let completedWalks = 0;

            const parseIndex = (oid, baseLen) => {
                const oidParts = oid.split('.');
                const indexParts = oidParts.slice(baseLen);
                let slot = 0, port = 0, onuId = 0;
                if (indexParts.length >= 4) {
                    slot = parseInt(indexParts[1]);
                    port = parseInt(indexParts[2]);
                    onuId = parseInt(indexParts[3]);
                } else if (indexParts.length >= 2) {
                    const ifIndex = parseInt(indexParts[0]);
                    onuId = parseInt(indexParts[1]);
                    const ponIndex = ifIndex > 0xFFFFFF ? (ifIndex & 0xFFFFFF) : ifIndex;
                    slot = (ponIndex >> 13) & 0x1F;
                    port = (ponIndex >> 8) & 0x1F;
                }
                return { slot, port, onuId };
            };

            const walkOptical = (oid, field) => {
                const baseLen = oid.split('.').length;
                session.subtree(oid, 20, (varbinds) => {
                    varbinds.forEach(vb => {
                        if (snmp.isVarbindError(vb)) return;
                        const rawValue = parseInt(vb.value);
                        if (isNaN(rawValue) || rawValue === 2147483647 || rawValue === -2147483648) return;
                        const dbm = rawValue / 100.0;
                        if (dbm < -50 || dbm > 10) return;
                        const { slot, port, onuId } = parseIndex(vb.oid, baseLen);
                        const key = `${slot}.${port}.${onuId}`;
                        if (!powerData[key]) powerData[key] = { slot, port, onuId };
                        powerData[key][field] = dbm;
                    });
                }, (error) => {
                    if (error) {
                        console.log(`[SNMP] Optical ${field} walk error for OLT ${oltId}: ${error.message || error}`);
                    }
                    completedWalks++;
                    if (completedWalks >= totalWalks) {
                        const count = Object.keys(powerData).length;
                        if (count > 0) {
                            console.log(`[SNMP] Optical power collected for OLT ${oltId}: ${count} ONUs`);
                        }
                        resolve(powerData);
                    }
                });
            };

            const walkDistance = () => {
                const baseLen = distOid.split('.').length;
                session.subtree(distOid, 20, (varbinds) => {
                    varbinds.forEach(vb => {
                        if (snmp.isVarbindError(vb)) return;
                        const rawValue = parseInt(vb.value);
                        if (isNaN(rawValue) || rawValue < 0 || rawValue > 100000) return;
                        const { slot, port, onuId } = parseIndex(vb.oid, baseLen);
                        const key = `${slot}.${port}.${onuId}`;
                        if (!powerData[key]) powerData[key] = { slot, port, onuId };
                        powerData[key].distance = rawValue;
                    });
                }, (error) => {
                    if (error) {
                        console.log(`[SNMP] Distance walk error for OLT ${oltId}: ${error.message || error}`);
                    }
                    completedWalks++;
                    if (completedWalks >= totalWalks) {
                        const count = Object.keys(powerData).length;
                        if (count > 0) {
                            console.log(`[SNMP] Optical power collected for OLT ${oltId}: ${count} ONUs`);
                        }
                        resolve(powerData);
                    }
                });
            };

            walkOptical(rxOid, 'rx_power');
            walkOptical(txOid, 'tx_power');
            walkDistance();
        });
    }

    async updateONUOpticalPower(oltId, powerData, statuses) {
        const client = await this.pool.connect();
        try {
            await client.query('BEGIN');
            let updated = 0;
            
            const dbResult = await client.query(`
                SELECT id, slot, port, onu_id FROM huawei_onus WHERE olt_id = $1
            `, [oltId]);
            
            const dbMap = {};
            for (const row of dbResult.rows) {
                const key = `${row.slot}.${row.port}.${row.onu_id}`;
                if (!dbMap[key]) dbMap[key] = row;
            }

            for (const [key, power] of Object.entries(powerData)) {
                let { slot, port, onuId } = power;
                const rx = power.rx_power ?? null;
                const tx = power.tx_power ?? null;
                const dist = power.distance ?? null;
                
                let dbOnu = dbMap[`${slot}.${port}.${onuId}`];
                
                if (!dbOnu) {
                    const methods = this.decodeIfIndex(slot * 8192 + port * 256);
                    for (const m of methods) {
                        dbOnu = dbMap[`${m.slot}.${m.port}.${onuId}`];
                        if (dbOnu) break;
                    }
                }
                
                if (!dbOnu) continue;

                const result = await client.query(`
                    UPDATE huawei_onus SET rx_power = COALESCE($1, rx_power), tx_power = COALESCE($2, tx_power), distance = COALESCE($3, distance), updated_at = CURRENT_TIMESTAMP
                    WHERE id = $4
                `, [rx, tx, dist, dbOnu.id]);

                if (result.rowCount > 0) {
                    updated++;
                }
            }

            await client.query('COMMIT');
            if (updated > 0) {
                console.log(`[SNMP] Updated optical power for ${updated} ONUs on OLT ${oltId}`);
            }
        } catch (error) {
            await client.query('ROLLBACK');
            console.error(`[SNMP] Failed to update optical power for OLT ${oltId}:`, error.message);
        } finally {
            client.release();
        }
    }

    async recordSignalHistory() {
        try {
            const result = await this.pool.query(`
                INSERT INTO onu_signal_history (onu_id, rx_power, tx_power, status, recorded_at)
                SELECT id, rx_power, tx_power, status, CURRENT_TIMESTAMP
                FROM huawei_onus
                WHERE is_authorized = true AND rx_power IS NOT NULL AND status IS NOT NULL
            `);
            if (result.rowCount > 0) {
                console.log(`[SNMP] Recorded signal history for ${result.rowCount} ONUs`);
            }
        } catch (error) {
            console.error('[SNMP] Failed to record signal history:', error.message);
        }
    }

    async cleanupSignalHistory() {
        try {
            const retentionDays = 2;
            const result = await this.pool.query(`
                DELETE FROM onu_signal_history 
                WHERE recorded_at < NOW() - INTERVAL '${retentionDays} days'
            `);
            if (result.rowCount > 0) {
                console.log(`[SNMP] Signal history cleanup: removed ${result.rowCount} records older than ${retentionDays} days`);
            }
        } catch (error) {
            console.error('[SNMP] Signal history cleanup failed:', error.message);
        }
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

    decodeIfIndex(ifIndex) {
        const ponIndex = ifIndex > 0xFFFFFF ? (ifIndex & 0xFFFFFF) : ifIndex;
        
        const base = { slot: (ponIndex >> 13) & 0x1F, port: (ponIndex >> 8) & 0x1F };
        const methods = [
            base,
            { slot: (ponIndex >> 8) & 0xFF, port: ponIndex & 0xFF },
            { slot: Math.floor(ponIndex / 256), port: ponIndex % 256 },
        ];
        
        for (const offset of [1, -1, 2, -2]) {
            methods.push({ slot: base.slot + offset, port: base.port, slotOffset: offset });
        }
        
        return methods;
    }

    async updateONUStatuses(oltId, statuses) {
        if (statuses.length === 0) return;

        const client = await this.pool.connect();
        const faults = [];
        try {
            await client.query('BEGIN');
            
            const dbResult = await client.query(`
                SELECT o.id, o.sn, o.slot, o.port, o.onu_id, o.status as prev_status, o.description, o.name,
                       o.last_down_cause,
                       c.name as customer_name, c.phone as customer_phone, c.id as customer_id
                FROM huawei_onus o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.olt_id = $1
            `, [oltId]);
            
            const dbMap = {};
            for (const row of dbResult.rows) {
                const key = `${row.slot}.${row.port}.${row.onu_id}`;
                if (!dbMap[key]) dbMap[key] = row;
            }
            
            let updated = 0;
            let noMatch = 0;
            let correctedSlotPort = null;
            
            for (const s of statuses) {
                let { frame, slot, port, onuId } = s;
                let key = `${slot}.${port}.${onuId}`;
                let prevOnu = dbMap[key];
                
                if (!prevOnu && !correctedSlotPort) {
                    const indexParts = s.index.split('.');
                    if (indexParts.length >= 2) {
                        const ifIndex = parseInt(indexParts[0]);
                        const methods = this.decodeIfIndex(ifIndex);
                        
                        for (const m of methods) {
                            const tryKey = `${m.slot}.${m.port}.${onuId}`;
                            if (dbMap[tryKey]) {
                                const offsetUsed = m.slotOffset || 0;
                                console.log(`[SNMP] OLT ${oltId}: ifIndex ${ifIndex} - method (s=${slot},p=${port}) failed, using (s=${m.slot},p=${m.port})${offsetUsed ? ` [slot offset=${offsetUsed > 0 ? '+' : ''}${offsetUsed}]` : ''}`);
                                correctedSlotPort = { 
                                    slotOffset: offsetUsed,
                                    calcSlot: (ifIdx) => {
                                        const pi = ifIdx > 0xFFFFFF ? (ifIdx & 0xFFFFFF) : ifIdx;
                                        const baseSlot = (pi >> 13) & 0x1F;
                                        const basePort = (pi >> 8) & 0x1F;
                                        if (offsetUsed) return { slot: baseSlot + offsetUsed, port: basePort };
                                        if (m.slot === baseSlot) return { slot: baseSlot, port: basePort };
                                        if (m.slot === ((pi >> 8) & 0xFF)) return { slot: (pi >> 8) & 0xFF, port: pi & 0xFF };
                                        return { slot: Math.floor(pi / 256), port: pi % 256 };
                                    }
                                };
                                slot = m.slot;
                                port = m.port;
                                key = tryKey;
                                prevOnu = dbMap[key];
                                break;
                            }
                        }
                        
                        if (!prevOnu) {
                            noMatch++;
                            if (noMatch <= 3) {
                                const dbSlots = [...new Set(dbResult.rows.map(r => `${r.slot}/${r.port}`))].slice(0, 5);
                                console.log(`[SNMP] OLT ${oltId}: No match for ifIndex=${ifIndex}, decoded s=${slot}/p=${port}/o=${onuId}. DB slots: ${dbSlots.join(', ')}`);
                            }
                            continue;
                        }
                    } else {
                        noMatch++;
                        continue;
                    }
                } else if (!prevOnu && correctedSlotPort) {
                    const indexParts = s.index.split('.');
                    if (indexParts.length >= 2) {
                        const ifIndex = parseInt(indexParts[0]);
                        const corrected = correctedSlotPort.calcSlot(ifIndex);
                        slot = corrected.slot;
                        port = corrected.port;
                        key = `${slot}.${port}.${onuId}`;
                        prevOnu = dbMap[key];
                    }
                    if (!prevOnu) {
                        noMatch++;
                        continue;
                    }
                }
                
                const prevStatus = prevOnu?.prev_status;
                const dbId = prevOnu?.id;
                
                let effectiveStatus = s.status;
                if (s.status === 'offline') {
                    const cause = (prevOnu.last_down_cause || '').toLowerCase();
                    if (cause && cause !== '-') {
                        const causeSuggestsLos = cause.includes('los') || cause.includes('lob') || cause.includes('losi') || cause.includes('lobi') || cause.includes('lofi');
                        const causeSuggestsDying = cause.includes('dying') || cause.includes('power');
                        if (causeSuggestsLos) {
                            effectiveStatus = 'los';
                        } else if (causeSuggestsDying) {
                            effectiveStatus = 'dying-gasp';
                        }
                    }
                }
                
                let result;
                if (effectiveStatus === 'online' && prevStatus !== 'online') {
                    result = await client.query(`
                        UPDATE huawei_onus 
                        SET status = $1, snmp_status = $2, online_since = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                        WHERE id = $3
                    `, [effectiveStatus, s.status, dbId]);
                } else if (effectiveStatus !== 'online' && prevStatus === 'online') {
                    result = await client.query(`
                        UPDATE huawei_onus 
                        SET status = $1, snmp_status = $2, online_since = NULL, updated_at = CURRENT_TIMESTAMP
                        WHERE id = $3
                    `, [effectiveStatus, s.status, dbId]);
                } else {
                    result = await client.query(`
                        UPDATE huawei_onus 
                        SET status = $1, snmp_status = $2, updated_at = CURRENT_TIMESTAMP
                        WHERE id = $3
                    `, [effectiveStatus, s.status, dbId]);
                }
                
                if (result.rowCount > 0) {
                    updated++;
                    if (prevOnu && prevStatus !== s.status && (s.status === 'los' || s.status === 'dying-gasp')) {
                        faults.push({
                            onu_id: prevOnu.id,
                            sn: prevOnu.sn,
                            name: prevOnu.name || prevOnu.description || prevOnu.sn,
                            slot, port, onu_id: onuId,
                            prev_status: prevStatus || 'unknown',
                            new_status: s.status,
                            customer_name: prevOnu.customer_name,
                            customer_phone: prevOnu.customer_phone,
                            customer_id: prevOnu.customer_id
                        });
                    }
                } else {
                    noMatch++;
                }
            }

            await client.query('COMMIT');
            console.log(`[SNMP] OLT ${oltId}: updated ${updated}/${statuses.length} ONU statuses${noMatch > 0 ? ` (${noMatch} not matched in DB)` : ''}`);
            
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
                SELECT setting_value FROM company_settings WHERE setting_key = 'wa_provisioning_group'
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

    async updatePollingStats(oltId, success, errorMsg = null, method = 'snmp') {
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
            console.log(`[SNMP] Updated OLT ${oltId} status via ${method}`);
        } catch (error) {
            console.error(`[SNMP] Failed to update polling stats for OLT ${oltId}:`, error.message);
        }
    }

    async backfillOfflineDownCause() {
        if (!this.sessionManager) return;
        
        try {
            const olts = await this.pool.query(`
                SELECT id, name, ip_address FROM huawei_olts WHERE is_active = true
            `);
            
            for (const olt of olts.rows) {
                const oltKey = olt.id.toString();
                const session = this.sessionManager.sessions?.get(oltKey);
                if (!session || !session.connected) continue;
                
                await this.pool.query(`
                    UPDATE huawei_onus 
                    SET status = 'los'
                    WHERE olt_id = $1 
                      AND is_authorized = true 
                      AND status = 'offline'
                      AND last_down_cause IS NOT NULL 
                      AND last_down_cause != ''
                      AND (LOWER(last_down_cause) LIKE '%los%' OR LOWER(last_down_cause) LIKE '%lob%' 
                           OR LOWER(last_down_cause) LIKE '%losi%' OR LOWER(last_down_cause) LIKE '%lobi%' 
                           OR LOWER(last_down_cause) LIKE '%lofi%')
                `, [olt.id]);
                
                await this.pool.query(`
                    UPDATE huawei_onus 
                    SET status = 'dying-gasp'
                    WHERE olt_id = $1 
                      AND is_authorized = true 
                      AND status = 'offline'
                      AND last_down_cause IS NOT NULL 
                      AND last_down_cause != ''
                      AND (LOWER(last_down_cause) LIKE '%dying%' OR LOWER(last_down_cause) LIKE '%power%')
                `, [olt.id]);
                
                const offlineOnus = await this.pool.query(`
                    SELECT id, frame, slot, port, onu_id, sn, status, last_down_cause
                    FROM huawei_onus 
                    WHERE olt_id = $1 
                      AND is_authorized = true 
                      AND status IN ('offline', 'los', 'dying-gasp')
                      AND (last_down_cause IS NULL OR last_down_cause = '')
                    ORDER BY slot, port, onu_id
                    LIMIT 50
                `, [olt.id]);
                
                if (offlineOnus.rows.length === 0) {
                    console.log(`[LOS-Backfill] No unresolved offline ONUs on ${olt.name}`);
                    continue;
                }
                
                const portGroups = {};
                for (const onu of offlineOnus.rows) {
                    const key = `${onu.frame}/${onu.slot}/${onu.port}`;
                    if (!portGroups[key]) portGroups[key] = { frame: onu.frame, slot: onu.slot, port: onu.port, onus: [] };
                    portGroups[key].onus.push(onu);
                }
                
                const portKeys = Object.keys(portGroups);
                console.log(`[LOS-Backfill] Checking ${offlineOnus.rows.length} offline ONUs across ${portKeys.length} ports on ${olt.name}...`);
                let updated = 0;
                let losFound = 0;
                const backfillFaults = [];
                
                const backfillStart = Date.now();
                const BACKFILL_TIME_BUDGET_MS = 120000;
                let backfillTimedOut = false;
                
                for (const pk of portKeys) {
                    if (backfillTimedOut) break;
                    const group = portGroups[pk];
                    try {
                        const causeMap = {};
                        for (const onu of group.onus) {
                            if (Date.now() - backfillStart > BACKFILL_TIME_BUDGET_MS) {
                                console.log(`[LOS-Backfill] Time budget exceeded (${BACKFILL_TIME_BUDGET_MS/1000}s), deferring remaining ONUs`);
                                backfillTimedOut = true;
                                break;
                            }
                            try {
                                const script = `config\ninterface gpon ${group.frame}/${group.slot}\ndisplay ont info ${group.port} ${onu.onu_id}\nquit\nquit\n`;
                                const result = await this.sessionManager.executeRaw(oltKey, script, { timeout: 15000 });
                                if (result) {
                                    const causeMatch = result.match(/Last down cause\s*:\s*(.+)/i);
                                    if (causeMatch) {
                                        causeMap[onu.onu_id] = causeMatch[1].trim();
                                    }
                                }
                            } catch (e) { /* skip individual ONU failures */ }
                            await new Promise(r => setTimeout(r, 200));
                        }
                        
                        for (const onu of group.onus) {
                            const cause = causeMap[onu.onu_id];
                            if (!cause) continue;
                            
                            const causeLower = cause.toLowerCase();
                            let newStatus = 'offline';
                            if (causeLower.includes('los') || causeLower.includes('lob') || causeLower.includes('losi') || causeLower.includes('lobi') || causeLower.includes('lofi')) {
                                newStatus = 'los';
                                losFound++;
                            } else if (causeLower.includes('dying') || causeLower.includes('power')) {
                                newStatus = 'dying-gasp';
                            }
                            
                            const existingCause = (onu.last_down_cause || '').toLowerCase();
                            if (onu.status === newStatus && existingCause === causeLower) continue;
                            
                            await this.pool.query(`
                                UPDATE huawei_onus SET last_down_cause = $1, status = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3
                            `, [cause, newStatus, onu.id]);
                            updated++;
                            
                            if ((newStatus === 'los' || newStatus === 'dying-gasp') && onu.status !== newStatus) {
                                const onuDetail = await this.pool.query(`
                                    SELECT o.*, c.name as customer_name, c.phone as customer_phone
                                    FROM huawei_onus o LEFT JOIN customers c ON o.customer_id = c.id
                                    WHERE o.id = $1
                                `, [onu.id]);
                                const detail = onuDetail.rows[0];
                                if (detail) {
                                    backfillFaults.push({
                                        onu_id: detail.id,
                                        sn: detail.sn,
                                        name: detail.name || detail.description || detail.sn,
                                        slot: detail.slot, port: detail.port, onu_id: detail.onu_id,
                                        prev_status: onu.status,
                                        new_status: newStatus,
                                        customer_name: detail.customer_name,
                                        customer_phone: detail.customer_phone,
                                        customer_id: detail.customer_id
                                    });
                                }
                            }
                        }
                    } catch (e) {
                        if (e.message && e.message.includes('not connected')) break;
                    }
                }
                
                console.log(`[LOS-Backfill] Updated ${updated} ONUs on ${olt.name} (${losFound} LOS detected)`);
                
                if (backfillFaults.length > 0) {
                    console.log(`[LOS-Backfill] Sending ${backfillFaults.length} fault notifications for ${olt.name}`);
                    await this.sendFaultNotifications(olt.id, backfillFaults);
                }
            }
        } catch (e) {
            console.log(`[LOS-Backfill] Error: ${e.message}`);
        }
    }

    async refreshManagementIPs() {
        const startTime = Date.now();
        try {
            const settingsResult = await this.pool.query(
                "SELECT setting_value FROM settings WHERE setting_key = 'genieacs_url'"
            );
            const genieUrl = settingsResult.rows[0]?.setting_value;
            if (!genieUrl) {
                return;
            }

            const onusResult = await this.pool.query(
                "SELECT id, sn FROM huawei_onus WHERE is_authorized = true AND sn IS NOT NULL"
            );
            if (onusResult.rows.length === 0) return;

            const serialToOnu = {};
            for (const onu of onusResult.rows) {
                const sn = onu.sn.replace(/[\s-]/g, '').toUpperCase();
                serialToOnu[sn] = onu.id;
                if (sn.length >= 12) {
                    serialToOnu[sn.substring(4)] = onu.id;
                    serialToOnu[sn.substring(0, 4) + '-' + sn.substring(4)] = onu.id;
                }
            }

            let devices = [];
            try {
                const resp = await axios.get(`${genieUrl}/devices`, {
                    params: { projection: '_id,InternetGatewayDevice.ManagementServer.ConnectionRequestURL._value,InternetGatewayDevice.DeviceInfo.SerialNumber._value,Device.ManagementServer.ConnectionRequestURL._value,Device.DeviceInfo.SerialNumber._value,_lastInform' },
                    timeout: 30000
                });
                devices = resp.data || [];
            } catch (e) {
                console.log(`[MGMT-IP] GenieACS unreachable: ${e.message}`);
                return;
            }

            let updated = 0;
            for (const device of devices) {
                try {
                    let sn = device?.InternetGatewayDevice?.DeviceInfo?.SerialNumber?._value
                          || device?.Device?.DeviceInfo?.SerialNumber?._value
                          || '';
                    sn = sn.replace(/[\s-]/g, '').toUpperCase();

                    let onuId = serialToOnu[sn];
                    if (!onuId && sn.length >= 8) {
                        onuId = serialToOnu[sn.substring(sn.length - 8)];
                    }
                    if (!onuId) {
                        const deviceId = device._id || '';
                        const parts = deviceId.split('-');
                        for (const part of parts) {
                            const normalized = part.replace(/[\s-]/g, '').toUpperCase();
                            if (serialToOnu[normalized]) {
                                onuId = serialToOnu[normalized];
                                break;
                            }
                        }
                    }
                    if (!onuId) continue;

                    let ip = null;
                    const connUrl = device?.InternetGatewayDevice?.ManagementServer?.ConnectionRequestURL?._value
                                 || device?.Device?.ManagementServer?.ConnectionRequestURL?._value
                                 || '';
                    const ipMatch = connUrl.match(/https?:\/\/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/);
                    if (ipMatch) ip = ipMatch[1];

                    let tr069Status = 'offline';
                    if (device._lastInform) {
                        const lastInform = new Date(device._lastInform).getTime();
                        const diff = (Date.now() - lastInform) / 1000;
                        tr069Status = diff < 300 ? 'online' : 'offline';
                    }

                    if (ip) {
                        await this.pool.query(
                            "UPDATE huawei_onus SET tr069_ip = $1, tr069_status = $2, genieacs_id = $3, updated_at = NOW() WHERE id = $4",
                            [ip, tr069Status, device._id, onuId]
                        );
                        updated++;
                    } else {
                        await this.pool.query(
                            "UPDATE huawei_onus SET tr069_status = $1, genieacs_id = $2, updated_at = NOW() WHERE id = $3",
                            [tr069Status, device._id, onuId]
                        );
                    }
                } catch (e) {
                    // Skip individual device errors
                }
            }

            const elapsed = Date.now() - startTime;
            if (updated > 0) {
                console.log(`[MGMT-IP] Refreshed ${updated}/${devices.length} Management IPs in ${elapsed}ms`);
            }
        } catch (error) {
            console.error('[MGMT-IP] Management IP refresh error:', error.message);
        }
    }

    async getPollingStatus() {
        const result = await this.pool.query(`
            SELECT id, name, snmp_status, snmp_last_poll,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND status = 'online') as online_count,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND status = 'offline' AND NOT ((status = 'los') OR (status = 'offline' AND last_down_cause IS NOT NULL AND last_down_cause != '' AND last_down_cause != '-' AND (LOWER(last_down_cause) LIKE '%los%' OR LOWER(last_down_cause) LIKE '%lob%' OR LOWER(last_down_cause) LIKE '%lofi%')))) as offline_count,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND ((status = 'los') OR (status = 'offline' AND last_down_cause IS NOT NULL AND last_down_cause != '' AND last_down_cause != '-' AND (LOWER(last_down_cause) LIKE '%los%' OR LOWER(last_down_cause) LIKE '%lob%' OR LOWER(last_down_cause) LIKE '%lofi%')))) as los_count,
                   (SELECT COUNT(*) FROM huawei_onus WHERE olt_id = huawei_olts.id AND is_authorized = false) as unconfigured_count
            FROM huawei_olts WHERE is_active = true
        `);
        return result.rows;
    }
}

module.exports = SNMPPollingWorker;
