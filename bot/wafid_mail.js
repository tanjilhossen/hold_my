const http = require('http');
const https = require('https');
const crypto = require('crypto');

class WafidMailClient {
    constructor(baseUrl = 'https://mail.wafidmaster.com', keyId = null, secretKey = null) {
        this.baseUrl = (baseUrl || 'https://mail.wafidmaster.com').replace(/\/+$/, '');
        this.keyId = keyId || process.env.WAFID_MAIL_KEY_ID || null;
        this.secretKey = secretKey || process.env.WAFID_MAIL_SECRET_KEY || null;
    }

    isConfigured() {
        return !!(this.keyId && this.secretKey);
    }

    getBaseUrlForDomain(domain) {
        if (!domain) return this.baseUrl;
        const d = domain.trim().toLowerCase();
        if (d.includes('renonx.tech')) {
            return 'https://mail.renonx.tech';
        }
        if (d.includes('wafidmaster.com')) {
            return 'https://mail.wafidmaster.com';
        }
        return this.baseUrl || 'https://mail.wafidmaster.com';
    }

    getSignedHeaders(method, path, body = null) {
        const timestamp = Math.floor(Date.now() / 1000).toString();
        const nonce = crypto.randomUUID();
        const canonicalPath = path.split('?')[0];

        let bodyStr = '';
        if (body !== null && body !== undefined && (typeof body === 'object' && Object.keys(body).length > 0)) {
            bodyStr = JSON.stringify(body);
        }

        const bodyHash = crypto.createHash('sha256').update(bodyStr).digest('hex');
        const canonicalStr = `${method.toUpperCase()}\n${canonicalPath}\n${timestamp}\n${nonce}\n${bodyHash}`;
        
        const signingKey = crypto.createHash('sha256').update(this.secretKey).digest('hex');
        const signature = crypto.createHmac('sha256', signingKey).update(canonicalStr).digest('hex');

        const headers = {
            'Accept': 'application/json',
            'X-API-Key': this.keyId,
            'X-API-Timestamp': timestamp,
            'X-API-Nonce': nonce,
            'X-API-Signature': signature,
        };

        if (bodyStr.length > 0) {
            headers['Content-Type'] = 'application/json';
        }

        return headers;
    }

    requestWithUrl(targetBaseUrl, method, path, body = null) {
        return new Promise((resolve, reject) => {
            const baseUrl = (targetBaseUrl || this.baseUrl).replace(/\/+$/, '');
            const urlStr = `${baseUrl}${path}`;
            const url = new URL(urlStr);

            const headers = this.getSignedHeaders(method, path, body);
            const client = url.protocol === 'https:' ? https : http;

            const options = {
                hostname: url.hostname,
                port: url.port || (url.protocol === 'https:' ? 443 : 80),
                path: url.pathname + url.search,
                method: method.toUpperCase(),
                headers: headers,
                timeout: 10000
            };

            const req = client.request(options, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    let parsed = data;
                    try {
                        parsed = JSON.parse(data);
                    } catch (e) {}
                    resolve({
                        status: res.statusCode,
                        headers: res.headers,
                        data: parsed
                    });
                });
            });

            req.on('error', (err) => reject(err));
            req.on('timeout', () => {
                req.destroy();
                reject(new Error('Request timeout'));
            });

            if (body !== null && body !== undefined) {
                const bodyStr = typeof body === 'string' ? body : JSON.stringify(body);
                req.write(bodyStr);
            }
            req.end();
        });
    }

    request(method, path, body = null) {
        return this.requestWithUrl(this.baseUrl, method, path, body);
    }

    async createMailbox(name, domain = 'renonx.tech') {
        const cleanName = name.replace(/[^a-zA-Z0-9_\-]/g, '').toLowerCase();
        const path = `/api/v1/mailboxes?domain=${encodeURIComponent(domain)}`;
        
        if (!this.isConfigured()) {
            return {
                id: cleanName,
                name: cleanName,
                email: `${cleanName}@${domain}`,
                status: 'active'
            };
        }

        try {
            const targetBaseUrl = this.getBaseUrlForDomain(domain);
            const res = await this.requestWithUrl(targetBaseUrl, 'POST', path, { name: cleanName, domain: domain });
            if (res.status === 200 || res.status === 201) {
                return res.data;
            }
        } catch (e) {
            console.error('[WafidMail] createMailbox error:', e.message);
        }

        return {
            name: cleanName,
            email: `${cleanName}@${domain}`
        };
    }

    async getMessages(mailboxName) {
        const cleanName = mailboxName.replace(/@.*$/, '');
        const domain = mailboxName.includes('@') ? mailboxName.split('@')[1].trim().toLowerCase() : 'wafidmaster.com';
        const fullEmail = mailboxName.includes('@') ? mailboxName.trim() : `${cleanName}@${domain}`;

        if (!this.isConfigured()) {
            return [];
        }

        const primaryBaseUrl = this.getBaseUrlForDomain(domain);
        const altBaseUrl = domain.includes('renonx') ? 'https://mail.wafidmaster.com' : 'https://mail.renonx.tech';
        
        const pathsToTry = [
            `/api/v1/mailboxes/${encodeURIComponent(fullEmail)}/messages`,
            `/api/v1/mailboxes/${encodeURIComponent(fullEmail)}/messages?domain=${encodeURIComponent(domain)}`,
            `/api/v1/mailboxes/${cleanName}/messages`,
            `/api/v1/mailboxes/${cleanName}/messages?domain=${encodeURIComponent(domain)}`
        ];

        const serversToTry = Array.from(new Set([primaryBaseUrl, this.baseUrl, altBaseUrl].filter(Boolean)));

        for (const serverUrl of serversToTry) {
            for (const p of pathsToTry) {
                try {
                    const res = await this.requestWithUrl(serverUrl, 'GET', p);
                    if (res.status === 200 && res.data) {
                        const msgs = res.data.messages || res.data.data || res.data;
                        if (Array.isArray(msgs) && msgs.length > 0) {
                            return msgs;
                        }
                    }
                } catch (e) {}
            }
        }

        return [];
    }

    async getMessageDetail(messageId, domain = null) {
        const path = `/api/v1/messages/${messageId}`;

        if (!this.isConfigured()) {
            return null;
        }

        try {
            const primaryBaseUrl = domain ? this.getBaseUrlForDomain(domain) : this.baseUrl;
            let res = await this.requestWithUrl(primaryBaseUrl, 'GET', path);
            if (res.status === 200 && res.data) {
                return res.data;
            }
            if (primaryBaseUrl !== this.baseUrl) {
                res = await this.requestWithUrl(this.baseUrl, 'GET', path);
                if (res.status === 200 && res.data) {
                    return res.data;
                }
            }
        } catch (e) {
            console.error('[WafidMail] getMessageDetail error:', e.message);
        }

        return null;
    }

    async waitForOtp(mailboxName, timeoutSec = 45, pollIntervalMs = 1200, minTimestamp = 0) {
        const cleanName = mailboxName.replace(/@.*$/, '');
        const domain = mailboxName.includes('@') ? mailboxName.split('@')[1].trim().toLowerCase() : 'renonx.tech';
        const fullEmail = `${cleanName}@${domain}`;
        
        console.log(`[WafidMail Engine] 🔄 Domain-Aware Polling fresh OTP for ${fullEmail} (Domain: ${domain})...`);
        try {
            await this.createMailbox(cleanName, domain);
        } catch(e) {}
        
        const startTime = Date.now();
        const minTime = minTimestamp > 0 ? minTimestamp : (startTime - 120000);

        while ((Date.now() - startTime) < timeoutSec * 1000) {
            try {
                const messages = await this.getMessages(mailboxName);
                if (messages && messages.length > 0) {
                    for (let i = 0; i < Math.min(5, messages.length); i++) {
                        const msg = messages[i];
                        
                        // Parse message timestamp
                        const msgTime = msg.created_at ? new Date(msg.created_at).getTime() : 
                                       (msg.date ? new Date(msg.date).getTime() : 
                                       (msg.timestamp ? (msg.timestamp * (msg.timestamp < 10000000000 ? 1000 : 1)) : Date.now()));

                        // Strictly reject any email received before OTP page load!
                        if (minTime > 0 && msgTime < minTime) {
                            console.log(`[WafidMail Engine] ⏳ Skipping old email (${new Date(msgTime).toLocaleTimeString()} < ${new Date(minTime).toLocaleTimeString()})...`);
                            continue;
                        }

                        if (msg.id) {
                            const detail = await this.getMessageDetail(msg.id, domain);
                            const d = (detail && (detail.data || detail.message || detail)) || {};
                            const fullText = [
                                d.text_body, d.text, d.html_body, d.html, d.text_content, d.body, d.snippet, d.subject,
                                msg.subject, msg.snippet, msg.text
                            ].filter(Boolean).join(' ');
                            
                            const matches = fullText.match(/\b([0-9]{6})\b/g) || [];
                            const validOtps = matches.filter(code => !code.startsWith('2026') && !code.startsWith('2025') && code !== '808080' && code !== '000000');
                            if (validOtps.length > 0) {
                                const otp = validOtps[0];
                                console.log(`\n🎯 [WafidMail Engine] 🔥 FRESH LATEST OTP CAPTURED FOR ${fullEmail}: ${otp}! 🔥\n`);
                                return otp;
                            }
                        }
                    }
                }
            } catch(e) {
                console.error('[WafidMail Polling Error]:', e.message);
            }

            await new Promise(r => setTimeout(r, pollIntervalMs));
        }

        console.log(`[WafidMail Engine] ⚠️ Timeout waiting for fresh OTP on ${fullEmail}`);
        return null;
    }
}

module.exports = {
    WafidMailClient
};
