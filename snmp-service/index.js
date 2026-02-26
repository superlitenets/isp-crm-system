const express = require('express');
const SNMPPollingWorker = require('./SNMPPollingWorker');

process.env.TZ = process.env.TZ || 'Africa/Nairobi';

const app = express();
app.use(express.json());

const snmpWorker = new SNMPPollingWorker();

app.get('/health', (req, res) => {
    res.json({ status: 'ok', service: 'snmp-polling' });
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

const PORT = process.env.SNMP_SERVICE_PORT || 3003;
const SNMP_INTERVAL = parseInt(process.env.SNMP_POLL_INTERVAL) || 300;

const server = app.listen(PORT, '0.0.0.0', () => {
    console.log(`SNMP Polling Service running on port ${PORT}`);
    snmpWorker.start(SNMP_INTERVAL);
    console.log(`[SNMP] Background polling started (every ${SNMP_INTERVAL}s)`);
});
server.timeout = 300000;
server.keepAliveTimeout = 300000;
server.headersTimeout = 310000;

process.on('SIGTERM', async () => {
    console.log('Shutting down SNMP Polling Service...');
    snmpWorker.stop();
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('Shutting down SNMP Polling Service...');
    snmpWorker.stop();
    process.exit(0);
});
