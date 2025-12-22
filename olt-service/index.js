const express = require('express');
const OLTSessionManager = require('./OLTSessionManager');

const app = express();
app.use(express.json());

const sessionManager = new OLTSessionManager();

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

const PORT = process.env.OLT_SERVICE_PORT || 3001;
app.listen(PORT, '0.0.0.0', () => {
    console.log(`OLT Session Manager running on port ${PORT}`);
});

process.on('SIGTERM', async () => {
    console.log('Shutting down OLT Session Manager...');
    await sessionManager.disconnectAll();
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('Shutting down OLT Session Manager...');
    await sessionManager.disconnectAll();
    process.exit(0);
});
