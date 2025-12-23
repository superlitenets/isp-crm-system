const net = require('net');

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
        this.promptPattern = /(?:<[^<>]+>|\[[^\[\]]+\])\s*$/;
        this.dataListeners = [];
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
            });

            this.socket.on('data', (data) => {
                const chunk = data.toString();
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

            // Wait for login to complete
            const loginCheck = setInterval(() => {
                if (this.connected) {
                    clearInterval(loginCheck);
                    clearTimeout(timeout);
                    this.reconnectAttempts = 0;
                    resolve(true);
                }
            }, 500);

            setTimeout(() => {
                clearInterval(loginCheck);
                if (!this.connected) {
                    reject(new Error('Login timeout'));
                }
            }, 30000);
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
                
                // Disable paging
                setTimeout(() => {
                    this.sendCommand('screen-length 0 temporary').catch(() => {});
                }, 500);
            }
        }
    }

    async sendCommand(command, timeout = 60000) {
        return new Promise((resolve, reject) => {
            if (!this.socket || !this.connected) {
                return reject(new Error('Not connected'));
            }

            let response = '';
            let resolved = false;
            let timeoutId = null;
            
            const startMarker = `__CMD_START_${Date.now()}__`;
            
            const dataHandler = (chunk) => {
                response += chunk;
                
                // Handle pagination
                if (response.includes('---- More') || response.includes('--More--') || response.includes('Press any key')) {
                    this.socket.write(' ');
                    response = response.replace(/---- More.*?----/gi, '');
                    response = response.replace(/--More--/gi, '');
                    response = response.replace(/Press any key.*$/gi, '');
                }
                
                // Check for prompt (command complete)
                if (this.promptPattern.test(response)) {
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
            const cmdBuffer = Buffer.from(command + '\r\n', 'utf8');
            console.log(`[OLT ${this.oltId}] Sending command: "${command}" (${cmdBuffer.length} bytes)`);
            this.socket.write(cmdBuffer);
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

        const session = new OLTSession(oltId, config);
        await session.connect();
        this.sessions.set(oltId, session);
        
        this.startKeepalive(oltId);
    }

    startKeepalive(oltId) {
        if (this.keepaliveIntervals.has(oltId)) {
            clearInterval(this.keepaliveIntervals.get(oltId));
        }
        
        const interval = setInterval(async () => {
            const session = this.sessions.get(oltId);
            if (session && session.connected && session.socket) {
                try {
                    session.socket.write('\r\n');
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
