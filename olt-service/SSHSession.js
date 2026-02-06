const { Client } = require('ssh2');

class SSHSession {
    constructor(oltId, config) {
        this.oltId = oltId;
        this.config = config;
        this.client = null;
        this.stream = null;
        this.connected = false;
        this.buffer = '';
        this.lastActivity = null;
        this.initComplete = false;
        this.initStarted = false;
        this.promptPattern = /(?:<[^<>]+>|\[[^\[\]]+\]|[A-Z0-9_-]+(?:\([^)]+\))?[#>])\s*$/i;
        this.dataListeners = [];
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.client = new Client();
            
            const timeout = setTimeout(() => {
                this.client.end();
                reject(new Error('SSH connection timeout'));
            }, 30000);

            this.client.on('ready', () => {
                console.log(`[OLT ${this.oltId}] SSH connected to ${this.config.host}`);
                
                // Use 'vt100' terminal with raw mode settings to prevent space stripping
                // Setting ICRNL=0 and ICANON=0 helps with character-level processing
                const ptyModes = {
                    ECHO: 0,        // Don't echo back locally
                    ICANON: 0,      // Non-canonical mode (raw)
                    ICRNL: 0,       // Don't translate CR to NL
                    INLCR: 0,       // Don't translate NL to CR
                    ISIG: 0,        // Don't process signals
                    TTY_OP_ISPEED: 115200,
                    TTY_OP_OSPEED: 115200
                };
                this.client.shell({ term: 'vt100', cols: 200, rows: 50, modes: ptyModes }, (err, stream) => {
                    if (err) {
                        clearTimeout(timeout);
                        reject(err);
                        return;
                    }
                    
                    this.stream = stream;
                    this.connected = true;
                    this.lastActivity = Date.now();
                    
                    stream.on('data', (data) => {
                        const chunk = data.toString('utf8');
                        this.buffer += chunk;
                        this.dataListeners.forEach(listener => listener(chunk));
                        
                        // Check for login prompt completion - only run init once
                        if (!this.initComplete && !this.initStarted && this.promptPattern.test(this.buffer)) {
                            this.initStarted = true;
                            this.runInitSequence();
                        }
                    });
                    
                    stream.on('close', () => {
                        console.log(`[OLT ${this.oltId}] SSH stream closed`);
                        this.connected = false;
                    });
                    
                    stream.stderr.on('data', (data) => {
                        console.error(`[OLT ${this.oltId}] SSH stderr:`, data.toString());
                    });
                });
            });

            this.client.on('error', (err) => {
                console.error(`[OLT ${this.oltId}] SSH error:`, err.message);
                clearTimeout(timeout);
                this.connected = false;
                reject(err);
            });

            this.client.on('close', () => {
                console.log(`[OLT ${this.oltId}] SSH connection closed`);
                this.connected = false;
            });

            // Connect with legacy algorithm support for older Huawei OLTs
            this.client.connect({
                host: this.config.host,
                port: this.config.sshPort || 22,
                username: this.config.username,
                password: this.config.password,
                readyTimeout: 30000,
                // Legacy algorithms for older Huawei OLTs
                // Note: blowfish-cbc is not supported in ssh2 v1.x
                algorithms: {
                    kex: [
                        'diffie-hellman-group-exchange-sha256',
                        'diffie-hellman-group14-sha256',
                        'diffie-hellman-group14-sha1',
                        'diffie-hellman-group-exchange-sha1',
                        'diffie-hellman-group1-sha1'
                    ],
                    cipher: [
                        'aes128-ctr',
                        'aes192-ctr',
                        'aes256-ctr',
                        'aes256-gcm',
                        'aes256-gcm@openssh.com',
                        'aes128-gcm',
                        'aes128-gcm@openssh.com',
                        'aes128-cbc',
                        'aes192-cbc',
                        'aes256-cbc',
                        '3des-cbc'
                    ],
                    serverHostKey: [
                        'ssh-rsa',
                        'rsa-sha2-256',
                        'rsa-sha2-512',
                        'ssh-dss',
                        'ecdsa-sha2-nistp256',
                        'ecdsa-sha2-nistp384',
                        'ecdsa-sha2-nistp521'
                    ],
                    hmac: [
                        'hmac-sha2-256',
                        'hmac-sha2-512',
                        'hmac-sha1',
                        'hmac-md5'
                    ]
                }
            });

            // Wait for init sequence to complete
            const initCheck = setInterval(() => {
                if (this.initComplete) {
                    clearInterval(initCheck);
                    clearTimeout(timeout);
                    console.log(`[OLT ${this.oltId}] SSH session ready`);
                    resolve(true);
                }
            }, 500);

            // Timeout for init sequence
            setTimeout(() => {
                clearInterval(initCheck);
                if (!this.initComplete && this.connected) {
                    console.log(`[OLT ${this.oltId}] SSH init timeout, proceeding anyway`);
                    this.initComplete = true;
                    clearTimeout(timeout);
                    resolve(true);
                }
            }, 15000);
        });
    }

    async runInitSequence() {
        if (this.initComplete) return;
        
        try {
            await new Promise(r => setTimeout(r, 1000));
            
            await this.sendCommand('enable', 10000);
            console.log(`[OLT ${this.oltId}] SSH entered enable mode`);
            
            await this.sendCommand('config', 10000);
            console.log(`[OLT ${this.oltId}] SSH entered config mode`);
            
            await this.sendCommand('screen-length 0 temporary', 10000);
            console.log(`[OLT ${this.oltId}] SSH disabled paging`);
            
            this.initComplete = true;
        } catch (e) {
            console.log(`[OLT ${this.oltId}] SSH init error: ${e.message}`);
            this.initComplete = true;
        }
    }

    async sendCommand(command, timeout = 60000) {
        const lines = command.split(/\r?\n/).filter(l => l.trim());
        if (lines.length > 1) {
            console.log(`[OLT ${this.oltId}] SSH multi-line command (${lines.length} lines)`);
            let fullResponse = '';
            for (let i = 0; i < lines.length; i++) {
                const response = await this.sendSingleCommand(lines[i], timeout);
                fullResponse += response;
                if (i < lines.length - 1) {
                    await new Promise(r => setTimeout(r, 500));
                }
            }
            return fullResponse;
        }
        
        return this.sendSingleCommand(command, timeout);
    }

    async flushBuffer() {
        this.buffer = '';
        this.dataListeners = [];
        await new Promise(r => setTimeout(r, 100));
        this.buffer = '';
    }
    
    stripAnsi(str) {
        return str.replace(/\x1b\[[0-9;]*[a-zA-Z]/g, '')
                  .replace(/\x1b\[[0-9;]*[?]?[a-zA-Z]/g, '')
                  .replace(/\x1b[()][A-Z0-9]/g, '')
                  .replace(/\x1b[>=<]/g, '')
                  .replace(/[\x00-\x08\x0e-\x1f]/g, '');
    }

    async sendSingleCommand(command, timeout = 60000) {
        await this.flushBuffer();
        
        return new Promise((resolve, reject) => {
            if (!this.stream || !this.connected) {
                return reject(new Error('SSH not connected'));
            }

            let response = '';
            let resolved = false;
            let timeoutId = null;
            let commandSeen = false;
            let confirmationSent = false;
            const cmdPrefix = command.substring(0, Math.min(20, command.length));

            const dataHandler = (chunk) => {
                response += chunk;
                const cleanResponse = this.stripAnsi(response);
                
                if (!commandSeen && cleanResponse.includes(cmdPrefix)) {
                    commandSeen = true;
                }
                
                if (response.includes('---- More') || response.includes('--More--')) {
                    this.stream.write(' ');
                    response = response.replace(/---- More.*?----/gi, '');
                    response = response.replace(/--More--/gi, '');
                }
                
                if (!confirmationSent && commandSeen) {
                    const confirmPatterns = [
                        /\[y\/n\]/i,
                        /\(y\/n\)/i,
                        /y or n/i,
                        /Are you sure/i,
                        /confirm.*\?/i,
                        /delete this ont/i,
                        /to delete\?/i
                    ];
                    const needsConfirmation = confirmPatterns.some(p => p.test(cleanResponse));
                    if (needsConfirmation) {
                        confirmationSent = true;
                        console.log(`[OLT ${this.oltId}] SSH confirmation prompt detected, sending 'y'`);
                        setTimeout(() => {
                            this.stream.write('y\r');
                        }, 100);
                    }
                }
                
                if (commandSeen && this.promptPattern.test(cleanResponse)) {
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
                    console.log(`[OLT ${this.oltId}] SSH command timeout, got ${response.length} bytes`);
                    if (response.length > 50) {
                        resolve(response);
                    } else {
                        reject(new Error('SSH command timeout'));
                    }
                }
            }, timeout);

            this.buffer = '';
            response = '';
            
            console.log(`[OLT ${this.oltId}] SSH sending: "${command}"`);
            
            this.stream.write(command + '\r');
        });
    }

    addDataListener(handler) {
        this.dataListeners.push(handler);
    }

    removeDataListener(handler) {
        this.dataListeners = this.dataListeners.filter(h => h !== handler);
    }

    async disconnect() {
        if (this.stream) {
            this.stream.end();
        }
        if (this.client) {
            this.client.end();
        }
        this.stream = null;
        this.client = null;
        this.connected = false;
        this.initComplete = false;
        this.initStarted = false;
        this.buffer = '';
        console.log(`[OLT ${this.oltId}] SSH disconnected`);
    }

    async execute(command, options = {}) {
        if (!this.connected || !this.stream) {
            throw new Error('SSH session not connected');
        }

        const timeout = options.timeout || 60000;
        
        try {
            return await this.sendCommand(command, timeout);
        } catch (error) {
            console.error(`[OLT ${this.oltId}] SSH command failed:`, error.message);
            throw error;
        }
    }

    getStatus() {
        return {
            oltId: this.oltId,
            host: this.config.host,
            connected: this.connected,
            protocol: 'ssh',
            lastActivity: this.lastActivity,
            initComplete: this.initComplete
        };
    }
}

module.exports = SSHSession;
