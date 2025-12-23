const { Telnet } = require('telnet-client');

class OLTSession {
    constructor(oltId, config) {
        this.oltId = oltId;
        this.config = config;
        this.connection = null;
        this.connected = false;
        this.lastActivity = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.buffer = '';
        this.promptPattern = /(?:<[^<>]+>|\[[^\[\]]+\])\s*$/;
    }

    async connect() {
        this.connection = new Telnet();
        
        const params = {
            host: this.config.host,
            port: this.config.port || 23,
            timeout: 30000,
            shellPrompt: false,
            loginPrompt: /[Uu]sername:|[Ll]ogin:|>>User name:|User name:/i,
            passwordPrompt: /[Pp]assword:/i,
            username: this.config.username,
            password: this.config.password,
            ors: '\r\n',
            sendTimeout: 30000,
            negotiationMandatory: false,
            initialLFCR: true,
            debug: false
        };

        try {
            await this.connection.connect(params);
            this.connected = true;
            this.lastActivity = Date.now();
            this.reconnectAttempts = 0;
            console.log(`[OLT ${this.oltId}] Connected to ${this.config.host}`);
            
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            await this.sendAndWait('screen-length 0 temporary', 10000);
            
            return true;
        } catch (error) {
            console.error(`[OLT ${this.oltId}] Connection failed:`, error.message);
            this.connected = false;
            throw error;
        }
    }

    async sendAndWait(command, timeout = 60000) {
        return new Promise(async (resolve, reject) => {
            if (!this.connection) {
                return reject(new Error('No connection'));
            }

            let response = '';
            let timeoutId = null;
            let resolved = false;

            const cleanup = () => {
                if (timeoutId) clearTimeout(timeoutId);
                this.connection.removeListener('data', dataHandler);
            };

            const dataHandler = (data) => {
                const chunk = data.toString();
                response += chunk;
                
                response = response.replace(/---- More.*?----/gi, '');
                response = response.replace(/--More--/gi, '');
                
                if (this.promptPattern.test(response)) {
                    if (!resolved) {
                        resolved = true;
                        cleanup();
                        this.lastActivity = Date.now();
                        resolve(response);
                    }
                }
            };

            this.connection.on('data', dataHandler);

            timeoutId = setTimeout(() => {
                if (!resolved) {
                    resolved = true;
                    cleanup();
                    if (response.length > 0) {
                        resolve(response);
                    } else {
                        reject(new Error('Command timeout'));
                    }
                }
            }, timeout);

            try {
                await this.connection.send(command);
            } catch (error) {
                cleanup();
                reject(error);
            }
        });
    }

    async disconnect() {
        if (this.connection && this.connected) {
            try {
                await this.connection.end();
            } catch (error) {
                // Ignore disconnect errors
            }
        }
        
        this.connected = false;
        this.connection = null;
        console.log(`[OLT ${this.oltId}] Disconnected`);
    }

    async reconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.reconnectAttempts = 0;
            throw new Error(`Max reconnect attempts (${this.maxReconnectAttempts}) reached`);
        }
        
        this.reconnectAttempts++;
        console.log(`[OLT ${this.oltId}] Reconnecting (attempt ${this.reconnectAttempts})...`);
        
        await this.disconnect();
        await new Promise(resolve => setTimeout(resolve, 3000));
        await this.connect();
    }

    async executeRaw(command, options = {}) {
        if (!this.connected || !this.connection) {
            await this.reconnect();
        }

        const timeout = options.timeout || 60000;
        
        try {
            const result = await this.sendAndWait(command, timeout);
            this.reconnectAttempts = 0;
            return result;
        } catch (error) {
            console.error(`[OLT ${this.oltId}] Command failed:`, error.message);
            this.connected = false;
            
            try {
                await this.reconnect();
                return await this.sendAndWait(command, timeout);
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
            if (session && session.connected) {
                try {
                    await session.sendAndWait('', 5000);
                } catch (error) {
                    console.error(`[OLT ${oltId}] Keepalive failed`);
                    session.connected = false;
                }
            }
        }, 50000);
        
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

        if (!this.commandLocks.has(oltId)) {
            this.commandLocks.set(oltId, Promise.resolve());
        }

        const currentLock = this.commandLocks.get(oltId);
        
        const executionPromise = currentLock.then(async () => {
            return await session.executeRaw(command, options);
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
