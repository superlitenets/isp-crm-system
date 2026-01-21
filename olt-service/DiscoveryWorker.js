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
        this.smartOltSerials = new Set();
        this.smartOltCacheTime = 0;
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
            await this.refreshSmartOltCache();
            
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

        // Try SNMP first for faster, non-blocking discovery
        let usedSnmp = false;
        try {
            usedSnmp = await this.discoverViaSNMP(olt);
        } catch (error) {
            console.log(`[Discovery] SNMP discovery failed for ${olt.name}: ${error.message}`);
        }

        // Fall back to CLI only for unconfigured ONU discovery (autofind)
        // SNMP can't discover unconfigured ONUs - they're not in the ONU table yet
        if (!usedSnmp) {
            await this.discoverViaCLI(olt);
        } else {
            // Even with SNMP, we still need CLI for autofind (unconfigured ONUs)
            await this.discoverUnconfiguredViaCLI(olt);
        }
    }

    async discoverViaSNMP(olt) {
        // PHP-based SNMP discovery disabled - it blocks the single-threaded PHP server
        // SNMP polling is handled separately by SNMPWorker.js
        console.log(`[Discovery] Skipping PHP SNMP (using CLI + background SNMP polling)`);
        return false;
    }

    async updateOnuFromSNMP(oltId, snmpData) {
        try {
            const serial = snmpData.sn?.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            if (!serial) return;

            const existingResult = await this.pool.query(`
                SELECT id FROM huawei_onus 
                WHERE olt_id = $1 AND UPPER(REPLACE(sn, '-', '')) = $2
            `, [oltId, serial]);

            if (existingResult.rows.length > 0) {
                await this.pool.query(`
                    UPDATE huawei_onus 
                    SET rx_power = COALESCE($1, rx_power),
                        tx_power = COALESCE($2, tx_power),
                        distance = COALESCE($3, distance),
                        status = COALESCE($4, status),
                        frame = COALESCE($5, frame),
                        slot = COALESCE($6, slot),
                        port = COALESCE($7, port),
                        onu_id = COALESCE($8, onu_id),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = $9
                `, [
                    snmpData.rx_power || null,
                    snmpData.tx_power || null,
                    snmpData.distance || null,
                    snmpData.status || null,
                    snmpData.frame || 0,
                    snmpData.slot || null,
                    snmpData.port || null,
                    snmpData.onu_id || null,
                    existingResult.rows[0].id
                ]);
            } else {
                let onuName = '';
                const desc = snmpData.description || '';
                if (desc) {
                    // Truncate at first underscore
                    const parts = desc.split('_');
                    onuName = parts[0].trim();
                }
                if (!onuName) {
                    onuName = `ONU ${snmpData.slot || 0}/${snmpData.port || 0}:${snmpData.onu_id || 0}`;
                }

                await this.pool.query(`
                    INSERT INTO huawei_onus (olt_id, sn, name, description, frame, slot, port, onu_id, 
                        rx_power, tx_power, distance, status, is_authorized, created_at, updated_at)
                    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                `, [
                    oltId,
                    snmpData.sn,
                    onuName,
                    desc,
                    snmpData.frame || 0,
                    snmpData.slot || 0,
                    snmpData.port || 0,
                    snmpData.onu_id || 0,
                    snmpData.rx_power || null,
                    snmpData.tx_power || null,
                    snmpData.distance || null,
                    snmpData.status || 'online'
                ]);
                console.log(`[Discovery] Added new ONU: ${onuName} (${snmpData.sn})`);
            }
        } catch (error) {
            console.error(`[Discovery] Error saving ONU ${snmpData.sn}:`, error.message);
        }
    }

    async discoverViaCLI(olt) {
        // Full CLI discovery (legacy method)
        const sessionStatus = this.sessionManager.getSessionStatus(olt.id.toString());
        if (!sessionStatus.connected) {
            console.log(`[Discovery] Connecting to ${olt.name}...`);
            const password = this.decryptPassword(olt.password_encrypted);
            const protocol = olt.cli_protocol || 'telnet';
            await this.sessionManager.connect(olt.id.toString(), {
                host: olt.ip_address,
                port: olt.port || 23,
                sshPort: olt.ssh_port || 22,
                username: olt.username,
                password: password,
                protocol: protocol
            });
        }

        const command = 'display ont autofind all';
        const output = await this.sessionManager.execute(olt.id.toString(), command, { timeout: 60000 });

        const unconfiguredOnus = this.parseAutofindOutput(output);
        console.log(`[Discovery] CLI found ${unconfiguredOnus.length} unconfigured ONUs on ${olt.name}`);

        // Clean up: mark ONUs as authorized if they're no longer in autofind
        await this.cleanupAuthorizedOnus(olt, unconfiguredOnus);

        for (const onu of unconfiguredOnus) {
            await this.recordDiscovery(olt, onu);
        }
        
        // Also update ONU statuses via CLI since SNMP may not work on some OLTs
        await this.updateOnuStatusesViaCLI(olt);
    }

    async discoverUnconfiguredViaCLI(olt) {
        // Only discover unconfigured ONUs via CLI (autofind command)
        // This is the only CLI command needed when SNMP handles the rest
        try {
            const sessionStatus = this.sessionManager.getSessionStatus(olt.id.toString());
            if (!sessionStatus.connected) {
                const password = this.decryptPassword(olt.password_encrypted);
                const protocol = olt.cli_protocol || 'telnet';
                await this.sessionManager.connect(olt.id.toString(), {
                    host: olt.ip_address,
                    port: olt.port || 23,
                    sshPort: olt.ssh_port || 22,
                    username: olt.username,
                    password: password,
                    protocol: protocol
                });
            }

            const command = 'display ont autofind all';
            const output = await this.sessionManager.execute(olt.id.toString(), command, { timeout: 30000 });

            const unconfiguredOnus = this.parseAutofindOutput(output);
            
            // Clean up: mark ONUs as authorized if they're no longer in autofind
            await this.cleanupAuthorizedOnus(olt, unconfiguredOnus);
            
            if (unconfiguredOnus.length > 0) {
                console.log(`[Discovery] Found ${unconfiguredOnus.length} unconfigured ONUs on ${olt.name}`);
                for (const onu of unconfiguredOnus) {
                    await this.recordDiscovery(olt, onu);
                }
            }
            
            // Also update ONU statuses via CLI since SNMP may not work on some OLTs
            await this.updateOnuStatusesViaCLI(olt);
        } catch (error) {
            console.log(`[Discovery] Autofind error on ${olt.name}: ${error.message}`);
        }
    }

    async updateOnuStatusesViaCLI(olt) {
        try {
            // Get all configured ONUs for this OLT
            const onusResult = await this.pool.query(`
                SELECT id, sn, slot, port, onu_id FROM huawei_onus 
                WHERE olt_id = $1 AND slot IS NOT NULL AND port IS NOT NULL
            `, [olt.id]);
            
            if (onusResult.rows.length === 0) {
                return;
            }
            
            // Use a single command to get all ONU info at once
            try {
                const command = 'display ont info 0 all';
                const output = await this.sessionManager.execute(olt.id.toString(), command, { timeout: 30000 });
                
                // Parse the status output
                const statusMap = this.parseOntInfoOutput(output);
                
                let updatedCount = 0;
                
                // Update each ONU's status
                for (const onu of onusResult.rows) {
                    // Try multiple key formats to find a match
                    // OLT output F/S/P could be frame/slot/port or just slot/port depending on OLT
                    const keys = [
                        `0/${onu.slot}/${onu.port}/${onu.onu_id}`,  // frame/slot/port/onuId
                        `${onu.slot}/${onu.port}/${onu.onu_id}`,    // slot/port/onuId (if F/S/P omits frame)
                        onu.onu_id                                   // just onuId as fallback
                    ];
                    const statusInfo = keys.reduce((found, k) => found || statusMap[k], null);
                    if (statusInfo) {
                        const newStatus = statusInfo.status?.toLowerCase() || 'offline';
                        const result = await this.pool.query(`
                            UPDATE huawei_onus 
                            SET status = $1, updated_at = CURRENT_TIMESTAMP
                            WHERE id = $2 AND (status IS NULL OR status != $1)
                            RETURNING id
                        `, [newStatus, onu.id]);
                        if (result.rowCount > 0) updatedCount++;
                    }
                }
                
                if (updatedCount > 0) {
                    console.log(`[Discovery] CLI updated ${updatedCount} ONU statuses on ${olt.name}`);
                }
            } catch (cmdError) {
                console.log(`[Discovery] CLI ont info error on ${olt.name}: ${cmdError.message}`);
            }
        } catch (error) {
            console.log(`[Discovery] CLI status check error on ${olt.name}: ${error.message}`);
        }
    }

    parseOntInfoOutput(output) {
        const statusMap = {};
        const lines = output.split('\n');
        
        // Debug: log sample lines containing actual data (not headers/separators)
        const dataLines = lines.filter(l => l.match(/\d+\/\d+\/\d+/) && !l.includes('F/S/P'));
        if (dataLines.length > 0) {
            console.log(`[Discovery] Sample data lines from ONT info: "${dataLines.slice(0, 3).join(' | ')}"`);
        } else {
            console.log(`[Discovery] ONT info has ${lines.length} lines but no data rows found. First 500 chars: ${output.substring(0, 500).replace(/\n/g, '\\n')}`);
        }
        
        // Huawei OLT output formats vary. Common patterns:
        // Format 1: 0/2/0  1      HWTC12345678  active      online   normal   match    no
        // Format 2: 0/ 2/ 0  1    HWTC12345678  active      online   ...
        // Format 3: Columns may be fixed-width with spaces
        
        for (const line of lines) {
            // Skip header and separator lines
            if (line.includes('---') || line.includes('F/S/P') || line.trim().length === 0) {
                continue;
            }
            
            // Try multiple regex patterns for different OLT output formats
            
            // Pattern 1: Standard format - F/S/P ONT SN Control RunState ...
            // F/S/P is frame/slot/port e.g. "0/2/4" or "0/ 2/ 4"
            let match = line.match(/^\s*(\d+)\s*\/\s*(\d+)\s*\/\s*(\d+)\s+(\d+)\s+(\S+)\s+\S+\s+(online|offline|los|dying)/i);
            if (match) {
                const frame = match[1];
                const slot = match[2];
                const port = match[3];
                const onuId = parseInt(match[4]);
                const status = match[6].toLowerCase();
                // Store multiple key formats for flexible matching
                statusMap[`${frame}/${slot}/${port}/${onuId}`] = { status };
                statusMap[`${slot}/${port}/${onuId}`] = { status };
                statusMap[onuId] = { status };
                continue;
            }
            
            // Pattern 2: Fallback - any line with F/S/P followed eventually by online/offline
            match = line.match(/(\d+)\s*\/\s*(\d+)\s*\/\s*(\d+)\s+(\d+)\s+\S+.*?(online|offline|los|dying)/i);
            if (match) {
                const frame = match[1];
                const slot = match[2];
                const port = match[3];
                const onuId = parseInt(match[4]);
                const status = match[5].toLowerCase();
                statusMap[`${frame}/${slot}/${port}/${onuId}`] = { status };
                statusMap[`${slot}/${port}/${onuId}`] = { status };
                statusMap[onuId] = { status };
                continue;
            }
            
            // Pattern 3: Very flexible - just look for the status keywords in any line with numbers
            if (line.match(/\d+/) && line.match(/online|offline|los|dying/i)) {
                const statusMatch = line.match(/(online|offline|los|dying)/i);
                const numMatches = line.match(/(\d+)/g);
                if (statusMatch && numMatches && numMatches.length >= 4) {
                    // Assume format: frame slot port onuId ... status
                    const frame = numMatches[0];
                    const slot = numMatches[1];
                    const port = numMatches[2];
                    const onuId = parseInt(numMatches[3]);
                    const status = statusMatch[1].toLowerCase();
                    statusMap[`${frame}/${slot}/${port}/${onuId}`] = { status };
                    statusMap[`${slot}/${port}/${onuId}`] = { status };
                    statusMap[onuId] = { status };
                }
            }
        }
        
        console.log(`[Discovery] Parsed ${Object.keys(statusMap).length} status entries from ONT info`);
        
        // Warn if no entries parsed from large output
        if (Object.keys(statusMap).length === 0 && output.length > 1000) {
            console.log(`[Discovery] WARNING: No status entries parsed from ${output.length} bytes of output`);
        }
        
        return statusMap;
    }

    async cleanupAuthorizedOnus(olt, currentAutofindOnus) {
        try {
            // Get all pending (not authorized) ONUs for this OLT from discovery log
            const pendingResult = await this.pool.query(`
                SELECT id, serial_number FROM onu_discovery_log 
                WHERE olt_id = $1 AND authorized = false
            `, [olt.id]);

            if (pendingResult.rows.length === 0) return;

            // Create a set of current autofind serials for fast lookup
            const currentSerials = new Set(
                currentAutofindOnus.map(o => o.sn.toUpperCase().replace(/[^A-Z0-9]/g, ''))
            );

            // Find ONUs that are pending but no longer in autofind = they were authorized externally
            for (const pending of pendingResult.rows) {
                const normalizedSn = pending.serial_number.toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (!currentSerials.has(normalizedSn)) {
                    // This ONU is no longer in autofind - mark as authorized
                    await this.pool.query(`
                        UPDATE onu_discovery_log 
                        SET authorized = true, 
                            authorized_at = CURRENT_TIMESTAMP,
                            notified = true,
                            notes = COALESCE(notes, '') || ' Authorized externally'
                        WHERE id = $1
                    `, [pending.id]);
                    console.log(`[Discovery] Marked ${pending.serial_number} as authorized (no longer in autofind)`);
                }
            }
        } catch (error) {
            console.error(`[Discovery] Error cleaning up authorized ONUs:`, error.message);
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

        // Debug: log raw output length and first 500 chars
        console.log(`[Discovery] Parsing autofind output (${output.length} chars)`);
        console.log(`[Discovery] Raw output preview: ${output.substring(0, 500).replace(/\n/g, '\\n')}`);

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

    async refreshSmartOltCache() {
        const now = Date.now();
        if (now - this.smartOltCacheTime < 300000) return;
        
        try {
            const response = await axios.get(`${this.phpApiUrl}/api/smartolt-onus.php`, { timeout: 30000 });
            if (response.data && response.data.success && Array.isArray(response.data.serials)) {
                this.smartOltSerials = new Set(response.data.serials.map(s => s.toUpperCase().replace(/[^A-Z0-9]/g, '')));
                this.smartOltCacheTime = now;
                console.log(`[Discovery] Cached ${this.smartOltSerials.size} SmartOLT serial numbers`);
            }
        } catch (error) {
            console.log(`[Discovery] SmartOLT cache refresh skipped: ${error.message}`);
        }
    }

    isInSmartOlt(serial) {
        const normalized = serial.toUpperCase().replace(/[^A-Z0-9]/g, '');
        return this.smartOltSerials.has(normalized);
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
                await this.pool.query(`
                    UPDATE onu_discovery_log 
                    SET authorized = true, authorized_at = COALESCE(authorized_at, CURRENT_TIMESTAMP)
                    WHERE serial_number = $1 AND olt_id = $2
                `, [onu.sn, olt.id]);
                return;
            }

            // Check if ONU is configured in SmartOLT
            if (this.isInSmartOlt(onu.sn)) {
                console.log(`[Discovery] ${onu.sn} already in SmartOLT, skipping`);
                await this.pool.query(`
                    UPDATE onu_discovery_log 
                    SET authorized = true, authorized_at = CURRENT_TIMESTAMP, notes = 'Configured via SmartOLT'
                    WHERE serial_number = $1 AND olt_id = $2
                `, [onu.sn, olt.id]);
                return;
            }
            
            const onuTypeId = await this.matchOnuType(onu.eqid);
            
            if (onuTypeId) {
                console.log(`[Discovery] Matched ${onu.sn} (${onu.eqid}) to ONU type ID ${onuTypeId}`);
            } else if (onu.eqid) {
                console.log(`[Discovery] No match for ${onu.sn} (${onu.eqid}) - add ONU type first`);
            }
            
            await this.pool.query(`
                INSERT INTO onu_discovery_log (olt_id, serial_number, frame_slot_port, equipment_id, onu_type_id, last_seen_at, authorized)
                VALUES ($1, $2, $3, $4, $5, CURRENT_TIMESTAMP, false)
                ON CONFLICT (olt_id, serial_number) 
                DO UPDATE SET 
                    last_seen_at = CURRENT_TIMESTAMP, 
                    frame_slot_port = $3,
                    equipment_id = COALESCE($4, onu_discovery_log.equipment_id),
                    onu_type_id = COALESCE($5, onu_discovery_log.onu_type_id),
                    authorized = false,
                    notes = NULL
            `, [olt.id, onu.sn, onu.fsp, onu.eqid || null, onuTypeId]);
        } catch (error) {
            console.error(`[Discovery] Error recording discovery:`, error.message);
        }
    }

    async getProvisioningGroup() {
        try {
            const result = await this.pool.query(`
                SELECT setting_value FROM company_settings WHERE setting_key = 'wa_provisioning_group'
            `);
            const value = result.rows[0]?.setting_value;
            console.log(`[Discovery] Provisioning group query result: rows=${result.rows.length}, value="${value || 'null'}"`);
            return value || null;
        } catch (error) {
            console.error('[Discovery] Error getting provisioning group:', error.message);
            return null;
        }
    }

    async isDiscoveryNotifyEnabled() {
        try {
            const result = await this.pool.query(`
                SELECT setting_value FROM company_settings WHERE setting_key = 'onu_discovery_notify'
            `);
            return result.rows[0]?.setting_value !== '0';
        } catch (error) {
            return true;
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
            // Debug: check if there are any pending but why they're not queued
            const debugResult = await this.pool.query(`
                SELECT COUNT(*) as total, 
                       SUM(CASE WHEN notified = false AND authorized = false THEN 1 ELSE 0 END) as pending,
                       SUM(CASE WHEN authorized = true THEN 1 ELSE 0 END) as authorized
                FROM onu_discovery_log WHERE first_seen_at > NOW() - INTERVAL '1 hour'
            `);
            const stats = debugResult.rows[0];
            if (parseInt(stats.total) > 0) {
                console.log(`[Discovery] No pending notifications (last hour: ${stats.total} total, ${stats.pending} pending, ${stats.authorized} authorized)`);
            }
            return;
        }

        console.log(`[Discovery] Sending ${result.rows.length} new ONU notifications...`);

        const notifyEnabled = await this.isDiscoveryNotifyEnabled();
        if (!notifyEnabled) {
            console.log('[Discovery] Discovery notifications disabled in settings');
            for (const d of result.rows) {
                await this.markNotified(d.id, true);
            }
            return;
        }

        const provisioningGroup = await this.getProvisioningGroup();

        const discoveriesByGroup = {};
        for (const d of result.rows) {
            const groupId = d.whatsapp_group || provisioningGroup;
            if (!groupId) {
                console.log(`[Discovery] No group for ONU ${d.serial_number} (no branch group or global provisioning group)`);
                continue;
            }
            
            if (!discoveriesByGroup[groupId]) {
                discoveriesByGroup[groupId] = [];
            }
            discoveriesByGroup[groupId].push({
                id: d.id,
                olt_name: d.olt_name,
                olt_ip: d.olt_ip,
                branch_name: d.branch_name || 'Unassigned',
                branch_code: d.branch_code || '',
                serial_number: d.serial_number,
                frame_slot_port: d.frame_slot_port,
                equipment_id: d.equipment_id,
                first_seen_at: d.first_seen_at
            });
        }

        const groupIds = Object.keys(discoveriesByGroup);
        if (groupIds.length === 0) {
            console.log('[Discovery] No WhatsApp groups configured for notifications');
            return;
        }

        for (const groupId of groupIds) {
            const discoveries = discoveriesByGroup[groupId];
            console.log(`[Discovery] Sending ${discoveries.length} notifications to group: ${groupId.substring(0, 20)}...`);

            try {
                const response = await axios.post(`${this.phpApiUrl}/api/oms-notify.php`, {
                    type: 'new_onu_discovery',
                    group_id: groupId,
                    discoveries: discoveries
                });

                if (response.data && response.data.success) {
                    for (const d of discoveries) {
                        await this.markNotified(d.id, true);
                    }
                    console.log(`[Discovery] Notified group about ${discoveries.length} new ONUs`);
                } else {
                    console.error(`[Discovery] PHP API returned failure:`, JSON.stringify(response.data));
                }
            } catch (error) {
                console.error(`[Discovery] Failed to notify (will retry):`, error.message);
                if (error.response) {
                    console.error(`[Discovery] Response status: ${error.response.status}, data:`, JSON.stringify(error.response.data));
                }
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
