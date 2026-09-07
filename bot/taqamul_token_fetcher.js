const path = require('path');
module.paths.push(path.join(__dirname, 'node_modules'));
module.paths.push(path.join(__dirname, '..', 'node_modules'));
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');
const fs = require('fs');
const { WafidMailClient } = require('./wafid_mail');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const LOGIN_URL = "https://svp-international.pacc.sa/auth/login?role=labor";
const LOG_FILE = path.join(__dirname, '..', 'storage', 'app', 'bot_login_stream.log');

function logStream(msg) {
    console.log(msg);
    try {
        fs.appendFileSync(LOG_FILE, msg + '\n');
    } catch(e) {}
}

const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

function postJson(url, payload) {
    return new Promise((resolve, reject) => {
        const data = JSON.stringify(payload);
        const u = new URL(url);
        const req = https.request({
            hostname: u.hostname,
            port: 443,
            path: u.pathname,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': data.length
            }
        }, (res) => {
            let body = '';
            res.on('data', d => body += d);
            res.on('end', () => {
                try { resolve(JSON.parse(body)); } catch (e) { reject(new Error("Invalid JSON: " + body)); }
            });
        });
        req.on('error', reject);
        req.write(data);
        req.end();
    });
}

// 🧩 Turbo-Fast CapSolver reCAPTCHA v2 Solver with 300ms Sub-Second Polling
async function solveLoginRecaptcha(capsolverKey) {
    const startTime = Date.now();
    console.log("[CapSolver AI ⚡] Launching Parallel Google reCAPTCHA v2 Solver...");
    
    const createTask = await postJson("https://api.capsolver.com/createTask", {
        clientKey: capsolverKey,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: LOGIN_URL,
            websiteKey: SITE_KEY
        }
    });

    if (!createTask || createTask.errorId !== 0) {
        throw new Error("CapSolver Task Error: " + JSON.stringify(createTask));
    }

    const taskId = createTask.taskId;
    for (let i = 0; i < 150; i++) {
        await delay(300); // 300ms polling
        const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);

        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: capsolverKey,
            taskId: taskId
        });

        if (result.status === "ready") {
            console.log(`[CapSolver AI ✅] reCAPTCHA Solved in ${elapsed}s!`);
            return result.solution.gRecaptchaResponse;
        }
        if (result.status === "failed") {
            throw new Error("CapSolver Failed: " + JSON.stringify(result));
        }
    }
    throw new Error("CapSolver Timeout (45s)");
}

// Fetch OTP strictly received AFTER form submission timestamp
async function fetchLoginOtp(email, wafidClient, maxWaitSec = 45, minTimestamp = 0) {
    if (email.toLowerCase().includes('@wafidmaster.com') || email.toLowerCase().includes('@renonx.tech')) {
        console.log(`[WafidMail API] 📩 Polling FRESH OTP for: ${email} (Sent at: ${minTimestamp ? new Date(minTimestamp).toLocaleTimeString() : 'Now'})...`);
        if (typeof wafidClient.waitForOtp === 'function') {
            return await wafidClient.waitForOtp(email, maxWaitSec, 1000, minTimestamp);
        }
    }
    return null;
}

function postJsonWithHeaders(urlStr, payload, customHeaders = {}, retries = 2) {
    return new Promise((resolve, reject) => {
        const data = JSON.stringify(payload);
        const u = new URL(urlStr);
        const req = https.request({
            hostname: u.hostname,
            port: 443,
            path: u.pathname + u.search,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(data),
                'Connection': 'close',
                ...customHeaders
            }
        }, (res) => {
            let body = '';
            res.on('data', d => body += d);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(body));
                } catch (e) {
                    reject(new Error(`Invalid JSON response (Status ${res.statusCode}): ${body}`));
                }
            });
        });
        req.on('error', (err) => {
            if (retries > 0) {
                setTimeout(() => {
                    postJsonWithHeaders(urlStr, payload, customHeaders, retries - 1).then(resolve).catch(reject);
                }, 1000);
            } else {
                reject(err);
            }
        });
        req.write(data);
        req.end();
    });
}

// ⚡ Ultra-Fast Pure HTTP Login Engine (Bypasses Puppeteer Chrome overhead)
async function fetchBearerTokenFastHttp(config) {
    const email = config.email;
    const password = config.password;
    const capsolverKey = config.capsolver_api_key || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
    
    const wafidClient = new WafidMailClient(
        config.wafid_mail_base_url || process.env.WAFID_MAIL_BASE_URL || 'https://mail.wafidmaster.com',
        config.wafid_mail_key_id || process.env.WAFID_MAIL_KEY_ID || 'ak_live_f845898cbeb87d63e21d04a6',
        config.wafid_mail_secret_key || process.env.WAFID_MAIL_SECRET_KEY || 'sk_live_dd00dc26382465e37c31b246e37b3f345ff5c874d1ce0426'
    );

    logStream(`[Token Bot HTTP ⚡] Starting Direct Pure HTTP Login for: ${email}`);

    const requestStartTime = Date.now();

    // Step 1: Request OTP dispatch via POST /api/v1/sessions/login?locale=en (Direct Captcha-Free)
    const loginPayload = {
        user: {
            login: email,
            password: password,
            otp_method: "email",
            fe_app: "legislator",
            recaptcha_response: ""
        }
    };

    logStream(`[Token Bot HTTP ⚡] Sending POST /api/v1/sessions/login...`);
    const loginRes = await postJsonWithHeaders("https://svp-international-api.pacc.sa/api/v1/sessions/login?locale=en", loginPayload, {
        'Host': 'svp-international-api.pacc.sa',
        'X-Tenant-Name': 'svp-international',
        'Content-Type': 'application/json',
        'Accept': 'application/json, text/plain, */*',
        'Origin': 'https://svp-international.pacc.sa',
        'Referer': 'https://svp-international.pacc.sa/',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'
    });

    logStream(`[Token Bot HTTP ⚡] Step 1 Response: ${JSON.stringify(loginRes)}`);

    if (loginRes?.access_payload?.access) {
        logStream(`[Token Bot HTTP 🔑] Direct login returned Bearer Token without OTP!`);
        return loginRes.access_payload.access;
    }

    if (!loginRes || !loginRes.required_2fa) {
        throw new Error(`Login step 1 failed: ${JSON.stringify(loginRes)}`);
    }

    await delay(1500);

    // Step 3: Fetch fresh OTP code from WafidMail API
    logStream(`[Token Bot HTTP 📩] Waiting for fresh OTP for ${email}...`);
    const otpCode = await fetchLoginOtp(email, wafidClient, 35, requestStartTime);
    if (!otpCode) {
        throw new Error(`Failed to receive OTP for ${email} within timeout.`);
    }
    logStream(`[Token Bot HTTP 📩] Received fresh OTP code: ${otpCode}`);

    // Step 4: Submit OTP via POST /api/v1/sessions/otp?locale=en
    const otpPayload = {
        user: {
            login: email,
            password: password,
            otp_attempt: String(otpCode).trim(),
            fe_app: "legislator",
            otp_method: "email"
        }
    };

    logStream(`[Token Bot HTTP ⚡] Submitting OTP via POST /api/v1/sessions/otp...`);
    const otpRes = await postJsonWithHeaders("https://svp-international-api.pacc.sa/api/v1/sessions/otp?locale=en", otpPayload, {
        'Host': 'svp-international-api.pacc.sa',
        'X-Tenant-Name': 'svp-international',
        'Content-Type': 'application/json',
        'Accept': 'application/json, text/plain, */*',
        'Origin': 'https://svp-international.pacc.sa',
        'Referer': 'https://svp-international.pacc.sa/',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'
    });

    logStream(`[Token Bot HTTP ⚡] Step 2 Response: ${JSON.stringify(otpRes)}`);

    const bearerToken = otpRes?.access_payload?.access;
    if (bearerToken && typeof bearerToken === 'string' && bearerToken.length > 20) {
        logStream(`[Token Bot HTTP 🔑] Fast HTTP Login Success! Token acquired!`);
        return bearerToken;
    }

    throw new Error(`OTP Verification failed: ${JSON.stringify(otpRes)}`);
}

// Autonomous On-Screen Visual Login & Token Extractor
async function fetchBearerToken(config) {
    const email = config.email;
    const password = config.password;
    const capsolverKey = config.capsolver_api_key || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
    
    // ⚡ STEP 1: Attempt Ultra-Fast Pure HTTP Login (< 10 seconds, zero Chrome browser overhead)
    try {
        logStream(`[Token Bot ⚡] Attempting Pure Fast HTTP Login for: ${email}...`);
        const fastToken = await fetchBearerTokenFastHttp(config);
        if (fastToken && typeof fastToken === 'string' && fastToken.length > 20) {
            logStream(`[Token Bot 🚀] PURE HTTP FAST LOGIN SUCCESS FOR ${email}! Token acquired!`);
            return { success: true, email: email, token: fastToken };
        }
    } catch (httpErr) {
        logStream(`[Token Bot ⚠️] Fast HTTP Login attempt failed: ${httpErr.message}. Falling back to Visual Chrome Browser mode...`);
    }

    // 🚀 STEP 2: Fallback to Puppeteer Chrome Visual Browser Mode
    let browser = null;
    let capturedBearer = null;

    const wafidClient = new WafidMailClient(
        config.wafid_mail_base_url || process.env.WAFID_MAIL_BASE_URL || 'https://mail.wafidmaster.com',
        config.wafid_mail_key_id || process.env.WAFID_MAIL_KEY_ID || 'ak_live_f845898cbeb87d63e21d04a6',
        config.wafid_mail_secret_key || process.env.WAFID_MAIL_SECRET_KEY || 'sk_live_dd00dc26382465e37c31b246e37b3f345ff5c874d1ce0426'
    );

    try {
        logStream(`[Token Bot] Starting Fast Visual Login for: ${email}`);

        // 🚀 START CAPSOLVER AI SOLVER IMMEDIATELY IN PARALLEL WITH PAGE LOAD & FORM FILLING
        const captchaPromise = solveLoginRecaptcha(capsolverKey);

        browser = await puppeteer.launch({
            executablePath: fs.existsSync(CHROME_PATH) ? CHROME_PATH : undefined,
            headless: false, // Visual Chrome Mode for 100% login success
            defaultViewport: null,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--disable-infobars',
                '--window-position=80,80',
                '--window-size=1280,800'
            ]
        });

        // Use the single initial tab (Prevents duplicate blank tab)
        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

        function isValidBearerToken(t) {
            if (!t || typeof t !== 'string') return false;
            const clean = t.replace(/^Bearer\s+/i, '').trim();
            if (clean.startsWith('{') || clean.startsWith('[') || clean.includes('portal') || clean.includes('"') || clean.includes('path')) {
                return false;
            }
            return clean.length > 20;
        }

        // Intercept request & response headers for post-OTP authenticated Bearer Token
        page.on('request', req => {
            try {
                const auth = req.headers()['authorization'] || req.headers()['Authorization'];
                if (auth && auth.startsWith('Bearer ') && !auth.includes('undefined')) {
                    const tStr = auth.replace('Bearer ', '').trim();
                    if (isValidBearerToken(tStr)) {
                        capturedBearer = tStr;
                        logStream("[Token Bot 🔑] Intercepted Bearer Token from Request Headers!");
                    }
                }
            } catch(e) {}
        });

        page.on('response', async res => {
            try {
                const url = res.url();
                if (url.includes('/api/v1/auth') || url.includes('/api/v1/individual_labor_space') || (isOtpSubmitted && url.includes('/api/v1/'))) {
                    const authHeader = res.headers()['authorization'] || res.headers()['Authorization'];
                    if (authHeader && authHeader.startsWith('Bearer ') && !authHeader.includes('undefined')) {
                        const tokenStr = authHeader.replace('Bearer ', '').trim();
                        if (isValidBearerToken(tokenStr)) {
                            capturedBearer = tokenStr;
                            logStream("[Token Bot 🔑] Intercepted Bearer Token from Response Headers!");
                        }
                    }
                    try {
                        const json = await res.json();
                        const t = json?.token || json?.access_token || json?.data?.token || json?.data?.access_token || json?.data?.user?.token || json?.result?.token;
                        if (isValidBearerToken(t)) {
                            capturedBearer = t;
                            logStream("[Token Bot 🔑] Intercepted Bearer Token from Response JSON!");
                        }
                    } catch (e) {}
                }
            } catch (e) {}
        });

        // Navigate to Login Page with Retry Mechanism
        let navSuccess = false;
        for (let attempt = 1; attempt <= 3; attempt++) {
            try {
                logStream(`[Token Bot] Navigating to Taqamul Login Page (Attempt ${attempt}/3)...`);
                await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: 35000 });
                navSuccess = true;
                break;
            } catch (e) {
                logStream(`[Token Bot ⚠️] Navigation Attempt ${attempt} error: ${e.message}. Retrying in 2s...`);
                await delay(2000);
            }
        }
        if (!navSuccess) {
            throw new Error("Failed to connect to Taqamul login server after 3 attempts.");
        }
        try { await page.bringToFront(); } catch(e) {}
        
        // Wait until Element UI inputs are rendered & visible
        try {
            await page.waitForSelector('input[type="password"]', { visible: true, timeout: 20000 });
        } catch(e) {}

        // 1. Fill Email & Password with native OS keyboard events for Element-UI reactivity
        logStream(`[Token Bot] Filling Email (${email}) & Password...`);
        const emailSelector = 'input[placeholder*="email" i], input[type="text"]';
        const passSelector = 'input[type="password"]';

        try {
            const emailEl = await page.$(emailSelector);
            if (emailEl) {
                await emailEl.focus();
                await emailEl.click({ clickCount: 3 });
                await page.keyboard.press('Backspace');
                await page.keyboard.type(email, { delay: 15 });
            }
        } catch (e) {}

        try {
            const passEl = await page.$(passSelector);
            if (passEl) {
                await passEl.focus();
                await passEl.click({ clickCount: 3 });
                await page.keyboard.press('Backspace');
                await page.keyboard.type(password, { delay: 15 });
            }
        } catch (e) {}

        // Native value injection ensuring Vue reactivity for both fields
        await page.evaluate((em, pw) => {
            function setNativeValue(element, value) {
                if (!element) return;
                element.focus();
                const valueSetter = Object.getOwnPropertyDescriptor(element, 'value')?.set;
                const prototype = Object.getPrototypeOf(element);
                const prototypeValueSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
                if (prototypeValueSetter && valueSetter !== prototypeValueSetter) {
                    prototypeValueSetter.call(element, value);
                } else if (valueSetter) {
                    valueSetter.call(element, value);
                } else {
                    element.value = value;
                }
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
                element.dispatchEvent(new Event('blur', { bubbles: true }));
            }

            const emailInput = document.querySelector('input[placeholder*="email" i], input[type="text"]');
            const passInput = document.querySelector('input[type="password"]');
            if (emailInput) setNativeValue(emailInput, em);
            if (passInput) setNativeValue(passInput, pw);
        }, email, password);

        // 2. Await Captcha Token (Already solving in background)
        logStream("[Token Bot] Awaiting CapSolver AI solution...");
        const captchaToken = await captchaPromise;

        // Inject solved Captcha
        logStream("[Token Bot] Injecting solved reCAPTCHA token into page...");
        await page.evaluate((t) => {
            document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(el => {
                el.value = t;
                el.innerHTML = t;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
            if (window.___grecaptcha_cfg && window.___grecaptcha_cfg.clients) {
                for (const c in window.___grecaptcha_cfg.clients) {
                    const client = window.___grecaptcha_cfg.clients[c];
                    if (!client) continue;
                    for (const key in client) {
                        const obj = client[key];
                        if (obj && typeof obj === 'object') {
                            if (typeof obj.callback === 'function') {
                                try { obj.callback(t); } catch (e) {}
                            }
                            if (typeof obj.promise === 'object' && obj.promise) {
                                for (const pk in obj.promise) {
                                    if (typeof obj.promise[pk] === 'function') {
                                        try { obj.promise[pk](t); } catch(e) {}
                                    }
                                }
                            }
                            for (const subKey in obj) {
                                if (typeof obj[subKey] === 'function') {
                                    try { obj[subKey](t); } catch (e) {}
                                }
                            }
                        }
                    }
                }
            }
            document.querySelectorAll('*').forEach(el => {
                if (el.__vue__) {
                    const v = el.__vue__;
                    if (v.recaptchaResponse !== undefined) v.recaptchaResponse = t;
                    if (v.recaptchaStatus !== undefined) v.recaptchaStatus = 'ready';
                    if (v.captchaResponse !== undefined) v.captchaResponse = t;
                    if (v.captchaToken !== undefined) v.captchaToken = t;
                    if (v.successCaptcha) try { v.successCaptcha(t); } catch (e) {}
                    if (v.onCaptchaSuccess) try { v.onCaptchaSuccess(t); } catch (e) {}
                }
            });
        }, captchaToken);

        await delay(300);

        // Record precise timestamp of form submission
        const formSubmitTimestamp = Date.now();

        // Submit form
        logStream("[Token Bot] Submitting Login Form (Sign in)...");
        try {
            await page.waitForSelector('[data-test-id="loginInit"]', { visible: true, timeout: 5000 });
            await page.click('[data-test-id="loginInit"]');
        } catch (e) {
            await page.evaluate(() => {
                const btn = document.querySelector('[data-test-id="loginInit"], button.svp-btn, .el-button--primary');
                if (btn) btn.click();
            });
        }

        await delay(1000);

        // 3. Monitor and Enter OTP Challenge
        for (let check = 0; check < 25; check++) {
            if (capturedBearer) break;

            const pageError = await page.evaluate(() => {
                const errEl = document.querySelector('.error-message, .invalid-feedback, .v-toast__text, .el-message--error, .el-form-item__error');
                const text = errEl ? errEl.innerText.trim() : (document.body ? document.body.innerText : '');
                if (text.includes('Invalid Email or Password') || text.includes('Incorrect password') || text.includes('User not found')) {
                    return 'Invalid Email or Password';
                }
                return null;
            });

            const isOtpPage = await page.evaluate(() => {
                const text = document.body ? document.body.innerText : '';
                const allInputs = Array.from(document.querySelectorAll('input'));
                const hasOtpInput = allInputs.some(i => 
                    (i.placeholder && i.placeholder.toLowerCase().includes('otp')) ||
                    (i.name && i.name.toLowerCase().includes('otp')) ||
                    (i.name && i.name.toLowerCase().includes('code')) ||
                    (i.maxLength === 6 || (i.maxLength === 1 && document.querySelectorAll('input[maxlength="1"]').length >= 4))
                );
                return hasOtpInput || text.includes('verification code') || text.includes('OTP') || text.includes('رمز التحقق');
            });

            if (isOtpPage) {
                logStream("[Token Bot] 📩 OTP challenge detected! Fetching fresh OTP from Mailbox...");
                const otp = await fetchLoginOtp(email, wafidClient, 20, 0);
                
                if (otp) {
                    logStream(`\n[Token Bot] 🎯 FRESH OTP RECEIVED: ${otp}! Auto-filling into OTP boxes...\n`);
                    
                    // Native Vue value setter for OTP digits
                    await page.evaluate((code) => {
                        function setNativeValue(element, value) {
                            if (!element) return;
                            element.focus();
                            const valueSetter = Object.getOwnPropertyDescriptor(element, 'value')?.set;
                            const prototype = Object.getPrototypeOf(element);
                            const prototypeValueSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
                            if (prototypeValueSetter && valueSetter !== prototypeValueSetter) {
                                prototypeValueSetter.call(element, value);
                            } else if (valueSetter) {
                                valueSetter.call(element, value);
                            } else {
                                element.value = value;
                            }
                            element.dispatchEvent(new Event('input', { bubbles: true }));
                            element.dispatchEvent(new Event('change', { bubbles: true }));
                            element.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: value }));
                        }

                        const splitInputs = Array.from(document.querySelectorAll('input[maxlength="1"]'));
                        if (splitInputs.length >= 6) {
                            for (let d = 0; d < 6; d++) {
                                setNativeValue(splitInputs[d], code[d]);
                            }
                        } else {
                            const single = document.querySelector('input[placeholder*="OTP" i], input[maxlength="6"], input[name*="otp" i], input[name*="code" i], .otp-input, input[type="text"]');
                            if (single) setNativeValue(single, code);
                        }
                    }, otp);

                    // Focus first box and type digits sequentially with realistic keyboard typing
                    const splitEls = await page.$$('input[maxlength="1"]');
                    if (splitEls && splitEls.length >= 6) {
                        for (let d = 0; d < 6; d++) {
                            await splitEls[d].focus();
                            await splitEls[d].click();
                            await page.keyboard.press(otp[d]);
                            await delay(40);
                        }
                    } else {
                        const singleEl = await page.$('input[placeholder*="OTP" i], input[maxlength="6"], input[name*="otp" i], input[name*="code" i], .otp-input');
                        if (singleEl) {
                            await singleEl.focus();
                            await singleEl.click();
                            await page.keyboard.type(otp, { delay: 40 });
                        }
                    }

                    await delay(600);

                    // Click Confirm / Verify OTP Button
                    logStream("[Token Bot] Submitting OTP verification...");
                    isOtpSubmitted = true;
                    await page.evaluate(() => {
                        const btns = Array.from(document.querySelectorAll('button'));
                        const verifyBtn = btns.find(b => {
                            const t = b.innerText.toLowerCase();
                            return t.includes('verify') || t.includes('confirm') || t.includes('submit') || t.includes('continue') || t.includes('تحقق') || t.includes('دخول');
                        }) || document.querySelector('button[type="submit"]');

                        if (verifyBtn) {
                            verifyBtn.disabled = false;
                            verifyBtn.removeAttribute('disabled');
                            verifyBtn.click();
                        }
                    });

                    await page.keyboard.press('Enter');

                    logStream("[Token Bot] OTP submitted. Waiting for dashboard navigation and Bearer Token...");
                    await delay(4000);
                    break;
                }
            }
            await delay(1000);
        }

        // Check storage for token (including vuex, cookies, and localStorage)
        for (let tWait = 0; tWait < 15; tWait++) {
            if (capturedBearer) break;
            capturedBearer = await page.evaluate(() => {
                const direct = localStorage.getItem('token') || localStorage.getItem('auth_token') || sessionStorage.getItem('token') || sessionStorage.getItem('auth_token');
                if (direct && direct.length > 20) return direct;

                // Check Vuex state in localStorage
                try {
                    const vuexRaw = localStorage.getItem('vuex') || localStorage.getItem('auth') || localStorage.getItem('user');
                    if (vuexRaw) {
                        const parsed = JSON.parse(vuexRaw);
                        const t = parsed?.auth?.token || parsed?.token || parsed?.user?.token || parsed?.auth?.access_token || parsed?.access_token;
                        if (t && typeof t === 'string' && t.length > 20) return t;
                    }
                } catch(e) {}

                // Check all keys in localStorage for any JWT string
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    const val = localStorage.getItem(key);
                    if (val && typeof val === 'string' && val.startsWith('eyJ') && !val.includes('portal') && !val.includes('"')) {
                        return val;
                    }
                    if (val && typeof val === 'string' && val.includes('"token":')) {
                        try {
                            const p = JSON.parse(val);
                            const t = p.token || p.access_token || p?.auth?.token;
                            if (t && typeof t === 'string' && t.startsWith('eyJ')) return t;
                        } catch(e) {}
                    }
                }
                return null;
            });
            if (capturedBearer) break;
            await delay(1000);
        }

        if (capturedBearer) {
            logStream("\n🎉 [Token Bot] Bearer token successfully acquired!\n");
            await delay(1500);
            await browser.close();
            return { success: true, email: email, token: capturedBearer };
        } else {
            await browser.close();
            return { success: false, error: 'Could not extract Bearer token after login.' };
        }

    } catch (err) {
        if (browser) await browser.close();
        logStream("❌ Token Bot Error: " + err.message);
        return { success: false, error: err.message };
    }
}

if (require.main === module) {
    const args = process.argv.slice(2);
    let config = {};

    if (args.length > 0) {
        if (fs.existsSync(args[0])) {
            try {
                config = JSON.parse(fs.readFileSync(args[0], 'utf8'));
            } catch (e) {
                console.error("Error reading JSON file:", e.message);
            }
        } else {
            try {
                config = JSON.parse(args[0]);
            } catch (e) {
                config.email = args[0];
                config.password = args[1] || '';
            }
        }
    }

    if (!config.email || !config.password) {
        console.error(JSON.stringify({ success: false, error: "Missing email or password" }));
        process.exit(1);
    }

    fetchBearerToken(config).then(res => {
        logStream("\nFINAL_TOKEN_RESULT:" + JSON.stringify(res));
    });
}

module.exports = { fetchBearerToken };
