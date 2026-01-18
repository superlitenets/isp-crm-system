const express = require('express');
const OLTSessionManager = require('./OLTSessionManager');
const DiscoveryWorker = require('./DiscoveryWorker');
const SNMPPollingWorker = require('./SNMPPollingWorker');

const app = express();
app.use(express.json());

const sessionManager = new OLTSessionManager();
const discoveryWorker = new DiscoveryWorker(sessionManager);
const snmpWorker = new SNMPPollingWorker();

app.get('/health', (req, res) => {
    res.json({ status: 'ok', sessions: sessionManager.getSessionCount() });
});

app.get('/sessions', (req, res) => {
    res.json(sessionManager.getAllSessionStatus());
});

app.post('/connect', async (req, res) => {
    try {
        const { oltId, host, port, username, password, protocol, sshPort } = req.body;
        if (!oltId || !host || !username || !password) {
            return res.status(400).json({ success: false, error: 'Missing required fields' });
        }
        await sessionManager.connect(oltId, { 
            host, 
            port: port || 23, 
            username, 
            password,
            protocol: protocol || 'telnet',  // 'telnet' or 'ssh'
            sshPort: sshPort || 22
        });
        res.json({ success: true, message: `Connected to OLT ${oltId} via ${protocol || 'telnet'}` });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/disconnect', async (req, res) => {
    try {
        const { oltId } = req.body;
        if (!oltId) {
            return res.status(400).json({ success: false, error: 'Missing oltId' });
        }
        await sessionManager.disconnect(oltId);
        res.json({ success: true, message: `Disconnected from OLT ${oltId}` });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/execute', async (req, res) => {
    try {
        const { oltId, command, timeout, expectPrompt } = req.body;
        if (!oltId || !command) {
            return res.status(400).json({ success: false, error: 'Missing oltId or command' });
        }
        const result = await sessionManager.execute(oltId, command, { timeout, expectPrompt });
        res.json({ success: true, output: result });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/execute-async', async (req, res) => {
    try {
        const { oltId, command, timeout } = req.body;
        console.log(`[Async] Received command for OLT ${oltId}: ${command.substring(0, 80)}...`);
        if (!oltId || !command) {
            return res.status(400).json({ success: false, error: 'Missing oltId or command' });
        }
        sessionManager.execute(oltId, command, { timeout }).then(result => {
            console.log(`[Async] Command completed for OLT ${oltId}: ${command.substring(0, 50)}...`);
            console.log(`[Async] Result: ${(result || '').substring(0, 200)}`);
        }).catch(err => {
            console.log(`[Async] Command failed for OLT ${oltId}: ${err.message}`);
        });
        res.json({ success: true, message: 'Command queued for execution' });
    } catch (error) {
        console.log(`[Async] Error: ${error.message}`);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/execute-batch', async (req, res) => {
    try {
        const { oltId, commands, timeout } = req.body;
        if (!oltId || !commands || !Array.isArray(commands)) {
            return res.status(400).json({ success: false, error: 'Missing oltId or commands array' });
        }
        const results = await sessionManager.executeBatch(oltId, commands, { timeout });
        res.json({ success: true, outputs: results });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/status/:oltId', (req, res) => {
    const status = sessionManager.getSessionStatus(req.params.oltId);
    res.json(status);
});

app.post('/discovery/run', async (req, res) => {
    try {
        await discoveryWorker.runDiscovery();
        res.json({ success: true, message: 'Discovery completed' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/discovery/status', (req, res) => {
    res.json({ 
        isRunning: discoveryWorker.isRunning,
        cronActive: discoveryWorker.cronJob !== null
    });
});

app.post('/snmp/poll', async (req, res) => {
    try {
        await snmpWorker.runPolling();
        res.json({ success: true, message: 'SNMP polling completed' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/snmp/status', async (req, res) => {
    try {
        const status = await snmpWorker.getPollingStatus();
        res.json({ 
            isRunning: snmpWorker.isRunning,
            olts: status 
        });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// Background ONU refresh endpoint (fire-and-forget from PHP)
app.post('/refresh-onu', async (req, res) => {
    const { oltId, onuDbId } = req.body;
    res.json({ success: true, message: 'Refresh queued' }); // Respond immediately
    
    // Run refresh in background
    if (oltId && onuDbId) {
        refreshSingleONU(oltId, onuDbId).catch(err => {
            console.log(`[RefreshONU] Error: ${err.message}`);
        });
    }
});

// Background refresh function for single ONU
async function refreshSingleONU(oltId, onuDbId) {
    const { Pool } = require('pg');
    const pool = new Pool({ connectionString: process.env.DATABASE_URL });
    
    try {
        // Get ONU details from database
        const onuResult = await pool.query(
            'SELECT * FROM huawei_onus WHERE id = $1', [onuDbId]
        );
        if (onuResult.rows.length === 0) return;
        
        const onu = onuResult.rows[0];
        const { frame, slot, port, onu_id } = onu;
        
        if (slot === null || port === null || onu_id === null) return;
        
        // Execute CLI commands via session manager
        const interfaceCmd = `interface gpon ${frame}/${slot}`;
        await sessionManager.execute(oltId.toString(), interfaceCmd, { timeout: 10000 });
        
        // Get optical info
        const opticalCmd = `display ont optical-info ${port} ${onu_id}`;
        const opticalResult = await sessionManager.execute(oltId.toString(), opticalCmd, { timeout: 15000 });
        
        // Parse optical data
        let rxPower = null, txPower = null;
        const cleanOutput = (opticalResult || '').replace(/\x1b\[[0-9;]*[A-Za-z]/g, '');
        
        const rxMatch = cleanOutput.match(/Rx\s+optical\s+power\s*\([^)]*\)\s*:\s*([-\d.]+)/i);
        if (rxMatch) rxPower = parseFloat(rxMatch[1]);
        
        const txMatch = cleanOutput.match(/Tx\s+optical\s+power\s*\([^)]*\)\s*:\s*([-\d.]+)/i);
        if (txMatch) txPower = parseFloat(txMatch[1]);
        
        // Get status
        const infoCmd = `display ont info ${port} ${onu_id}`;
        const infoResult = await sessionManager.execute(oltId.toString(), infoCmd, { timeout: 15000 });
        const cleanInfo = (infoResult || '').replace(/\x1b\[[0-9;]*[A-Za-z]/g, '');
        
        let status = 'offline';
        const statusMatch = cleanInfo.match(/Run\s+state\s*:\s*(\w+)/i);
        if (statusMatch) {
            const state = statusMatch[1].toLowerCase();
            if (state === 'online') status = 'online';
            else if (state.includes('los')) status = 'los';
        }
        if (status === 'offline' && rxPower !== null && rxPower > -35) {
            status = 'online';
        }
        
        // Exit interface
        await sessionManager.execute(oltId.toString(), 'quit', { timeout: 5000 });
        
        // Update database
        await pool.query(`
            UPDATE huawei_onus 
            SET rx_power = COALESCE($1, rx_power),
                tx_power = COALESCE($2, tx_power),
                status = COALESCE($3, status),
                updated_at = NOW()
            WHERE id = $4
        `, [rxPower, txPower, status, onuDbId]);
        
        console.log(`[RefreshONU] Updated ONU ${onu.sn}: status=${status}, rx=${rxPower}`);
    } catch (err) {
        console.log(`[RefreshONU] Error refreshing ONU ${onuDbId}: ${err.message}`);
    } finally {
        await pool.end();
    }
}

const { exec } = require('child_process');
const util = require('util');
const execPromise = util.promisify(exec);

app.post('/ping', async (req, res) => {
    try {
        const { ip, count = 3, timeout = 2 } = req.body;
        
        if (!ip || !/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
            return res.status(400).json({ success: false, error: 'Invalid IP address' });
        }
        
        const { stdout, stderr } = await execPromise(`ping -c ${count} -W ${timeout} ${ip} 2>&1`).catch(e => ({ stdout: e.stdout || '', stderr: e.stderr || e.message }));
        const output = stdout || stderr;
        
        const result = {
            success: output.includes(' 0% packet loss') || output.includes('bytes from'),
            ip,
            method: 'ping',
            output,
            packets_sent: count,
            packets_received: 0,
            latency_avg: null,
            latency_min: null,
            latency_max: null
        };
        
        const packetsMatch = output.match(/(\d+) packets transmitted, (\d+) (?:packets )?received/);
        if (packetsMatch) {
            result.packets_received = parseInt(packetsMatch[2]);
            result.success = result.packets_received > 0;
        }
        
        const latencyMatch = output.match(/min\/avg\/max.*= ([\d.]+)\/([\d.]+)\/([\d.]+)/);
        if (latencyMatch) {
            result.latency_min = parseFloat(latencyMatch[1]);
            result.latency_avg = parseFloat(latencyMatch[2]);
            result.latency_max = parseFloat(latencyMatch[3]);
        }
        
        res.json(result);
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/ping-batch', async (req, res) => {
    try {
        const { targets, count = 2, timeout = 2 } = req.body;
        
        if (!targets || !Array.isArray(targets)) {
            return res.status(400).json({ success: false, error: 'Missing targets array' });
        }
        
        const results = await Promise.all(targets.map(async (target) => {
            const ip = target.ip;
            if (!ip || !/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
                return { ...target, success: false, error: 'Invalid IP' };
            }
            
            try {
                const { stdout } = await execPromise(`ping -c ${count} -W ${timeout} ${ip} 2>&1`).catch(e => ({ stdout: e.stdout || '' }));
                
                const packetsMatch = stdout.match(/(\d+) packets transmitted, (\d+) (?:packets )?received/);
                const latencyMatch = stdout.match(/min\/avg\/max.*= ([\d.]+)\/([\d.]+)\/([\d.]+)/);
                
                return {
                    ...target,
                    success: packetsMatch ? parseInt(packetsMatch[2]) > 0 : false,
                    latency_avg: latencyMatch ? parseFloat(latencyMatch[2]) : null
                };
            } catch (e) {
                return { ...target, success: false, error: e.message };
            }
        }));
        
        res.json({ success: true, results });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// WireGuard config apply endpoint
app.post('/wireguard/apply', async (req, res) => {
    try {
        const { config, subnets } = req.body;
        
        if (!config) {
            return res.status(400).json({ success: false, error: 'Missing config' });
        }
        
        const fs = require('fs');
        const configPath = '/tmp/wg0_apply.conf';
        const containerName = 'isp_crm_wireguard';
        const results = { configApplied: false, routesAdded: [], routesRemoved: [], errors: [] };
        
        // Write config to temp file
        fs.writeFileSync(configPath, config);
        
        // Try docker cp to WireGuard container
        try {
            await execPromise(`docker cp ${configPath} ${containerName}:/config/wg_confs/wg0.conf 2>&1`);
            
            // Try to apply via wg syncconf
            try {
                await execPromise(`docker exec ${containerName} wg syncconf wg0 /config/wg_confs/wg0.conf 2>&1`);
                results.configApplied = true;
                results.configMessage = 'Config applied via wg syncconf';
            } catch (syncErr) {
                // Fallback to container restart
                await execPromise(`docker restart ${containerName} 2>&1`);
                await new Promise(r => setTimeout(r, 3000)); // Wait for container
                results.configApplied = true;
                results.configMessage = 'Config applied via container restart';
            }
        } catch (cpErr) {
            results.errors.push('Failed to copy config: ' + cpErr.message);
        } finally {
            try { fs.unlinkSync(configPath); } catch (e) {}
        }
        
        // Sync routes if subnets provided
        // NOTE: wg0 is on the HOST, not in a container. OLT service runs with network_mode: host
        // so we can add routes directly without docker exec
        if (subnets && Array.isArray(subnets)) {
            try {
                // Get current routes on HOST (wg0 is a host interface)
                const { stdout } = await execPromise(`ip route show dev wg0 2>/dev/null`).catch(() => ({ stdout: '' }));
                const currentRoutes = [];
                stdout.split('\n').forEach(line => {
                    const match = line.match(/^([\d.]+\/\d+)/);
                    if (match) currentRoutes.push(match[1]);
                });
                
                // Validate and add missing routes on HOST
                for (const subnet of subnets) {
                    if (!/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/.test(subnet)) continue;
                    if (!currentRoutes.includes(subnet)) {
                        try {
                            await execPromise(`ip route add ${subnet} dev wg0 2>&1`);
                            results.routesAdded.push(subnet);
                        } catch (e) {
                            if (!e.message.includes('File exists')) {
                                results.errors.push(`Route ${subnet}: ${e.message}`);
                            }
                        }
                    }
                }
                
                // Remove stale routes (skip tunnel IPs)
                for (const route of currentRoutes) {
                    if (route.startsWith('10.200.0.')) continue; // Skip tunnel network
                    if (!subnets.includes(route)) {
                        try {
                            await execPromise(`ip route del ${route} 2>&1`);
                            results.routesRemoved.push(route);
                        } catch (e) {
                            if (!e.message.includes('No such process')) {
                                results.errors.push(`Remove ${route}: ${e.message}`);
                            }
                        }
                    }
                }
            } catch (routeErr) {
                results.errors.push('Route sync error: ' + routeErr.message);
            }
        }
        
        res.json({ 
            success: results.configApplied && results.errors.length === 0,
            message: results.configMessage,
            routesAdded: results.routesAdded,
            routesRemoved: results.routesRemoved,
            errors: results.errors
        });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// RADIUS CoA/Disconnect endpoint - sends packets via VPN tunnel
const dgram = require('dgram');
const crypto = require('crypto');

// RADIUS codes
const RADIUS_DISCONNECT_REQUEST = 40;
const RADIUS_DISCONNECT_ACK = 41;
const RADIUS_DISCONNECT_NAK = 42;
const RADIUS_COA_REQUEST = 43;
const RADIUS_COA_ACK = 44;
const RADIUS_COA_NAK = 45;

// RADIUS attributes
const ATTR_USER_NAME = 1;
const ATTR_ACCT_SESSION_ID = 44;
const ATTR_VENDOR_SPECIFIC = 26;
const VENDOR_MIKROTIK = 14988;
const MIKROTIK_RATE_LIMIT = 8;

function encodeRadiusAttribute(type, value) {
    const valBuf = Buffer.from(value, 'utf8');
    const attrBuf = Buffer.alloc(2 + valBuf.length);
    attrBuf.writeUInt8(type, 0);
    attrBuf.writeUInt8(2 + valBuf.length, 1);
    valBuf.copy(attrBuf, 2);
    return attrBuf;
}

function encodeMikrotikRateLimit(rateLimit) {
    const valBuf = Buffer.from(rateLimit, 'utf8');
    const vendorData = Buffer.alloc(2 + valBuf.length);
    vendorData.writeUInt8(MIKROTIK_RATE_LIMIT, 0);
    vendorData.writeUInt8(2 + valBuf.length, 1);
    valBuf.copy(vendorData, 2);
    
    const vsaBuf = Buffer.alloc(6 + vendorData.length);
    vsaBuf.writeUInt8(ATTR_VENDOR_SPECIFIC, 0);
    vsaBuf.writeUInt8(6 + vendorData.length, 1);
    vsaBuf.writeUInt32BE(VENDOR_MIKROTIK, 2);
    vendorData.copy(vsaBuf, 6);
    return vsaBuf;
}

function buildRadiusPacket(code, identifier, attributes, secret) {
    const attrBuffers = [];
    
    if (attributes.username) {
        attrBuffers.push(encodeRadiusAttribute(ATTR_USER_NAME, attributes.username));
    }
    if (attributes.sessionId) {
        attrBuffers.push(encodeRadiusAttribute(ATTR_ACCT_SESSION_ID, attributes.sessionId));
    }
    if (attributes.rateLimit) {
        attrBuffers.push(encodeMikrotikRateLimit(attributes.rateLimit));
    }
    
    const attrData = Buffer.concat(attrBuffers);
    const length = 20 + attrData.length;
    
    // Build pre-packet with zero authenticator
    const prePacket = Buffer.alloc(20 + attrData.length);
    prePacket.writeUInt8(code, 0);
    prePacket.writeUInt8(identifier, 1);
    prePacket.writeUInt16BE(length, 2);
    // bytes 4-19 are zeros (authenticator placeholder)
    attrData.copy(prePacket, 20);
    
    // Calculate authenticator = MD5(Code + ID + Length + 16 zeros + Attributes + Secret)
    const hash = crypto.createHash('md5');
    hash.update(prePacket);
    hash.update(secret);
    const authenticator = hash.digest();
    
    // Build final packet
    const packet = Buffer.alloc(length);
    prePacket.copy(packet);
    authenticator.copy(packet, 4);
    
    return packet;
}

function sendRadiusPacket(nasIp, nasPort, packet, expectedAck, timeout = 5000) {
    return new Promise((resolve) => {
        const socket = dgram.createSocket('udp4');
        let responded = false;
        
        const timer = setTimeout(() => {
            if (!responded) {
                responded = true;
                socket.close();
                resolve({ success: false, error: 'Timeout - no response from NAS' });
            }
        }, timeout);
        
        socket.on('message', (msg) => {
            if (responded) return;
            responded = true;
            clearTimeout(timer);
            socket.close();
            
            if (msg.length < 4) {
                resolve({ success: false, error: 'Invalid response' });
                return;
            }
            
            const code = msg.readUInt8(0);
            const codeNames = {
                [RADIUS_DISCONNECT_ACK]: 'Disconnect-ACK',
                [RADIUS_DISCONNECT_NAK]: 'Disconnect-NAK',
                [RADIUS_COA_ACK]: 'CoA-ACK',
                [RADIUS_COA_NAK]: 'CoA-NAK'
            };
            const codeName = codeNames[code] || `Unknown-${code}`;
            
            if (code === expectedAck) {
                resolve({ success: true, response: codeName, code });
            } else {
                resolve({ success: false, error: `Received ${codeName}`, code });
            }
        });
        
        socket.on('error', (err) => {
            if (!responded) {
                responded = true;
                clearTimeout(timer);
                socket.close();
                resolve({ success: false, error: err.message });
            }
        });
        
        socket.send(packet, 0, packet.length, nasPort, nasIp, (err) => {
            if (err && !responded) {
                responded = true;
                clearTimeout(timer);
                socket.close();
                resolve({ success: false, error: `Send failed: ${err.message}` });
            }
        });
    });
}

// Real-time event system for session updates
const EventEmitter = require('events');
const sessionEvents = new EventEmitter();
const sseClients = new Set();

// SSE endpoint for real-time session updates
app.get('/radius/events', (req, res) => {
    res.setHeader('Content-Type', 'text/event-stream');
    res.setHeader('Cache-Control', 'no-cache');
    res.setHeader('Connection', 'keep-alive');
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.flushHeaders();
    
    sseClients.add(res);
    console.log(`[SSE] Client connected (${sseClients.size} active)`);
    
    // Send heartbeat every 30s to keep connection alive
    const heartbeat = setInterval(() => {
        res.write(': heartbeat\n\n');
    }, 30000);
    
    req.on('close', () => {
        clearInterval(heartbeat);
        sseClients.delete(res);
        console.log(`[SSE] Client disconnected (${sseClients.size} active)`);
    });
});

function broadcastEvent(type, data) {
    const event = `event: ${type}\ndata: ${JSON.stringify(data)}\n\n`;
    sseClients.forEach(client => {
        try { client.write(event); } catch (e) {}
    });
}

// RADIUS Disconnect endpoint (via VPN)
app.post('/radius/disconnect', async (req, res) => {
    try {
        const { nasIp, nasPort = 3799, secret, username, sessionId, subscriptionId } = req.body;
        
        if (!nasIp || !secret) {
            return res.status(400).json({ success: false, error: 'Missing nasIp or secret' });
        }
        if (!username && !sessionId) {
            return res.status(400).json({ success: false, error: 'Need username or sessionId' });
        }
        
        const identifier = Math.floor(Math.random() * 256);
        const packet = buildRadiusPacket(RADIUS_DISCONNECT_REQUEST, identifier, { username, sessionId }, secret);
        const result = await sendRadiusPacket(nasIp, nasPort, packet, RADIUS_DISCONNECT_ACK);
        
        console.log(`[RADIUS] Disconnect ${username || sessionId} @ ${nasIp}: ${result.success ? 'OK' : result.error}`);
        
        // Broadcast real-time event
        broadcastEvent('session_disconnect', {
            username,
            sessionId,
            subscriptionId,
            nasIp,
            success: result.success,
            response: result.response || result.error,
            timestamp: new Date().toISOString()
        });
        
        res.json(result);
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// RADIUS CoA (speed update) endpoint (via VPN)
app.post('/radius/coa', async (req, res) => {
    try {
        const { nasIp, nasPort = 3799, secret, username, sessionId, rateLimit, subscriptionId } = req.body;
        
        if (!nasIp || !secret) {
            return res.status(400).json({ success: false, error: 'Missing nasIp or secret' });
        }
        if (!username && !sessionId) {
            return res.status(400).json({ success: false, error: 'Need username or sessionId' });
        }
        
        const identifier = Math.floor(Math.random() * 256);
        const packet = buildRadiusPacket(RADIUS_COA_REQUEST, identifier, { username, sessionId, rateLimit }, secret);
        const result = await sendRadiusPacket(nasIp, nasPort, packet, RADIUS_COA_ACK);
        
        console.log(`[RADIUS] CoA ${username || sessionId} @ ${nasIp} rate=${rateLimit}: ${result.success ? 'OK' : result.error}`);
        
        // Broadcast real-time event
        broadcastEvent('speed_update', {
            username,
            sessionId,
            subscriptionId,
            nasIp,
            rateLimit,
            success: result.success,
            response: result.response || result.error,
            timestamp: new Date().toISOString()
        });
        
        res.json({ ...result, rateLimit });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

const PORT = process.env.OLT_SERVICE_PORT || 3002;
const DISCOVERY_INTERVAL = process.env.DISCOVERY_INTERVAL || '0 * * * * *'; // CLI autofind every 60s (for new ONUs only)
const SNMP_INTERVAL = parseInt(process.env.SNMP_POLL_INTERVAL) || 300; // SNMP polling every 5 minutes (reduced to prevent slowdowns)

app.listen(PORT, '0.0.0.0', () => {
    console.log(`OLT Session Manager running on port ${PORT}`);
    discoveryWorker.start(DISCOVERY_INTERVAL);
    snmpWorker.start(SNMP_INTERVAL);
    console.log(`[Discovery] CLI autofind started (every 60s - for new unconfigured ONUs)`);
    console.log(`[SNMP] Background polling started (every ${SNMP_INTERVAL}s - for status updates)`);
});

process.on('SIGTERM', async () => {
    console.log('Shutting down OLT Session Manager...');
    discoveryWorker.stop();
    snmpWorker.stop();
    await sessionManager.disconnectAll();
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('Shutting down OLT Session Manager...');
    discoveryWorker.stop();
    snmpWorker.stop();
    await sessionManager.disconnectAll();
    process.exit(0);
});
