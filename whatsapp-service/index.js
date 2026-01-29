const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, downloadMediaMessage, getContentType } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode');
const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const pino = require('pino');

const app = express();
app.use(cors({ origin: true }));
app.use(bodyParser.json({ limit: '50mb' }));

const PORT = process.env.WA_PORT || 3001;
const BIND_HOST = process.env.WA_HOST || '0.0.0.0';
const SESSION_PATH = path.join(__dirname, '.baileys_auth');

const logger = pino({ level: 'warn' });

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

let sock = null;
let qrCodeData = null;
let qrCodeString = null;
let connectionStatus = 'disconnected';
let clientInfo = null;
let saveCreds = null;

let recentMessages = [];
let messageCallbacks = [];

let isInitializing = false;

async function initializeClient() {
    if (isInitializing) return;
    isInitializing = true;
    
    if (sock) {
        try {
            sock.ev.removeAllListeners('connection.update');
            sock.ev.removeAllListeners('creds.update');
            sock.ev.removeAllListeners('messages.upsert');
            sock.end();
        } catch (e) {}
        sock = null;
    }
    
    connectionStatus = 'initializing';
    console.log('Baileys client initializing...');
    
    try {
        if (!fs.existsSync(SESSION_PATH)) {
            fs.mkdirSync(SESSION_PATH, { recursive: true });
        }
        
        const { state, saveCreds: saveCredsFunc } = await useMultiFileAuthState(SESSION_PATH);
        saveCreds = saveCredsFunc;
        
        sock = makeWASocket({
            auth: state,
            printQRInTerminal: false,
            logger: logger,
            browser: ["Ubuntu", "Chrome", "20.0.04"],
            connectTimeoutMs: 120000,
            defaultQueryTimeoutMs: 120000,
            keepAliveIntervalMs: 30000,
            markOnlineOnConnect: true,
            syncFullHistory: false,
            retryRequestDelayMs: 5000,
            qrTimeout: 40000,
            version: [2, 3000, 1015901307]
        });
        
        sock.ev.on('creds.update', saveCreds);
        
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;
            
            if (qr) {
                console.log('QR Code received');
                connectionStatus = 'qr_ready';
                qrCodeString = qr;
                qrCodeData = await qrcode.toDataURL(qr);
            }
            
            if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode || lastDisconnect?.error?.code;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                
                console.log('Connection closed. Reason:', lastDisconnect?.error?.message || 'Unknown', 'Status Code:', statusCode, 'Reconnecting:', shouldReconnect);
                connectionStatus = 'disconnected';
                clientInfo = null;
                qrCodeData = null;
                qrCodeString = null;
                
                if (shouldReconnect) {
                    const delay = Math.min(30000, 5000); 
                    setTimeout(() => {
                        console.log(`Attempting to reconnect in ${delay/1000}s...`);
                        initializeClient();
                    }, delay);
                }
            } else if (connection === 'open') {
                console.log('WhatsApp client is ready!');
                connectionStatus = 'connected';
                qrCodeData = null;
                qrCodeString = null;
                
                if (sock.user) {
                    clientInfo = {
                        id: sock.user.id,
                        name: sock.user.name || sock.user.verifiedName,
                        phone: sock.user.id.split(':')[0].split('@')[0]
                    };
                    console.log('Connected as:', clientInfo.name, clientInfo.phone);
                }
            }
        });
        
        sock.ev.on('messages.upsert', async ({ messages, type }) => {
            if (type !== 'notify') return;
            
            for (const msg of messages) {
                if (msg.key.fromMe) continue;
                
                const messageType = getContentType(msg.message);
                const textContent = msg.message?.conversation || 
                                   msg.message?.extendedTextMessage?.text || 
                                   msg.message?.imageMessage?.caption ||
                                   msg.message?.videoMessage?.caption ||
                                   '';
                
                console.log('Incoming message from:', msg.key.remoteJid, 'Body:', textContent?.substring(0, 50));
                
                // Send webhook to PHP
                try {
                    const domain = process.env.REPL_SLUG + '.' + process.env.REPL_OWNER + '.repl.co';
                    const webhookUrl = `https://${domain}/webhooks/whatsapp.php`;
                    
                    fetch(webhookUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(msg)
                    }).catch(err => console.error('Webhook fetch error:', err.message));
                } catch (webhookErr) {
                    console.error('Webhook preparation error:', webhookErr.message);
                }
                
                const messageData = {
                    event: 'message_received',
                    from: msg.key.remoteJid,
                    to: sock.user?.id || '',
                    body: textContent,
                    type: messageType || 'text',
                    timestamp: msg.messageTimestamp,
                    messageId: msg.key.id,
                    isGroup: msg.key.remoteJid.endsWith('@g.us'),
                    senderName: msg.pushName || null,
                    hasMedia: ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage', 'stickerMessage'].includes(messageType),
                    mediaData: null,
                    mimetype: null,
                    filename: null
                };
                
                if (messageData.hasMedia) {
                    try {
                        const buffer = await downloadMediaMessage(msg, 'buffer', {});
                        if (buffer) {
                            messageData.mediaData = buffer.toString('base64');
                            messageData.mimetype = msg.message[messageType]?.mimetype || null;
                            messageData.filename = msg.message[messageType]?.fileName || null;
                            console.log('Media downloaded:', messageData.mimetype, messageData.filename || 'no filename');
                        }
                    } catch (mediaErr) {
                        console.error('Failed to download media:', mediaErr.message);
                    }
                }
                
                recentMessages.push(messageData);
                if (recentMessages.length > 100) recentMessages.shift();
                
                messageCallbacks.forEach(cb => cb(messageData));
            }
        });
        
        sock.ev.on('messages.update', (updates) => {
            for (const update of updates) {
                if (update.update?.status) {
                    const ackStatus = {
                        0: 'error',
                        1: 'pending',
                        2: 'sent',
                        3: 'delivered',
                        4: 'read',
                        5: 'played'
                    };
                    console.log('Message ack:', update.key.id, ackStatus[update.update.status] || update.update.status);
                    
                    messageCallbacks.forEach(cb => cb({
                        event: 'message_ack',
                        messageId: update.key.id,
                        ack: update.update.status,
                        status: ackStatus[update.update.status] || 'unknown'
                    }));
                }
            }
        });
        
        console.log('Baileys client initialized.');
    } catch (err) {
        console.error('Initialization error:', err);
        connectionStatus = 'disconnected';
    } finally {
        isInitializing = false;
    }
}

app.get('/status', (req, res) => {
    res.json({
        status: connectionStatus,
        hasQR: !!qrCodeData,
        info: clientInfo ? {
            pushname: clientInfo.name,
            phone: clientInfo.phone,
            platform: 'Baileys'
        } : null
    });
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

app.post('/logout', async (req, res) => {
    try {
        if (sock) {
            await sock.logout();
            sock.end();
            sock = null;
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
    
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const formattedPhone = phone.replace(/[^0-9]/g, '');
        const jid = formattedPhone.includes('@') ? formattedPhone : `${formattedPhone}@s.whatsapp.net`;
        
        const result = await sock.sendMessage(jid, { text: message });
        console.log(`Message sent to ${phone}`);
        res.json({ 
            success: true, 
            messageId: result.key.id,
            timestamp: Math.floor(Date.now() / 1000)
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
    
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const jid = groupId.includes('@g.us') ? groupId : `${groupId}@g.us`;
        
        const result = await sock.sendMessage(jid, { text: message });
        console.log(`Message sent to group ${groupId}`);
        res.json({ 
            success: true, 
            messageId: result.key.id,
            timestamp: Math.floor(Date.now() / 1000)
        });
    } catch (error) {
        console.error('Send group error:', error);
        res.status(500).json({ error: error.message, success: false });
    }
});

app.get('/groups', async (req, res) => {
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const groups = await sock.groupFetchAllParticipating();
        const groupList = Object.values(groups).map(group => ({
            id: group.id,
            name: group.subject,
            participantsCount: group.participants?.length || 0
        }));
        res.json({ groups: groupList });
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
    
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    const results = [];
    
    for (const phone of phones) {
        try {
            const formattedPhone = phone.replace(/[^0-9]/g, '');
            const jid = `${formattedPhone}@s.whatsapp.net`;
            const result = await sock.sendMessage(jid, { text: message });
            results.push({ phone, success: true, messageId: result.key.id });
            
            if (delay > 0) {
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        } catch (error) {
            results.push({ phone, success: false, error: error.message });
        }
    }
    
    res.json({ results, total: phones.length, sent: results.filter(r => r.success).length });
});

app.get('/chats', async (req, res) => {
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const groups = await sock.groupFetchAllParticipating();
        const groupList = Object.values(groups).map(group => ({
            id: group.id,
            name: group.subject,
            isGroup: true,
            unreadCount: 0,
            lastMessageAt: null,
            phone: group.id.split('@')[0]
        }));
        
        const uniqueContacts = [...new Set(recentMessages.map(m => m.from).filter(id => id && !id.endsWith('@g.us')))];
        const contactList = uniqueContacts.slice(0, 30).map(id => ({
            id: id,
            name: recentMessages.find(m => m.from === id)?.senderName || id.split('@')[0],
            isGroup: false,
            unreadCount: 0,
            lastMessageAt: null,
            phone: id.split('@')[0]
        }));
        
        res.json({ chats: [...groupList, ...contactList].slice(0, 50) });
    } catch (error) {
        console.error('Get chats error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/chat/:chatId/messages', async (req, res) => {
    const { chatId } = req.params;
    const { limit = 50, includeMedia = 'false' } = req.query;
    
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const messageList = recentMessages.filter(m => m.from === chatId || m.to === chatId).slice(-parseInt(limit));
        res.json({ messages: messageList, chatId });
    } catch (error) {
        console.error('Get messages error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/messages/recent', (req, res) => {
    const { since } = req.query;
    let messages = recentMessages;
    
    if (since) {
        const sinceTimestamp = parseInt(since);
        messages = recentMessages.filter(m => m.timestamp > sinceTimestamp);
    }
    
    res.json({ messages });
});

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

app.post('/chat/:chatId/send', async (req, res) => {
    const { chatId } = req.params;
    const { message } = req.body;
    
    if (!message) {
        return res.status(400).json({ error: 'Message is required' });
    }
    
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const result = await sock.sendMessage(chatId, { text: message });
        res.json({ 
            success: true, 
            messageId: result.key.id,
            timestamp: Math.floor(Date.now() / 1000),
            chatId
        });
    } catch (error) {
        console.error('Send chat message error:', error);
        res.status(500).json({ error: error.message, success: false });
    }
});

app.post('/chat/:chatId/read', async (req, res) => {
    const { chatId } = req.params;
    
    if (connectionStatus !== 'connected' || !sock) {
        return res.status(503).json({ error: 'WhatsApp not connected', status: connectionStatus });
    }
    
    try {
        const chatMessages = recentMessages.filter(m => m.from === chatId);
        if (chatMessages.length > 0) {
            const lastMsg = chatMessages[chatMessages.length - 1];
            const participant = chatId.endsWith('@g.us') ? lastMsg.participant : undefined;
            await sock.readMessages([{ remoteJid: chatId, id: lastMsg.messageId, participant }]);
        }
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
    console.log(`WhatsApp service (Baileys) running on ${BIND_HOST}:${PORT}`);
    console.log('API Secret:', API_SECRET ? 'Configured' : 'Not set (local-only access)');
    console.log('Benefits: No Chromium, No Puppeteer, Direct WebSocket, ~50-100MB RAM');
});
