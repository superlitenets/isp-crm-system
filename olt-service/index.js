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
        const { oltId, host, port, username, password } = req.body;
        if (!oltId || !host || !username || !password) {
            return res.status(400).json({ success: false, error: 'Missing required fields' });
        }
        await sessionManager.connect(oltId, { host, port: port || 23, username, password });
        res.json({ success: true, message: `Connected to OLT ${oltId}` });
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

const PORT = process.env.OLT_SERVICE_PORT || 3001;
const DISCOVERY_INTERVAL = process.env.DISCOVERY_INTERVAL || '*/30 * * * * *';
const SNMP_INTERVAL = parseInt(process.env.SNMP_POLL_INTERVAL) || 30;

app.listen(PORT, '0.0.0.0', () => {
    console.log(`OLT Session Manager running on port ${PORT}`);
    discoveryWorker.start(DISCOVERY_INTERVAL);
    snmpWorker.start(SNMP_INTERVAL);
    console.log(`[Discovery] Auto-discovery started (${DISCOVERY_INTERVAL})`);
    console.log(`[SNMP] Background polling started (every ${SNMP_INTERVAL}s)`);
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
