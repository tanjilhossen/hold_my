const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');
const path = require('path');
const fs = require('fs');
const { WafidMailClient } = require('./wafid_mail');

const wafidClient = new WafidMailClient(
    process.env.WAFID_MAIL_BASE_URL || 'https://mail.wafidmaster.com',
    process.env.WAFID_MAIL_KEY_ID,
    process.env.WAFID_MAIL_SECRET_KEY
);

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const CAPSOLVER_KEY = process.env.CAPSOLVER_API_KEY || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const LOGIN_URL = "https://svp-international.pacc.sa/auth/login?role=labor";

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

// 🧩 Solve Login reCAPTCHA v2 using CapSolver
async function solveLoginRecaptcha() {
    console.log("[CapSolver AI] Solving Google reCAPTCHA v2 for Taqamul Labor Login...");
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
            console.log("[CapSolver AI] ✅ reCAPTCHA Token received successfully!");
            return result.solution.gRecaptchaResponse;
        }
        if (result.status === "failed") {
            throw new Error("CapSolver task failed: " + JSON.stringify(result));
        }
    }
    throw new Error("CapSolver Timeout (60s)");
}

// 📩 Fetch OTP for Login
async function fetchLoginOtp(email, mailPassword, maxWaitSec = 60) {
    console.log(`[OTP Fetcher] 🔍 Monitoring inbox for login OTP (${email})...`);
    const cleanEmail = email.trim().toLowerCase();

    // 1. Private Mail Server (@wafidmaster.com & @renonx.tech)
    if (cleanEmail.endsWith('@wafidmaster.com') || cleanEmail.endsWith('@renonx.tech')) {
        try {
            const otp = await wafidClient.waitForOtp(cleanEmail, maxWaitSec, 1500);
            if (otp) {
                console.log(`[OTP Fetcher] 🎯 OTP Captured from Wafid Mail API: ${otp}`);
                return otp;
            }
        } catch(e) {
            console.error('[OTP Fetcher Error]:', e.message);
        }
    }

    // 2. YOPmail
    const emailUser = cleanEmail.split('@')[0];
    try {
        const https = require('https');
        for (let i = 0; i < maxWaitSec / 3; i++) {
            await delay(3000);
            const body = await new Promise((resolve) => {
                https.get(`https://yopmail.com/en/rss?login=${encodeURIComponent(emailUser)}`, (res) => {
                    let d = '';
                    res.on('data', chunk => d += chunk);
                    res.on('end', () => resolve(d));
                }).on('error', () => resolve(''));
            });
            const m = body.match(/\b([0-9]{6})\b/);
            if (m) {
                console.log(`[OTP Fetcher] 🎯 OTP Captured from YOPmail: ${m[1]}`);
                return m[1];
            }
        }
    } catch (e) {}

    return null;
}

async function runAutoLogin(candidateData = {}) {
    console.log("==========================================================");
    console.log("   TAQAMUL LABOR PORTAL AUTO-LOGIN & OTP INJECTION BOT    ");
    console.log("==========================================================\n");

    const email = (candidateData.email || "").trim();
    const password = (candidateData.password || "Taqamul@2026!").trim();
    const tempMailPassword = candidateData.temp_mail_password || "";

    if (!email) {
        console.error("❌ Error: No email provided for login.");
        return { success: false, error: "Candidate email is missing." };
    }

    console.log(`>>> Target Portal: ${LOGIN_URL}`);
    console.log(`    Login Email  : ${email}`);
    console.log(`    Password     : ${password}\n`);

    // Start pre-solving captcha concurrently in background
    const captchaPromise = solveLoginRecaptcha().catch(e => {
        console.error("Captcha solve error:", e.message);
        return null;
    });

    console.log("[Browser Engine] Launching Chrome Visual On-Screen Browser for Login...");
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: false, // Visual On-Screen so user can interact and stay logged in!
        userDataDir: path.join(__dirname, 'chrome_cache_data'),
        defaultViewport: null,
        args: [
            '--start-maximized',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--no-first-run',
            '--no-default-browser-check'
        ]
    });

    try {
        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

        console.log(`[Browser Engine] Navigating to ${LOGIN_URL}...`);
        await page.goto(LOGIN_URL, { waitUntil: 'networkidle2', timeout: 60000 });
        await delay(2000);

        // 1. Fill Email
        console.log("[Browser Engine] Entering Email...");
        const emailSelectors = ['input[type="email"]', 'input[name="email"]', 'input[name="username"]', 'input[placeholder*="email" i]', '#email', '#username'];
        let emailFilled = false;
        for (const sel of emailSelectors) {
            const el = await page.$(sel);
            if (el) {
                await el.click({ clickCount: 3 });
                await el.type(email, { delay: 30 });
                emailFilled = true;
                break;
            }
        }
        if (!emailFilled) {
            await page.evaluate((em) => {
                const inp = document.querySelector('input[type="email"], input[name="email"], input[name="username"], input[placeholder*="email" i]');
                if (inp) {
                    inp.value = em;
                    inp.dispatchEvent(new Event('input', { bubbles: true }));
                    inp.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, email);
        }

        // 2. Fill Password
        console.log("[Browser Engine] Entering Password...");
        const passSelectors = ['input[type="password"]', 'input[name="password"]', '#password'];
        let passFilled = false;
        for (const sel of passSelectors) {
            const el = await page.$(sel);
            if (el) {
                await el.click({ clickCount: 3 });
                await el.type(password, { delay: 30 });
                passFilled = true;
                break;
            }
        }
        if (!passFilled) {
            await page.evaluate((pw) => {
                const inp = document.querySelector('input[type="password"], input[name="password"]');
                if (inp) {
                    inp.value = pw;
                    inp.dispatchEvent(new Event('input', { bubbles: true }));
                    inp.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, password);
        }

        // 3. Inject Solved reCAPTCHA Token
        console.log("[Browser Engine] Awaiting solved reCAPTCHA token from CapSolver...");
        const token = await captchaPromise;
        if (token) {
            console.log("[Browser Engine] Injecting reCAPTCHA token into login page...");
            await page.evaluate((t) => {
                document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(ta => {
                    ta.value = t;
                    ta.innerHTML = t;
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                    ta.dispatchEvent(new Event('change', { bubbles: true }));
                });

                // Vue / Web Component state update
                document.querySelectorAll('*').forEach(el => {
                    if (el.__vue__) {
                        const v = el.__vue__;
                        if (v.recaptchaResponse !== undefined) v.recaptchaResponse = t;
                        if (v.recaptchaStatus !== undefined) v.recaptchaStatus = 'ready';
                        if (v.successCaptcha) try { v.successCaptcha(t); } catch (e) {}
                    }
                });
            }, token);
        }

        await delay(1000);

        // 4. Click Submit / Login button
        console.log("[Browser Engine] Submitting Login Form...");
        await page.evaluate(() => {
            const btn = document.querySelector('button[type="submit"], form button, .btn-primary, button:has(span)');
            if (btn) btn.click();
        });

        console.log("[Browser Engine] Form submitted. Monitoring for OTP verification challenge...");
        await delay(3500);

        // 5. Check if OTP is requested (single input or multi-digit inputs)
        for (let check = 0; check < 20; check++) {
            const isOtpPage = await page.evaluate(() => {
                const text = document.body ? document.body.innerText : '';
                const hasOtpInput = document.querySelector('input[placeholder*="OTP" i], input[maxlength="6"], input[name*="otp" i], input[name*="code" i], .otp-input, input[maxlength="1"]') !== null;
                return hasOtpInput || text.includes('verification code') || text.includes('OTP') || text.includes('رمز التحقق');
            });

            if (isOtpPage) {
                console.log("[Browser Engine] 📩 Login OTP prompt detected! Fetching OTP from mailbox...");
                const otpCode = await fetchLoginOtp(email, tempMailPassword, 60);

                if (otpCode) {
                    console.log(`[Browser Engine] ⚡ Entering Login OTP: ${otpCode}...`);

                    // Try entering 6 separate OTP boxes if present
                    const splitInputs = await page.$$('input[maxlength="1"]');
                    if (splitInputs && splitInputs.length === 6) {
                        for (let d = 0; d < 6; d++) {
                            await splitInputs[d].type(otpCode[d], { delay: 50 });
                        }
                    } else {
                        // Standard single OTP input box
                        const otpInput = await page.$('input[placeholder*="OTP" i], input[maxlength="6"], input[name*="otp" i], input[name*="code" i], input.otp-input, input[type="text"]');
                        if (otpInput) {
                            await otpInput.click();
                            await otpInput.type(otpCode, { delay: 40 });
                        } else {
                            await page.evaluate((code) => {
                                const inputs = Array.from(document.querySelectorAll('input'));
                                const target = inputs.find(i => i.placeholder.includes('OTP') || i.maxLength === 6 || i.type === 'number' || i.type === 'text');
                                if (target) {
                                    target.value = code;
                                    target.dispatchEvent(new Event('input', { bubbles: true }));
                                    target.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            }, otpCode);
                        }
                    }

                    await delay(1000);

                    // Click Confirm / Verify OTP button
                    await page.evaluate(() => {
                        const btns = Array.from(document.querySelectorAll('button'));
                        const verifyBtn = btns.find(b => {
                            const t = b.innerText.toLowerCase();
                            return t.includes('verify') || t.includes('confirm') || t.includes('submit') || t.includes('تحقق') || t.includes('دخول');
                        });
                        if (verifyBtn) verifyBtn.click();
                    });

                    console.log("[Browser Engine] 🎉 Login OTP Submitted! Landing into Taqamul Dashboard...");
                    await delay(4000);
                    break;
                }
            }
            await delay(1500);
        }

        console.log("✅ Auto-Login sequence finished! Browser session is active on screen.");
        return {
            success: true,
            message: "Auto-Login completed. Browser session is active on screen."
        };

    } catch (err) {
        console.error("❌ Auto-Login Error:", err.message);
        return { success: false, error: err.message };
    }
}

// CLI Execution Support
if (require.main === module) {
    const args = process.argv.slice(2);
    let candidateData = {};
    if (args.length > 0 && fs.existsSync(args[0])) {
        try {
            candidateData = JSON.parse(fs.readFileSync(args[0], 'utf8'));
        } catch (e) {
            console.error("Error reading JSON:", e.message);
        }
    }
    runAutoLogin(candidateData).then(res => {
        console.log("\nFINAL_RESULT:" + JSON.stringify(res));
    });
}

module.exports = { runAutoLogin };
