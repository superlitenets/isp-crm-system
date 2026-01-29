const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(cors({ origin: true }));
app.use(bodyParser.json());

const PORT = process.env.WA_PORT || 3001;
const BIND_HOST = process.env.WA_HOST || (process.env.DOCKER_ENV ? '0.0.0.0' : '127.0.0.1');
const SESSION_PATH = path.join(__dirname, '.wwebjs_auth');

function cleanupChromeLocks() {
    const sessionDir = path.join(SESSION_PATH, 'session');
    const chromiumDirs = [
        path.join(sessionDir, 'Default'),
        sessionDir
    ];
    
    for (const dir of chromiumDirs) {
        if (!fs.existsSync(dir)) continue;
        
        const lockFiles = ['SingletonLock', 'SingletonSocket', 'SingletonCookie'];
        for (const lockFile of lockFiles) {
            const lockPath = path.join(dir, lockFile);
            if (fs.existsSync(lockPath)) {
                try {
                    fs.unlinkSync(lockPath);
                    console.log(`Removed stale lock file: ${lockPath}`);
                } catch (e) {
                    console.warn(`Could not remove lock file ${lockPath}:`, e.message);
                }
            }
        }
    }
    
    try {
        const walkDir = (dir, depth = 0) => {
            if (depth > 3 || !fs.existsSync(dir)) return;
            const entries = fs.readdirSync(dir, { withFileTypes: true });
            for (const entry of entries) {
                if (entry.name === 'SingletonLock' || entry.name === 'SingletonSocket') {
                    const lockPath = path.join(dir, entry.name);
                    try {
                        fs.unlinkSync(lockPath);
                        console.log(`Removed nested lock file: ${lockPath}`);
                    } catch (e) {}
                } else if (entry.isDirectory()) {
                    walkDir(path.join(dir, entry.name), depth + 1);
                }
            }
        };
        if (fs.existsSync(SESSION_PATH)) {
            walkDir(SESSION_PATH);
        }
    } catch (e) {
        console.warn('Error walking session directory:', e.message);
    }
}

cleanupChromeLocks();
const API_SECRET_DIR = path.join(__dirname, '.api_secret_dir');
const API_SECRET_FILE = fs.existsSync(API_SECRET_DIR) && fs.statSync(API_SECRET_DIR).isDirectory() 
    ? path.join(API_SECRET_DIR, 'secret') 
    : path.join(__dirname, '.api_secret');

let API_SECRET = process.env.WA_API_SECRET || '';
if (!API_SECRET) {
    if (fs.existsSync(API_SECRET_FILE)) {
        API_SECRET = fs.readFileSync(API_SECRET_FILE, 'utf8').trim();
    } else {
        API_SECRET = crypto.randomBytes(32).toString('hex');
        if (fs.existsSync(API_SECRET_DIR) && fs.statSync(API_SECRET_DIR).isDirectory()) {
            fs.writeFileSync(path.join(API_SECRET_DIR, 'secret'), API_SECRET);
        } else {
            fs.writeFileSync(API_SECRET_FILE, API_SECRET);
        }
        console.log('Generated API secret saved');
    }
}

function authMiddleware(req, res, next) {
    const authHeader = req.headers['x-api-key'] || req.headers['authorization'];
    const providedSecret = authHeader ? authHeader.replace('Bearer ', '') : null;
    
    if (!API_SECRET || providedSecret === API_SECRET) {
        return next();
    }
    
    const localIPs = ['127.0.0.1', '::1', 'localhost', '::ffff:127.0.0.1'];
    const clientIP = req.ip || req.connection?.remoteAddress || '';
    
    if (localIPs.includes(clientIP)) {
        return next();
    }
    
    if (process.env.DOCKER_ENV && (clientIP.startsWith('172.') || clientIP.startsWith('::ffff:172.'))) {
        return next();
    }
    
    return res.status(401).json({ error: 'Unauthorized' });
}

app.use(authMiddleware);

let client = null;
let qrCodeData = null;
let qrCodeString = null;
let connectionStatus = 'disconnected';
let clientInfo = null;
let readyTimeout = null;
let authTime = null;

function initializeClient() {
    if (client) {
        client.destroy();
    }
    
    cleanupChromeLocks();
    
    const chromiumPath = process.env.PUPPETEER_EXECUTABLE_PATH || '/nix/store/qa9cnw4v5xkxyip6mb9kxqfq1z4x2dx1-chromium-138.0.7204.100/bin/chromium';
    console.log('Using Chromium at:', chromiumPath);
    
    client = new Client({
        authStrategy: new LocalAuth({
            dataPath: SESSION_PATH
        }),
        puppeteer: {
            headless: true,
            executablePath: chromiumPath,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu',
                '--single-process'
            ]
        }
    });

    client.on('qr', async (qr) => {
        console.log('QR Code received');
        connectionStatus = 'qr_ready';
        qrCodeString = qr;
        qrCodeData = await qrcode.toDataURL(qr);
    });

    client.on('ready', async () => {
        console.log('WhatsApp client is ready!');
        connectionStatus = 'connected';
        qrCodeData = null;
        qrCodeString = null;
        clientInfo = client.info;
        
        // Clear the ready timeout since we're now connected
        if (readyTimeout) {
            clearTimeout(readyTimeout);
            readyTimeout = null;
        }
        authTime = null;
        
        // Monkey-patch sendSeen to fix markedUnread error (WhatsApp Web API change)
        try {
            await client.pupPage.evaluate(() => {
                if (window.WWebJS && window.WWebJS.sendSeen) {
                    window.WWebJS.sendSeen = async () => true;
                    console.log('Patched WWebJS.sendSeen');
                }
            });
            console.log('Applied sendSeen patch for group messages');
        } catch (e) {
            console.warn('Could not patch sendSeen:', e.message);
        }
    });

    client.on('authenticated', () => {
        console.log('WhatsApp authenticated');
        connectionStatus = 'authenticated';
        authTime = Date.now();
        
        // Set a timeout - if ready doesn't fire within 90 seconds, reinitialize
        if (readyTimeout) {
            clearTimeout(readyTimeout);
        }
        readyTimeout = setTimeout(() => {
            if (connectionStatus === 'authenticated') {
                console.log('WARNING: Authenticated but ready event never fired after 90s. Reinitializing...');
                // Clear session and reinitialize
                const sessionDir = path.join(SESSION_PATH, 'session');
                if (fs.existsSync(sessionDir)) {
                    try {
                        fs.rmSync(sessionDir, { recursive: true, force: true });
                        console.log('Cleared stale session directory');
                    } catch (e) {
                        console.error('Failed to clear session:', e.message);
                    }
                }
                setTimeout(() => {
                    connectionStatus = 'disconnected';
                    initializeClient();
                    client.initialize();
                }, 2000);
            }
        }, 90000);
    });

    client.on('auth_failure', (msg) => {
        console.error('Authentication failed:', msg);
        connectionStatus = 'auth_failed';
        qrCodeData = null;
        qrCodeString = null;
    });

    client.on('disconnected', (reason) => {
        console.log('WhatsApp disconnected:', reason);
        connectionStatus = 'disconnected';
        clientInfo = null;
        qrCodeData = null;
        qrCodeString = null;
    });

    // Listen for incoming messages
    client.on('message', async (msg) => {
        console.log('Incoming message from:', msg.from, 'Body:', msg.body?.substring(0, 50));
        
        // Store message in database via webhook
        try {
            const messageData = {
                event: 'message_received',
                from: msg.from,
                to: msg.to,
                body: msg.body,
                type: msg.type,
                timestamp: msg.timestamp,
                messageId: msg.id._serialized,
                isGroup: msg.from.endsWith('@g.us'),
                senderName: msg._data?.notifyName || null,
                hasMedia: msg.hasMedia,
                mediaData: null,
                mimetype: null,
                filename: null
            };
            
            // Download media if present
            if (msg.hasMedia) {
                try {
                    const media = await msg.downloadMedia();
                    if (media) {
                        messageData.mediaData = media.data; // base64
                        messageData.mimetype = media.mimetype;
                        messageData.filename = media.filename || null;
                        console.log('Media downloaded:', media.mimetype, media.filename || 'no filename');
                    }
                } catch (mediaErr) {
                    console.error('Failed to download media:', mediaErr.message);
                }
            }
            
            // Store in messages array for polling
            recentMessages.push(messageData);
            if (recentMessages.length > 100) recentMessages.shift();
            
            // Emit to connected clients
            messageCallbacks.forEach(cb => cb(messageData));
        } catch (error) {
            console.error('Error processing incoming message:', error);
        }
    });

    // Listen for message acknowledgments (delivery, read receipts)
    client.on('message_ack', (msg, ack) => {
        const ackStatus = {
            '-1': 'error',
            '0': 'pending',
            '1': 'sent',
            '2': 'delivered',
            '3': 'read',
            '4': 'played'
        };
        console.log('Message ack:', msg.id._serialized, ackStatus[ack] || ack);
        
        // Notify callbacks
        messageCallbacks.forEach(cb => cb({
            event: 'message_ack',
            messageId: msg.id._serialized,
            ack: ack,
            status: ackStatus[ack] || 'unknown'
        }));
    });

    client.initialize();
}

// Store recent messages for polling
let recentMessages = [];
let messageCallbacks = [];

app.get('/status', (req, res) => {
    const response = {
        status: connectionStatus,
        hasQR: !!qrCodeData,
        info: clientInfo ? {
            pushname: clientInfo.pushname,
            phone: clientInfo.wid?.user,
            platform: clientInfo.platform
        } : null
    };
    
    // Add diagnostic info if stuck in authenticated state
    if (connectionStatus === 'authenticated' && authTime) {
        response.authenticatedSince = authTime;
        response.stuckSeconds = Math.floor((Date.now() - authTime) / 1000);
        response.message = 'Client authenticated but waiting for ready event. Will auto-reinitialize after 90s.';
    }
    
    res.json(response);
});

app.get('/qr', (req, res) => {
    if (qrCodeData) {
        res.json({ qr: qrCodeData, qrString: qrCodeString });
    } else if (connectionStatus === 'connected') {
        res.json({ message: 'Already connected', status: connectionStatus });
    } else {
        res.json({ message: 'QR not available yet', status: connectionStatus });
    }
});

app.get('/qr-terminal', async (req, res) => {
    if (qrCodeString) {
        res.set('Content-Type', 'text/plain');
        const qrt = await import('qrcode-terminal');
        let output = '';
        qrt.default.generate(qrCodeString, { small: true }, (qr) => {
            output = qr;
        });
        res.send(`WhatsApp QR Code - Scan with your phone:\n\n${output}\n\nStatus: ${connectionStatus}`);
    } else if (connectionStatus === 'connected') {
        res.set('Content-Type', 'text/plain');
        res.send(`WhatsApp is already connected!\nStatus: ${connectionStatus}`);
    } else {
        res.set('Content-Type', 'text/plain');
        res.send(`QR not available yet.\nStatus: ${connectionStatus}\n\nTry: POST /initialize to start the client`);
    }
});

app.post('/initialize', (req, res) => {
    console.log('Initializing WhatsApp client...');
    connectionStatus = 'initializing';
    initializeClient();
    res.json({ message: 'Initializing...', status: connectionStatus });
});

// Force reinitialize - clears session and starts fresh (requires new QR scan)
app.post('/force-reinit', async (req, res) => {
    console.log('Force reinitializing WhatsApp client - clearing session...');
    try {
        // Clear any pending timeouts
        if (readyTimeout) {
            clearTimeout(readyTimeout);
            readyTimeout = null;
        }
        authTime = null;
        
        // Destroy existing client
        if (client) {
            try {
                await client.destroy();
            } catch (e) {
                console.warn('Client destroy error:', e.message);
            }
            client = null;
        }
        
        // Clear session directory
        const sessionDir = path.join(SESSION_PATH, 'session');
        if (fs.existsSync(sessionDir)) {
            fs.rmSync(sessionDir, { recursive: true, force: true });
            console.log('Cleared session directory');
        }
        
        // Reset state
        connectionStatus = 'disconnected';
        clientInfo = null;
        qrCodeData = null;
        qrCodeString = null;
        
        // Reinitialize after a short delay
        setTimeout(() => {
            initializeClient();
            client.initialize();
        }, 1000);
        
        res.json({ message: 'Force reinitialization started. Scan new QR code to connect.', status: 'reinitializing' });
    } catch (error) {
        console.error('Force reinit error:', error);
        res.status(500).json({ error: error.message });
    }
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
        qrCodeString = null;
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
        
        // Use standard sendMessage (sendSeen is patched on ready)
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

// Get all chats/conversations
app.get('/chats', async (req, res) => {
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const chats = await client.getChats();
        const chatList = await Promise.all(chats.slice(0, 50).map(async chat => {
            let contactName = null;
            if (!chat.isGroup) {
                try {
                    const contact = await chat.getContact();
                    contactName = contact?.pushname || contact?.name || null;
                } catch (e) {
                    // Contact lookup may fail on newer WhatsApp versions
                }
            }
            return {
                id: chat.id._serialized,
                name: chat.name || contactName || chat.id.user,
                isGroup: chat.isGroup,
                unreadCount: chat.unreadCount,
                lastMessageAt: chat.timestamp,
                phone: chat.id.user
            };
        }));
        res.json({ chats: chatList });
    } catch (error) {
        console.error('Get chats error:', error);
        res.status(500).json({ error: error.message });
    }
});

// Get chat history for a specific chat
app.get('/chat/:chatId/messages', async (req, res) => {
    const { chatId } = req.params;
    const { limit = 50, includeMedia = 'false' } = req.query;
    
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const chat = await client.getChatById(chatId);
        const messages = await chat.fetchMessages({ limit: parseInt(limit) });
        
        const messageList = await Promise.all(messages.map(async msg => {
            const msgData = {
                id: msg.id._serialized,
                body: msg.body,
                type: msg.type,
                fromMe: msg.fromMe,
                timestamp: msg.timestamp,
                senderName: msg._data?.notifyName,
                hasMedia: msg.hasMedia,
                mediaData: null,
                mimetype: null,
                filename: null
            };
            
            // Download media if requested and message has media
            if (includeMedia === 'true' && msg.hasMedia) {
                try {
                    const media = await msg.downloadMedia();
                    if (media) {
                        msgData.mediaData = media.data;
                        msgData.mimetype = media.mimetype;
                        msgData.filename = media.filename || null;
                    }
                } catch (e) {
                    console.error('Failed to download media for message:', e.message);
                }
            }
            
            return msgData;
        }));
        
        res.json({ messages: messageList, chatId });
    } catch (error) {
        console.error('Get messages error:', error);
        res.status(500).json({ error: error.message });
    }
});

// Get recent incoming messages
app.get('/messages/recent', (req, res) => {
    const { since } = req.query;
    let messages = recentMessages;
    
    if (since) {
        const sinceTimestamp = parseInt(since);
        messages = recentMessages.filter(m => m.timestamp > sinceTimestamp);
    }
    
    res.json({ messages });
});

// SSE endpoint for real-time message updates
app.get('/messages/stream', (req, res) => {
    res.setHeader('Content-Type', 'text/event-stream');
    res.setHeader('Cache-Control', 'no-cache');
    res.setHeader('Connection', 'keep-alive');
    
    const callback = (data) => {
        res.write(`data: ${JSON.stringify(data)}\n\n`);
    };
    
    messageCallbacks.push(callback);
    
    req.on('close', () => {
        const index = messageCallbacks.indexOf(callback);
        if (index > -1) {
            messageCallbacks.splice(index, 1);
        }
    });
});

// Send message to a chat and get the sent message info
app.post('/chat/:chatId/send', async (req, res) => {
    const { chatId } = req.params;
    const { message } = req.body;
    
    if (!message) {
        return res.status(400).json({ error: 'Message is required' });
    }
    
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const result = await client.sendMessage(chatId, message);
        res.json({ 
            success: true, 
            messageId: result.id._serialized,
            timestamp: result.timestamp,
            chatId
        });
    } catch (error) {
        console.error('Send chat message error:', error);
        res.status(500).json({ error: error.message, success: false });
    }
});

// Mark chat as read
app.post('/chat/:chatId/read', async (req, res) => {
    const { chatId } = req.params;
    
    if (connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.sendSeen();
        res.json({ success: true, chatId });
    } catch (error) {
        console.error('Mark read error:', error);
        res.status(500).json({ error: error.message, success: false });
    }
});

if (fs.existsSync(SESSION_PATH)) {
    console.log('Session found, auto-initializing...');
    initializeClient();
}

app.listen(PORT, BIND_HOST, () => {
    console.log(`WhatsApp service running on ${BIND_HOST}:${PORT}`);
    console.log('API Secret:', API_SECRET ? 'Configured' : 'Not set (local-only access)');
});
