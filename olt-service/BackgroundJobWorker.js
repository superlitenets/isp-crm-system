const crypto = require('crypto');

class BackgroundJobWorker {
    constructor(sessionManager, pool) {
        this.sessionManager = sessionManager;
        this.pool = pool;
        this.pollInterval = 5000;
        this.timer = null;
        this.isProcessing = false;
    }

    start() {
        console.log(`[JobWorker] Started (polling every ${this.pollInterval / 1000}s)`);
        this.timer = setInterval(() => this.poll(), this.pollInterval);
        this.poll();
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        console.log('[JobWorker] Stopped');
    }

    async poll() {
        if (this.isProcessing) return;
        this.isProcessing = true;

        try {
            const result = await this.pool.query(
                `UPDATE background_jobs 
                 SET status = 'running', started_at = CURRENT_TIMESTAMP 
                 WHERE id = (
                     SELECT id FROM background_jobs 
                     WHERE status = 'pending' 
                     ORDER BY created_at ASC 
                     LIMIT 1 
                     FOR UPDATE SKIP LOCKED
                 ) 
                 RETURNING id, job_type, params, user_id`
            );

            if (result.rows.length > 0) {
                const job = result.rows[0];
                console.log(`[JobWorker] Claimed job #${job.id} (${job.job_type})`);
                await this.processJob(job);
            }
        } catch (err) {
            console.error(`[JobWorker] Poll error: ${err.message}`);
        } finally {
            this.isProcessing = false;
        }
    }

    async processJob(job) {
        let params;
        try {
            params = typeof job.params === 'string' ? JSON.parse(job.params) : (job.params || {});
        } catch (parseErr) {
            await this.pool.query(
                "UPDATE background_jobs SET status = 'failed', message = $1, completed_at = CURRENT_TIMESTAMP WHERE id = $2",
                [`Invalid job parameters: ${parseErr.message}`, job.id]
            );
            return;
        }

        try {
            switch (job.job_type) {
                case 'setup_tr069_full':
                    await this.processTR069BulkSetup(job.id, params, job.user_id);
                    break;
                default:
                    await this.pool.query(
                        "UPDATE background_jobs SET status = 'failed', message = $1, completed_at = CURRENT_TIMESTAMP WHERE id = $2",
                        [`Unknown job type: ${job.job_type}`, job.id]
                    );
            }
        } catch (err) {
            console.error(`[JobWorker] Job #${job.id} failed: ${err.message}`);
            await this.pool.query(
                "UPDATE background_jobs SET status = 'failed', message = $1, completed_at = CURRENT_TIMESTAMP WHERE id = $2",
                [err.message, job.id]
            );
        }
    }

    async processTR069BulkSetup(jobId, params, userId) {
        const oltId = parseInt(params.olt_id);
        const profileId = parseInt(params.profile_id || 3);
        const targetSlot = params.target_slot !== null && params.target_slot !== undefined ? parseInt(params.target_slot) : null;

        if (!oltId) {
            throw new Error('Missing olt_id in job parameters');
        }

        const oltResult = await this.pool.query(
            'SELECT * FROM huawei_olts WHERE id = $1 AND is_active = true',
            [oltId]
        );
        if (oltResult.rows.length === 0) {
            throw new Error('OLT not found or inactive');
        }
        const olt = oltResult.rows[0];

        await this.ensureOltSession(oltId, olt);

        let sql = `SELECT id, frame, slot, port, onu_id, sn, name FROM huawei_onus 
                    WHERE olt_id = $1 AND is_authorized = true AND onu_id IS NOT NULL`;
        const sqlParams = [oltId];
        if (targetSlot !== null) {
            sql += ' AND slot = $2';
            sqlParams.push(targetSlot);
        }
        sql += ' ORDER BY slot, port, onu_id';

        const onuResult = await this.pool.query(sql, sqlParams);
        const onus = onuResult.rows;
        const total = onus.length;

        await this.pool.query(
            'UPDATE background_jobs SET total = $1, progress = 0 WHERE id = $2',
            [total, jobId]
        );

        if (total === 0) {
            const slotLabel = targetSlot !== null ? ` on slot ${targetSlot}` : '';
            await this.pool.query(
                "UPDATE background_jobs SET status = 'completed', message = $1, completed_at = CURRENT_TIMESTAMP WHERE id = $2",
                [`No authorized ONUs found${slotLabel}`, jobId]
            );
            return;
        }

        const slotGroups = {};
        for (const onu of onus) {
            const key = `${onu.frame || 0}/${onu.slot}`;
            if (!slotGroups[key]) slotGroups[key] = [];
            slotGroups[key].push(onu);
        }

        let bound = 0;
        let failed = 0;
        const errors = [];
        let processed = 0;

        for (const [slotKey, slotOnus] of Object.entries(slotGroups)) {
            try {
                const scriptLines = [`interface gpon ${slotKey}`];
                for (const onu of slotOnus) {
                    scriptLines.push(`ont tr069-server-config ${onu.port} ${onu.onu_id} profile-id 0`);
                    scriptLines.push(`ont tr069-server-config ${onu.port} ${onu.onu_id} profile-id ${profileId}`);
                }
                scriptLines.push('quit');

                const script = scriptLines.join('\r\n');
                const slotTimeout = Math.max(120000, slotOnus.length * 3000);

                const output = await this.sessionManager.executeRaw(
                    oltId.toString(), script, { timeout: slotTimeout }
                );

                if (output && !/Failure|Error:|failed|Invalid/i.test(output)) {
                    bound += slotOnus.length;
                } else if (output && /already|repeatedly/i.test(output)) {
                    bound += slotOnus.length;
                } else {
                    for (const onu of slotOnus) {
                        failed++;
                        if (errors.length < 10) {
                            errors.push(`${onu.name || onu.sn}: ${(output || 'No output').substring(0, 100)}`);
                        }
                    }
                }
            } catch (err) {
                for (const onu of slotOnus) {
                    failed++;
                    if (errors.length < 10) {
                        errors.push(`${onu.name || onu.sn}: ${err.message}`);
                    }
                }
            }

            processed += slotOnus.length;
            await this.pool.query(
                'UPDATE background_jobs SET progress = $1, message = $2 WHERE id = $3',
                [processed, `Processing slot ${slotKey}: ${processed}/${total} ONUs`, jobId]
            );

            if (processed < total) {
                await new Promise(r => setTimeout(r, 500));
            }
        }

        const slotLabel = targetSlot !== null ? ` (Slot ${targetSlot})` : ' (All slots)';
        let summary = `TR-069 profile ${profileId} bound to ${bound}/${total} ONUs${slotLabel}`;
        if (failed > 0) summary += ` | ${failed} failed`;
        summary += ' | ONUs will auto-register in GenieACS on next Inform (no reboot)';
        if (errors.length > 0) summary += ' | Errors: ' + errors.slice(0, 5).join('; ');

        const resultData = JSON.stringify({
            bound, failed, total,
            errors: errors.slice(0, 10)
        });

        const status = failed === 0 ? 'completed' : 'completed_with_errors';
        await this.pool.query(
            "UPDATE background_jobs SET status = $1, progress = $2, message = $3, result = $4::jsonb, completed_at = CURRENT_TIMESTAMP WHERE id = $5",
            [status, total, summary, resultData, jobId]
        );

        await this.pool.query(
            `INSERT INTO huawei_olt_logs (olt_id, action, status, message, user_id, created_at) 
             VALUES ($1, 'setup_tr069_full', $2, $3, $4, CURRENT_TIMESTAMP)`,
            [oltId, failed > 0 ? 'partial' : 'success', summary, userId]
        ).catch(err => console.error(`[JobWorker] Failed to log: ${err.message}`));

        console.log(`[JobWorker] Job #${jobId} completed: ${summary}`);
    }

    decryptPassword(encrypted) {
        if (!encrypted) return '';
        try {
            const key = process.env.SESSION_SECRET || 'default-secret-key-change-me';
            const combined = Buffer.from(encrypted, 'base64');
            const iv = combined.slice(0, 16);
            const ciphertextBase64 = combined.slice(16).toString('utf8');
            const ciphertext = Buffer.from(ciphertextBase64, 'base64');
            const keyBuffer = Buffer.alloc(32);
            Buffer.from(key).copy(keyBuffer);
            const decipher = crypto.createDecipheriv('aes-256-cbc', keyBuffer, iv);
            let decrypted = decipher.update(ciphertext);
            decrypted = Buffer.concat([decrypted, decipher.final()]);
            return decrypted.toString('utf8');
        } catch (e) {
            return encrypted;
        }
    }

    async ensureOltSession(oltId, olt) {
        const oltIdStr = oltId.toString();

        if (this.sessionManager.sessions && this.sessionManager.sessions.has(oltIdStr)) {
            const session = this.sessionManager.sessions.get(oltIdStr);
            if (session && session.connected) return;
        }

        const password = this.decryptPassword(olt.password_encrypted);

        await this.sessionManager.connect(oltIdStr, {
            host: olt.ip_address,
            port: olt.port || 23,
            username: olt.username,
            password: password,
            protocol: olt.protocol || olt.connection_type || 'telnet',
            sshPort: olt.ssh_port || 22
        });

        console.log(`[JobWorker] Connected to OLT ${oltId} (${olt.name || olt.ip_address})`);
    }
}

module.exports = BackgroundJobWorker;
