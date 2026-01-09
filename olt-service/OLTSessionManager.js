const net = require('net');

// Telnet protocol constants
const IAC = 255;  // Interpret As Command
const DONT = 254;
const DO = 253;
const WONT = 252;
const WILL = 251;
const SB = 250;   // Sub-negotiation Begin
const SE = 240;   // Sub-negotiation End

// Telnet options
const OPT_ECHO = 1;
const OPT_SGA = 3;          // Suppress Go Ahead
const OPT_TTYPE = 24;       // Terminal Type
const OPT_NAWS = 31;        // Window Size
const OPT_LINEMODE = 34;    // Linemode - MUST REJECT
const OPT_BINARY = 0;       // Binary Transmission

class OLTSession {
    constructor(oltId, config) {
        this.oltId = oltId;
        this.config = config;
        this.socket = null;
        this.connected = false;
        this.lastActivity = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.buffer = '';
        this.promptPattern = /(?:<[^<>]+>|\[[^\[\]]+\]|[A-Z0-9_-]+(?:\([^)]+\))?[#>])\s*$/i;
        this.dataListeners = [];
    }

    // Handle Telnet protocol negotiations
    handleTelnetNegotiation(data) {
        const cleanData = [];
        let i = 0;
        
        while (i < data.length) {
            if (data[i] === IAC) {
                if (i + 1 < data.length) {
                    const cmd = data[i + 1];
                    
                    if (cmd === IAC) {
                        // Escaped IAC, keep one 255 byte
                        cleanData.push(255);
                        i += 2;
                    } else if (cmd === DO || cmd === DONT || cmd === WILL || cmd === WONT) {
                        if (i + 2 < data.length) {
                            const option = data[i + 2];
                            // Critical: Reject LINEMODE to prevent space stripping
                            // Accept SGA (Suppress Go Ahead) for proper character mode
                            if (cmd === DO) {
                                if (option === OPT_BINARY) {
                                    // Accept BINARY mode - critical for preserving spaces
                                    this.socket.write(Buffer.from([IAC, WILL, option]));
                                    console.log(`[OLT ${this.oltId}] WILL BINARY (critical)`);
                                } else if (option === OPT_SGA) {
                                    // Accept Suppress Go Ahead - needed for character mode
                                    this.socket.write(Buffer.from([IAC, WILL, option]));
                                    console.log(`[OLT ${this.oltId}] WILL SGA`);
                                } else if (option === OPT_LINEMODE) {
                                    // CRITICAL: Reject LINEMODE - this causes space stripping!
                                    this.socket.write(Buffer.from([IAC, WONT, option]));
                                    console.log(`[OLT ${this.oltId}] WONT LINEMODE (critical)`);
                                } else if (option === OPT_ECHO) {
                                    // Reject local echo - let server echo
                                    this.socket.write(Buffer.from([IAC, WONT, option]));
                                } else if (option === OPT_TTYPE) {
                                    // Accept terminal type negotiation
                                    this.socket.write(Buffer.from([IAC, WILL, option]));
                                } else if (option === OPT_NAWS) {
                                    // Reject window size negotiation
                                    this.socket.write(Buffer.from([IAC, WONT, option]));
                                } else {
                                    // Reject unknown options
                                    this.socket.write(Buffer.from([IAC, WONT, option]));
                                }
                            } else if (cmd === WILL) {
                                if (option === OPT_BINARY) {
                                    // Accept server BINARY mode - critical for preserving spaces
                                    this.socket.write(Buffer.from([IAC, DO, option]));
                                    console.log(`[OLT ${this.oltId}] DO BINARY (critical)`);
                                } else if (option === OPT_ECHO || option === OPT_SGA) {
                                    // Accept server echo and SGA
                                    this.socket.write(Buffer.from([IAC, DO, option]));
                                } else {
                                    // Reject other server offers
                                    this.socket.write(Buffer.from([IAC, DONT, option]));
                                }
                            }
                            i += 3;
                        } else {
                            i++;
                        }
                    } else if (cmd === SB) {
                        // Skip sub-negotiation until SE
                        let j = i + 2;
                        while (j < data.length - 1) {
                            if (data[j] === IAC && data[j + 1] === SE) {
                                i = j + 2;
                                break;
                            }
                            j++;
                        }
                        if (j >= data.length - 1) i = data.length;
                    } else {
                        i += 2;
                    }
                } else {
                    i++;
                }
            } else {
                cleanData.push(data[i]);
                i++;
            }
        }
        
        return Buffer.from(cleanData);
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.socket = new net.Socket();
            this.buffer = '';
            
            const timeout = setTimeout(() => {
                this.socket.destroy();
                reject(new Error('Connection timeout'));
            }, 30000);

            this.socket.connect(this.config.port || 23, this.config.host, () => {
                clearTimeout(timeout);
                console.log(`[OLT ${this.oltId}] TCP connected to ${this.config.host}`);
                
                // Disable Nagle's algorithm to send each character immediately
                this.socket.setNoDelay(true);
                
                // Proactively negotiate BINARY + character mode to prevent space stripping
                // CRITICAL: Binary mode disables all character processing on the telnet layer
                // Send: WILL/DO BINARY (request binary transmission mode)
                // Send: WILL SGA (we want character-at-a-time mode)
                // Send: DONT LINEMODE (reject any line editing that strips spaces)
                // Send: DO ECHO (let server echo)
                this.socket.write(Buffer.from([
                    IAC, WILL, OPT_BINARY,    // We will send binary (no processing)
                    IAC, DO, OPT_BINARY,      // Server should send binary (no processing)
                    IAC, WILL, OPT_SGA,       // We will suppress go-ahead (character mode)
                    IAC, DONT, OPT_LINEMODE,  // Don't use linemode
                    IAC, DO, OPT_ECHO,        // Server should echo
                    IAC, DO, OPT_SGA          // Server should suppress go-ahead
                ]));
                console.log(`[OLT ${this.oltId}] Sent proactive telnet negotiation (BINARY + character mode)`);
            });

            this.socket.on('data', (data) => {
                // Strip Telnet negotiation bytes
                const cleanData = this.handleTelnetNegotiation(data);
                const chunk = cleanData.toString('utf8');
                this.buffer += chunk;
                
                // Notify all listeners
                this.dataListeners.forEach(listener => listener(chunk));
                
                // Handle login sequence
                if (!this.connected) {
                    this.handleLoginSequence();
                }
            });

            this.socket.on('error', (err) => {
                console.error(`[OLT ${this.oltId}] Socket error:`, err.message);
                this.connected = false;
            });

            this.socket.on('close', () => {
                console.log(`[OLT ${this.oltId}] Socket closed`);
                this.connected = false;
            });

            // Wait for login AND init sequence to complete
            const loginCheck = setInterval(() => {
                if (this.connected && this.initComplete) {
                    clearInterval(loginCheck);
                    clearTimeout(timeout);
                    this.reconnectAttempts = 0;
                    console.log(`[OLT ${this.oltId}] Connection ready`);
                    resolve(true);
                }
            }, 500);

            setTimeout(() => {
                clearInterval(loginCheck);
                if (!this.connected) {
                    reject(new Error('Login timeout'));
                } else if (!this.initComplete) {
                    // Login worked but init not complete - still usable
                    console.log(`[OLT ${this.oltId}] Init incomplete, proceeding anyway`);
                    resolve(true);
                }
            }, 45000);
        });
    }

    handleLoginSequence() {
        const buf = this.buffer.toLowerCase();
        
        // Check for username prompt
        if (buf.includes('user name:') || buf.includes('username:') || buf.includes('login:')) {
            if (!this.sentUsername) {
                this.sentUsername = true;
                this.socket.write(this.config.username + '\r\n');
                console.log(`[OLT ${this.oltId}] Sent username`);
            }
        }
        
        // Check for password prompt
        if (buf.includes('password:')) {
            if (!this.sentPassword) {
                this.sentPassword = true;
                this.socket.write(this.config.password + '\r\n');
                console.log(`[OLT ${this.oltId}] Sent password`);
            }
        }
        
        // Debug: Log buffer after password sent
        if (this.sentPassword && !this.loggedBuffer) {
            setTimeout(() => {
                if (!this.connected) {
                    console.log(`[OLT ${this.oltId}] Buffer after login (${this.buffer.length} bytes):`);
                    console.log(`[OLT ${this.oltId}] Last 500 chars: ${this.buffer.slice(-500).replace(/\r?\n/g, '\\n')}`);
                    this.loggedBuffer = true;
                }
            }, 5000);
        }
        
        // Check if we're logged in (prompt detected) - multiple patterns for Huawei
        const huaweiPrompts = [
            /(?:<[^<>]+>)\s*$/,           // <hostname>
            /(?:\[[^\[\]]+\])\s*$/,       // [hostname]
            /MA\d+\S*[>#]\s*$/,           // MA5600T# or MA5683T>
            /\S+[>#]\s*$/                 // Any hostname with > or #
        ];
        
        const promptMatched = huaweiPrompts.some(p => p.test(this.buffer));
        
        if (promptMatched) {
            if (!this.connected) {
                this.connected = true;
                this.lastActivity = Date.now();
                console.log(`[OLT ${this.oltId}] Login successful - prompt detected`);
                this.initComplete = false;
                
                // Enter enable mode, then config mode, then disable paging
                this.runInitSequence();
            }
        }
    }
    
    async runInitSequence() {
        try {
            // Wait for telnet negotiation to complete before sending commands
            // This prevents the space-stripping issue during LINEMODE negotiation
            await new Promise(r => setTimeout(r, 2000));
            
            await this.sendCommand('enable');
            console.log(`[OLT ${this.oltId}] Entered enable mode`);
            
            await this.sendCommand('config');
            console.log(`[OLT ${this.oltId}] Entered config mode`);
            
            await this.sendCommand('screen-length 0 temporary');
            console.log(`[OLT ${this.oltId}] Disabled paging - ready for commands`);
            
            this.initComplete = true;
        } catch (e) {
            console.log(`[OLT ${this.oltId}] Init sequence error: ${e.message}`);
            this.initComplete = true; // Allow commands anyway
        }
    }

    async sendCommand(command, timeout = 60000) {
        // Check if this is a multi-line command - if so, split and send sequentially
        const lines = command.split(/\r?\n/).filter(l => l.trim());
        if (lines.length > 1) {
            console.log(`[OLT ${this.oltId}] Multi-line command detected (${lines.length} lines), sending sequentially`);
            let fullResponse = '';
            for (const line of lines) {
                const response = await this.sendSingleCommand(line, timeout);
                fullResponse += response;
                // Small delay between commands to prevent garbling
                await new Promise(r => setTimeout(r, 100));
            }
            return fullResponse;
        }
        
        const result = await this.sendSingleCommand(command, timeout);
        // Small delay after command to allow OLT to settle before next command
        await new Promise(r => setTimeout(r, 100));
        return result;
    }
    
    async sendSingleCommand(command, timeout = 60000) {
        // Flush any stale data first by sending empty line and waiting
        await this.flushBuffer();
        
        return new Promise((resolve, reject) => {
            if (!this.socket || !this.connected) {
                return reject(new Error('Not connected'));
            }

            let response = '';
            let resolved = false;
            let timeoutId = null;
            let commandSeen = false;
            
            const dataHandler = (chunk) => {
                response += chunk;
                
                // Only start collecting after we see echo of our command
                if (!commandSeen && response.includes(command.substring(0, 20))) {
                    commandSeen = true;
                }
                
                // Handle pagination
                if (response.includes('---- More') || response.includes('--More--') || response.includes('Press any key')) {
                    this.socket.write(' ');
                    response = response.replace(/---- More.*?----/gi, '');
                    response = response.replace(/--More--/gi, '');
                    response = response.replace(/Press any key.*$/gi, '');
                }
                
                // Check for prompt (command complete) - only after seeing our command
                if (commandSeen && this.promptPattern.test(response)) {
                    if (!resolved) {
                        resolved = true;
                        clearTimeout(timeoutId);
                        this.removeDataListener(dataHandler);
                        this.lastActivity = Date.now();
                        resolve(response);
                    }
                }
            };

            this.addDataListener(dataHandler);

            timeoutId = setTimeout(() => {
                if (!resolved) {
                    resolved = true;
                    this.removeDataListener(dataHandler);
                    console.log(`[OLT ${this.oltId}] Command timeout, got ${response.length} bytes`);
                    console.log(`[OLT ${this.oltId}] Last 300 chars: ${response.slice(-300).replace(/\r?\n/g, '\\n')}`);
                    
                    // Return what we have even on timeout
                    if (response.length > 50) {
                        resolve(response);
                    } else {
                        reject(new Error('Command timeout'));
                    }
                }
            }, timeout);

            // Clear buffer and send command
            this.buffer = '';
            response = '';
            
            // Send command character by character to simulate typing
            // This prevents the OLT from stripping spaces on fast buffer input
            const sendSlowly = async () => {
                console.log(`[OLT ${this.oltId}] Typing: "${command}" (${command.length} chars)`);
                
                for (let i = 0; i < command.length; i++) {
                    const char = command[i];
                    this.socket.write(Buffer.from(char, 'utf8'));
                    // Extra delay for spaces - OLT may need more time to process them
                    const delay = (char === ' ') ? 100 : 50;
                    await new Promise(r => setTimeout(r, delay));
                }
                // Send CR at the end
                this.socket.write(Buffer.from('\r', 'utf8'));
            };
            
            sendSlowly().catch(err => {
                console.error(`[OLT ${this.oltId}] Send error:`, err);
            });
        });
    }
    
    async flushBuffer() {
        return new Promise((resolve) => {
            // Clear internal buffer
            this.buffer = '';
            
            // Send empty line to flush any pending data
            if (this.socket && this.connected) {
                this.socket.write('\r\n');
            }
            
            // Wait briefly for any stale data to arrive and be discarded
            setTimeout(() => {
                this.buffer = '';
                resolve();
            }, 100);
        });
    }

    addDataListener(handler) {
        this.dataListeners.push(handler);
    }

    removeDataListener(handler) {
        this.dataListeners = this.dataListeners.filter(h => h !== handler);
    }

    async disconnect() {
        if (this.socket) {
            try {
                this.socket.destroy();
            } catch (e) {}
        }
        this.socket = null;
        this.connected = false;
        this.sentUsername = false;
        this.sentPassword = false;
        this.buffer = '';
        console.log(`[OLT ${this.oltId}] Disconnected`);
    }

    async reconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.reconnectAttempts = 0;
            throw new Error('Max reconnect attempts reached');
        }
        
        this.reconnectAttempts++;
        console.log(`[OLT ${this.oltId}] Reconnecting (attempt ${this.reconnectAttempts})...`);
        
        await this.disconnect();
        await new Promise(resolve => setTimeout(resolve, 3000));
        await this.connect();
    }

    async execute(command, options = {}) {
        if (!this.connected || !this.socket) {
            await this.reconnect();
        }

        const timeout = options.timeout || 60000;
        
        try {
            return await this.sendCommand(command, timeout);
        } catch (error) {
            console.error(`[OLT ${this.oltId}] Command failed:`, error.message);
            
            try {
                await this.reconnect();
                return await this.sendCommand(command, timeout);
            } catch (retryError) {
                throw retryError;
            }
        }
    }

    getStatus() {
        return {
            oltId: this.oltId,
            host: this.config.host,
            connected: this.connected,
            lastActivity: this.lastActivity,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

const SSHSession = require('./SSHSession');

class OLTSessionManager {
    constructor() {
        this.sessions = new Map();
        this.commandLocks = new Map();
        this.keepaliveIntervals = new Map();
    }

    async connect(oltId, config) {
        if (this.sessions.has(oltId)) {
            const existingSession = this.sessions.get(oltId);
            if (existingSession.connected) {
                return;
            }
            await this.disconnect(oltId);
        }

        let session;
        if (config.protocol === 'ssh') {
            console.log(`[OLT ${oltId}] Using SSH protocol`);
            session = new SSHSession(oltId, config);
        } else {
            console.log(`[OLT ${oltId}] Using Telnet protocol`);
            session = new OLTSession(oltId, config);
        }
        
        await session.connect();
        this.sessions.set(oltId, session);
        
        this.startKeepalive(oltId, config.protocol);
    }

    startKeepalive(oltId, protocol) {
        if (this.keepaliveIntervals.has(oltId)) {
            clearInterval(this.keepaliveIntervals.get(oltId));
        }
        
        const interval = setInterval(async () => {
            const session = this.sessions.get(oltId);
            if (session && session.connected) {
                try {
                    if (protocol === 'ssh' && session.stream) {
                        session.stream.write('\r');
                    } else if (session.socket) {
                        session.socket.write('\r\n');
                    }
                    session.lastActivity = Date.now();
                } catch (error) {
                    console.error(`[OLT ${oltId}] Keepalive failed`);
                    session.connected = false;
                }
            }
        }, 45000);
        
        this.keepaliveIntervals.set(oltId, interval);
    }

    stopKeepalive(oltId) {
        if (this.keepaliveIntervals.has(oltId)) {
            clearInterval(this.keepaliveIntervals.get(oltId));
            this.keepaliveIntervals.delete(oltId);
        }
    }

    async disconnect(oltId) {
        this.stopKeepalive(oltId);
        
        const session = this.sessions.get(oltId);
        if (session) {
            await session.disconnect();
            this.sessions.delete(oltId);
        }
        
        this.commandLocks.delete(oltId);
    }

    async disconnectAll() {
        for (const oltId of this.sessions.keys()) {
            await this.disconnect(oltId);
        }
    }

    async execute(oltId, command, options = {}) {
        const session = this.sessions.get(oltId);
        if (!session) {
            throw new Error(`No session for OLT ${oltId}. Connect first.`);
        }

        // Use command lock to serialize commands per OLT
        if (!this.commandLocks.has(oltId)) {
            this.commandLocks.set(oltId, Promise.resolve());
        }

        const currentLock = this.commandLocks.get(oltId);
        
        const executionPromise = currentLock.then(async () => {
            return await session.execute(command, options);
        });

        this.commandLocks.set(oltId, executionPromise.catch(() => {}));
        
        return await executionPromise;
    }

    async executeBatch(oltId, commands, options = {}) {
        const results = [];
        for (const command of commands) {
            const result = await this.execute(oltId, command, options);
            results.push(result);
        }
        return results;
    }

    getSessionCount() {
        return this.sessions.size;
    }

    getSessionStatus(oltId) {
        const session = this.sessions.get(oltId);
        return session ? session.getStatus() : { connected: false };
    }

    getAllSessionStatus() {
        const status = {};
        for (const [oltId, session] of this.sessions) {
            status[oltId] = session.getStatus();
        }
        return status;
    }
}

module.exports = OLTSessionManager;
