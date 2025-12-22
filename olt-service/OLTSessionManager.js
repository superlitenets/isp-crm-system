const { Telnet } = require('telnet-client');

class OLTSession {
    constructor(oltId, config) {
        this.oltId = oltId;
        this.config = config;
        this.connection = null;
        this.connected = false;
        this.lastActivity = null;
        this.commandQueue = [];
        this.processing = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.keepaliveInterval = null;
    }

    async connect() {
        this.connection = new Telnet();
        
        const params = {
            host: this.config.host,
            port: this.config.port || 23,
            timeout: 15000,
            shellPrompt: /[>#$%]\s*$/,
            loginPrompt: /[Uu]sername:|[Ll]ogin:/,
            passwordPrompt: /[Pp]assword:/,
            username: this.config.username,
            password: this.config.password,
            pageSeparator: '---- More ----',
            ors: '\r\n',
            sendTimeout: 10000,
            execTimeout: 30000,
            negotiationMandatory: false,
            stripShellPrompt: false
        };

        try {
            await this.connection.connect(params);
            this.connected = true;
            this.lastActivity = Date.now();
            this.reconnectAttempts = 0;
            console.log(`[OLT ${this.oltId}] Connected to ${this.config.host}`);
            
            this.startKeepalive();
            
            await this.execute('screen-length 0 temporary');
            
            return true;
        } catch (error) {
            console.error(`[OLT ${this.oltId}] Connection failed:`, error.message);
            this.connected = false;
            throw error;
        }
    }

    startKeepalive() {
        if (this.keepaliveInterval) {
            clearInterval(this.keepaliveInterval);
        }
        
        this.keepaliveInterval = setInterval(async () => {
            if (this.connected && !this.processing) {
                try {
                    await this.execute('');
                    console.log(`[OLT ${this.oltId}] Keepalive sent`);
                } catch (error) {
                    console.error(`[OLT ${this.oltId}] Keepalive failed:`, error.message);
                    this.connected = false;
                }
            }
        }, 60000);
    }

    async disconnect() {
        if (this.keepaliveInterval) {
            clearInterval(this.keepaliveInterval);
            this.keepaliveInterval = null;
        }
        
        if (this.connection && this.connected) {
            try {
                await this.connection.end();
            } catch (error) {
                console.error(`[OLT ${this.oltId}] Disconnect error:`, error.message);
            }
        }
        
        this.connected = false;
        this.connection = null;
        console.log(`[OLT ${this.oltId}] Disconnected`);
    }

    async reconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            throw new Error(`Max reconnect attempts (${this.maxReconnectAttempts}) reached`);
        }
        
        this.reconnectAttempts++;
        console.log(`[OLT ${this.oltId}] Reconnecting (attempt ${this.reconnectAttempts})...`);
        
        await this.disconnect();
        await new Promise(resolve => setTimeout(resolve, 2000));
        await this.connect();
    }

    async execute(command, options = {}) {
        if (!this.connected) {
            await this.reconnect();
        }

        const timeout = options.timeout || 30000;
        
        try {
            this.lastActivity = Date.now();
            const result = await this.connection.exec(command, {
                timeout: timeout,
                execTimeout: timeout
            });
            return result;
        } catch (error) {
            console.error(`[OLT ${this.oltId}] Command failed:`, error.message);
            
            if (error.message.includes('socket') || error.message.includes('timeout') || error.message.includes('end')) {
                this.connected = false;
                await this.reconnect();
                return await this.connection.exec(command, { timeout: timeout });
            }
            
            throw error;
        }
    }

    async executeBatch(commands, options = {}) {
        const results = [];
        for (const command of commands) {
            try {
                const result = await this.execute(command, options);
                results.push({ command, success: true, output: result });
            } catch (error) {
                results.push({ command, success: false, error: error.message });
            }
        }
        return results;
    }

    getStatus() {
        return {
            oltId: this.oltId,
            host: this.config.host,
            connected: this.connected,
            lastActivity: this.lastActivity,
            reconnectAttempts: this.reconnectAttempts,
            queueLength: this.commandQueue.length
        };
    }
}

class OLTSessionManager {
    constructor() {
        this.sessions = new Map();
        this.commandLocks = new Map();
    }

    async connect(oltId, config) {
        if (this.sessions.has(oltId)) {
            const existingSession = this.sessions.get(oltId);
            if (existingSession.connected) {
                console.log(`[OLT ${oltId}] Already connected`);
                return;
            }
            await existingSession.disconnect();
        }

        const session = new OLTSession(oltId, config);
        await session.connect();
        this.sessions.set(oltId, session);
    }

    async disconnect(oltId) {
        const session = this.sessions.get(oltId);
        if (session) {
            await session.disconnect();
            this.sessions.delete(oltId);
        }
    }

    async disconnectAll() {
        for (const [oltId, session] of this.sessions) {
            await session.disconnect();
        }
        this.sessions.clear();
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
            return await session.execute(command, options);
        });

        this.commandLocks.set(oltId, executionPromise.catch(() => {}));
        
        return await executionPromise;
    }

    async executeBatch(oltId, commands, options = {}) {
        const session = this.sessions.get(oltId);
        if (!session) {
            throw new Error(`No session for OLT ${oltId}. Connect first.`);
        }

        if (!this.commandLocks.has(oltId)) {
            this.commandLocks.set(oltId, Promise.resolve());
        }

        const currentLock = this.commandLocks.get(oltId);
        
        const executionPromise = currentLock.then(async () => {
            return await session.executeBatch(commands, options);
        });

        this.commandLocks.set(oltId, executionPromise.catch(() => {}));
        
        return await executionPromise;
    }

    getSessionStatus(oltId) {
        const session = this.sessions.get(oltId);
        if (!session) {
            return { oltId, connected: false, error: 'No session' };
        }
        return session.getStatus();
    }

    getAllSessionStatus() {
        const statuses = [];
        for (const [oltId, session] of this.sessions) {
            statuses.push(session.getStatus());
        }
        return statuses;
    }

    getSessionCount() {
        return this.sessions.size;
    }
}

module.exports = OLTSessionManager;
