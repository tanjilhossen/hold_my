const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const CAPSOLVER_KEY = process.env.CAPSOLVER_API_KEY || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const LOGIN_URL = "https://svp-international.pacc.sa/auth/login?role=labor";

const email = process.argv[2] || "pool__485381@wafidmaster.com";
const password = process.argv[3] || "Taqamul@2723!";
const otpCode = process.argv[4] || null;

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
                try { resolve(JSON.parse(body)); } catch (e) { reject(e); }
            });
        });
        req.on('error', reject);
        req.write(data);
        req.end();
    });
}

async function solveLoginRecaptcha() {
    console.log("[CapSolver AI ⚡] Solving Google reCAPTCHA v2...");
    const createTask = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: LOGIN_URL,
            websiteKey: SITE_KEY
        }
    });

    if (!createTask || createTask.errorId !== 0) {
        throw new Error("CapSolver Task creation failed: " + JSON.stringify(createTask));
    }

    const taskId = createTask.taskId;
    for (let i = 0; i < 40; i++) {
        await delay(1500);
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: taskId
        });

        if (result.status === "ready") {
            console.log("[CapSolver AI ✅] reCAPTCHA Solved!");
            return result.solution.gRecaptchaResponse;
        }
    }
    throw new Error("CapSolver Timeout");
}

(async () => {
    console.log(`\n==========================================================`);
    console.log(`   TAQAMUL DIRECT BARS & OTP AUTO-INJECTOR BOK   `);
    console.log(`   Email   : ${email}`);
    console.log(`   Password: ${password}`);
    console.log(`   OTP Code: ${otpCode || 'Waiting for input...'}`);
    console.log(`==========================================================\n`);

    const captchaPromise = solveLoginRecaptcha();

    const browser = await puppeteer.launch({
        headless: false,
        defaultViewport: null,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--window-size=1280,800'
        ]
    });

    const pages = await browser.pages();
    const page = pages.length > 0 ? pages[0] : await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

    let capturedBearer = null;

    page.on('request', req => {
        try {
            const auth = req.headers()['authorization'] || req.headers()['Authorization'];
            if (auth && auth.startsWith('Bearer ') && !auth.includes('undefined')) {
                capturedBearer = auth.replace('Bearer ', '').trim();
                console.log("\n🎯 [SUCCESS] INTERCEPTED ACTIVE BEARER TOKEN FROM REQUEST!");
                console.log(`Bearer Token: ${capturedBearer}\n`);
            }
        } catch(e) {}
    });

    page.on('response', async res => {
        try {
            const authHeader = res.headers()['authorization'] || res.headers()['Authorization'];
            if (authHeader && authHeader.startsWith('Bearer ') && !authHeader.includes('undefined')) {
                capturedBearer = authHeader.replace('Bearer ', '').trim();
                console.log("\n🎯 [SUCCESS] INTERCEPTED ACTIVE BEARER TOKEN FROM RESPONSE!");
            }
            const url = res.url();
            if (url.includes('/api/v1/auth') || url.includes('/api/v1/individual_labor_space')) {
                try {
                    const json = await res.json();
                    const t = json?.token || json?.access_token || json?.data?.token || json?.data?.access_token;
                    if (t && typeof t === 'string' && t.length > 20) {
                        capturedBearer = t;
                        console.log("\n🎯 [SUCCESS] INTERCEPTED BEARER TOKEN FROM JSON!");
                    }
                } catch(e) {}
            }
        } catch(e) {}
    });

    console.log("[Token Bot] Navigating to Taqamul Login Page...");
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });
    try { await page.bringToFront(); } catch(e) {}

    try {
        await page.waitForSelector('input[type="password"]', { visible: true, timeout: 20000 });
    } catch(e) {}

    console.log(`[Token Bot] Filling Email (${email}) & Password...`);
    await page.evaluate((em, pw) => {
        function setVal(el, val) {
            if (!el) return;
            el.focus();
            const proto = Object.getPrototypeOf(el);
            const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
            if (setter) setter.call(el, val);
            else el.value = val;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const emailEl = document.querySelector('input[placeholder*="email" i], input[type="text"]');
        const passEl = document.querySelector('input[type="password"]');
        if (emailEl) setVal(emailEl, em);
        if (passEl) setVal(passEl, pw);
    }, email, password);

    console.log("[Token Bot] Awaiting CapSolver AI solution...");
    const captchaToken = await captchaPromise;

    console.log("[Token Bot] Injecting solved reCAPTCHA...");
    await page.evaluate((t) => {
        document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(el => {
            el.value = t;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        document.querySelectorAll('*').forEach(el => {
            if (el.__vue__) {
                const v = el.__vue__;
                if (v.recaptchaResponse !== undefined) v.recaptchaResponse = t;
                if (v.recaptchaStatus !== undefined) v.recaptchaStatus = 'ready';
            }
        });
    }, captchaToken);

    await delay(500);

    console.log("[Token Bot] Submitting Login Form via Native Click...");
    try {
        const btn = await page.$('[data-test-id="loginInit"], button[type="submit"], .el-button--primary, button.svp-btn');
        if (btn) {
            await btn.focus();
            await btn.click();
        }
    } catch(e) {}

    await page.evaluate(() => {
        const btn = document.querySelector('[data-test-id="loginInit"], button[type="submit"], .el-button--primary, button.svp-btn');
        if (btn) btn.click();
    });

    console.log("\n[Token Bot] Waiting 5s for OTP Challenge / Response Page...");
    await delay(5000);

    // If OTP code is provided directly in arguments
    if (otpCode) {
        console.log(`[Token Bot] 🔑 Injecting User-Provided OTP (${otpCode}) into page...`);
        await page.evaluate((code) => {
            function setVal(el, val) {
                if (!el) return;
                el.focus();
                const proto = Object.getPrototypeOf(el);
                const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                if (setter) setter.call(el, val);
                else el.value = val;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const inputs = Array.from(document.querySelectorAll('input[maxlength="1"]'));
            if (inputs.length >= 6) {
                for (let i = 0; i < 6; i++) setVal(inputs[i], code[i]);
            } else {
                const single = document.querySelector('input[placeholder*="OTP" i], input[maxlength="6"], input[type="text"]');
                if (single) setVal(single, code);
            }
        }, otpCode);

        // Native keyboard typing fallback
        const splitEls = await page.$$('input[maxlength="1"]');
        if (splitEls && splitEls.length >= 6) {
            for (let d = 0; d < 6; d++) {
                await splitEls[d].focus();
                await page.keyboard.press(otpCode[d]);
            }
        }

        console.log("[Token Bot] Submitting OTP Form...");
        await page.evaluate(() => {
            const btn = document.querySelector('button[type="submit"], .svp-btn, .el-button--primary');
            if (btn) btn.click();
        });
    } else {
        console.log("----------------------------------------------------------");
        console.log(" 📩 OTP REQUEST SENT TO TAQAMUL! ");
        console.log(" PLEASE PROVIDE THE 6-DIGIT OTP HERE IN CHAT.");
        console.log("----------------------------------------------------------");
    }

    // Monitor for Bearer Token capture after OTP submission
    for (let i = 0; i < 180; i++) {
        if (capturedBearer) {
            console.log("\n==========================================================");
            console.log(" SUCCESS_TOKEN:" + capturedBearer);
            console.log("==========================================================\n");
            // Auto-save into database
            try {
                const phpCmd = `D:\\xampp\\php\\php.exe -r "require 'vendor/autoload.php'; \\$app = require_once 'bootstrap/app.php'; \\$app->make(Illuminate\\\\Contracts\\\\Console\\\\Kernel::class)->bootstrap(); \\\\App\\\\Models\\\\Setting::set('slot_checker_manual_token', '${capturedBearer}'); \\$accs = json_decode(\\\\App\\\\Models\\\\Setting::get('slot_checker_pool_accounts', '[]'), true) ?: []; if(!empty(\\$accs)){ \\$accs[0]['token'] = '${capturedBearer}'; \\$accs[0]['status'] = 'active'; \\\\App\\\\Models\\\\Setting::set('slot_checker_pool_accounts', json_encode(array_values(\\$accs))); } echo 'SAVED';"`;
                execSync(phpCmd, { cwd: 'E:\\taqamul\\slothold' });
                console.log("✅ BEARER TOKEN SAVED DIRECTLY INTO DATABASE & POOL ACCOUNT!");
            } catch(e) {
                console.error("Save error:", e.message);
            }
            break;
        }
        await delay(1000);
    }

    await browser.close();
})();
