const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(cors({ origin: ['http://localhost:5000', 'http://127.0.0.1:5000'] }));
app.use(bodyParser.json());

const PORT = process.env.WA_PORT || 3001;
const BIND_HOST = process.env.WA_HOST || '127.0.0.1';
const SESSION_PATH = path.join(__dirname, '.wwebjs_auth');
const API_SECRET_FILE = path.join(__dirname, '.api_secret');

let API_SECRET = process.env.WA_API_SECRET || '';
if (!API_SECRET) {
    if (fs.existsSync(API_SECRET_FILE)) {
        API_SECRET = fs.readFileSync(API_SECRET_FILE, 'utf8').trim();
    } else {
        API_SECRET = crypto.randomBytes(32).toString('hex');
        fs.writeFileSync(API_SECRET_FILE, API_SECRET);
        console.log('Generated API secret saved to .api_secret');
    }
}

function authMiddleware(req, res, next) {
    const authHeader = req.headers['x-api-key'] || req.headers['authorization'];
    const providedSecret = authHeader ? authHeader.replace('Bearer ', '') : null;
    
    if (!API_SECRET || providedSecret === API_SECRET) {
        return next();
    }
    
    const localIPs = ['127.0.0.1', '::1', 'localhost', '::ffff:127.0.0.1'];
    if (localIPs.includes(req.ip) || localIPs.includes(req.connection?.remoteAddress)) {
        return next();
    }
    
    return res.status(401).json({ error: 'Unauthorized' });
}

app.use(authMiddleware);

let client = null;
let qrCodeData = null;
let connectionStatus = 'disconnected';
let clientInfo = null;

function initializeClient() {
    if (client) {
        client.destroy();
    }
    
    client = new Client({
        authStrategy: new LocalAuth({
            dataPath: SESSION_PATH
        }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        }
    });

    client.on('qr', async (qr) => {
        console.log('QR Code received');
        connectionStatus = 'qr_ready';
        qrCodeData = await qrcode.toDataURL(qr);
    });

    client.on('ready', () => {
        console.log('WhatsApp client is ready!');
        connectionStatus = 'connected';
        qrCodeData = null;
        clientInfo = client.info;
    });

    client.on('authenticated', () => {
        console.log('WhatsApp authenticated');
        connectionStatus = 'authenticated';
    });

    client.on('auth_failure', (msg) => {
        console.error('Authentication failed:', msg);
        connectionStatus = 'auth_failed';
        qrCodeData = null;
    });

    client.on('disconnected', (reason) => {
        console.log('WhatsApp disconnected:', reason);
        connectionStatus = 'disconnected';
        clientInfo = null;
        qrCodeData = null;
    });

    client.initialize();
}

app.get('/status', (req, res) => {
    res.json({
        status: connectionStatus,
        hasQR: !!qrCodeData,
        info: clientInfo ? {
            pushname: clientInfo.pushname,
            phone: clientInfo.wid?.user,
            platform: clientInfo.platform
        } : null
    });
});

app.get('/qr', (req, res) => {
    if (qrCodeData) {
        res.json({ qr: qrCodeData });
    } else if (connectionStatus === 'connected') {
        res.json({ message: 'Already connected', status: connectionStatus });
    } else {
        res.json({ message: 'QR not available yet', status: connectionStatus });
    }
});

app.post('/initialize', (req, res) => {
    console.log('Initializing WhatsApp client...');
    connectionStatus = 'initializing';
    initializeClient();
    res.json({ message: 'Initializing...', status: connectionStatus });
});

app.post('/logout', async (req, res) => {
    try {
        if (client) {
            await client.logout();
            await client.destroy();
            client = null;
        }
        if (fs.existsSync(SESSION_PATH)) {
            fs.rmSync(SESSION_PATH, { recursive: true, force: true });
        }
        connectionStatus = 'disconnected';
        clientInfo = null;
        qrCodeData = null;
        res.json({ message: 'Logged out successfully', status: connectionStatus });
    } catch (error) {
        console.error('Logout error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/send', async (req, res) => {
    const { phone, message } = req.body;
    
    if (!phone || !message) {
        return res.status(400).json({ error: 'Phone and message are required' });
    }
    
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const formattedPhone = phone.replace(/[^0-9]/g, '');
        const chatId = formattedPhone.includes('@') ? formattedPhone : `${formattedPhone}@c.us`;
        
        const result = await client.sendMessage(chatId, message);
        console.log(`Message sent to ${phone}`);
        res.json({ 
            success: true, 
            messageId: result.id._serialized,
            timestamp: result.timestamp
        });
    } catch (error) {
        console.error('Send error:', error);
        res.status(500).json({ error: error.message, success: false });
    }
});

app.post('/send-group', async (req, res) => {
    const { groupId, message } = req.body;
    
    if (!groupId || !message) {
        return res.status(400).json({ error: 'Group ID and message are required' });
    }
    
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const chatId = groupId.includes('@g.us') ? groupId : `${groupId}@g.us`;
        const result = await client.sendMessage(chatId, message);
        console.log(`Message sent to group ${groupId}`);
        res.json({ 
            success: true, 
            messageId: result.id._serialized,
            timestamp: result.timestamp
        });
    } catch (error) {
        console.error('Send group error:', error);
        res.status(500).json({ error: error.message, success: false });
    }
});

app.get('/groups', async (req, res) => {
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const chats = await client.getChats();
        const groups = chats
            .filter(chat => chat.isGroup)
            .map(group => ({
                id: group.id._serialized,
                name: group.name,
                participantsCount: group.participants?.length || 0
            }));
        res.json({ groups });
    } catch (error) {
        console.error('Get groups error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/send-bulk', async (req, res) => {
    const { phones, message, delay = 2000 } = req.body;
    
    if (!phones || !Array.isArray(phones) || !message) {
        return res.status(400).json({ error: 'Phones array and message are required' });
    }
    
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    const results = [];
    
    for (const phone of phones) {
        try {
            const formattedPhone = phone.replace(/[^0-9]/g, '');
            const chatId = `${formattedPhone}@c.us`;
            const result = await client.sendMessage(chatId, message);
            results.push({ phone, success: true, messageId: result.id._serialized });
            
            if (delay > 0) {
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        } catch (error) {
            results.push({ phone, success: false, error: error.message });
        }
    }
    
    res.json({ results, total: phones.length, sent: results.filter(r => r.success).length });
});

if (fs.existsSync(SESSION_PATH)) {
    console.log('Session found, auto-initializing...');
    initializeClient();
}

app.listen(PORT, BIND_HOST, () => {
    console.log(`WhatsApp service running on ${BIND_HOST}:${PORT}`);
    console.log('API Secret:', API_SECRET ? 'Configured' : 'Not set (local-only access)');
});
