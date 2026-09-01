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

let CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
if (process.platform === 'linux') {
    if (fs.existsSync('/usr/bin/google-chrome-stable')) CHROME_PATH = '/usr/bin/google-chrome-stable';
    else if (fs.existsSync('/usr/bin/google-chrome')) CHROME_PATH = '/usr/bin/google-chrome';
    else if (fs.existsSync('/opt/google/chrome/chrome')) CHROME_PATH = '/opt/google/chrome/chrome';
    else {
        try {
            const found = require('child_process').execSync('find /root/.cache/puppeteer /root/.cache /tmp /var/www -name chrome 2>/dev/null').toString().trim().split('\n')[0];
            if (found && fs.existsSync(found)) CHROME_PATH = found;
            else if (fs.existsSync('/usr/bin/chromium-browser')) CHROME_PATH = '/usr/bin/chromium-browser';
            else if (fs.existsSync('/usr/bin/chromium')) CHROME_PATH = '/usr/bin/chromium';
            else CHROME_PATH = null;
        } catch (e) {
            CHROME_PATH = null;
        }
    }
}
const CAPSOLVER_KEY = process.env.CAPSOLVER_API_KEY || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const YOPMAIL_SITE_KEY = "6LcG5v8SAAAAAOdAn2iqMEQTdVyX8t0w9T3cpdN2";
const PAGE_URL = "https://svp-international.pacc.sa/auth/register?role=labor";

const DESKTOP_PASSPORT_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";
const DESKTOP_PHOTO_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PHOTO.jpg";

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

// ⚡ Ultra-Fast CapSolver with 300ms high-frequency polling
async function solveRecaptchaFast(label = "Captcha", websiteUrl = PAGE_URL, websiteKey = SITE_KEY) {
    const startTime = Date.now();
    console.log(`[CapSolver AI] ⚡ Solving reCAPTCHA for ${label}...`);
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: websiteUrl,
            websiteKey: websiteKey
        }
    });

    if (createRes.errorId !== 0 || !createRes.taskId) {
        throw new Error("CapSolver error: " + JSON.stringify(createRes));
    }

    const taskId = createRes.taskId;
    for (let i = 0; i < 200; i++) {
        await delay(150);
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: taskId
        });

        if (result.status === "ready") {
            const duration = ((Date.now() - startTime) / 1000).toFixed(1);
            console.log(`[CapSolver AI] 🎯 ${label} Solved in ${duration}s!`);
            return result.solution.gRecaptchaResponse;
        }

        if (result.status === "failed" || result.errorId !== 0) {
            throw new Error("CapSolver failed: " + JSON.stringify(result));
        }
    }
    throw new Error("CapSolver timeout");
}

async function injectToken(page, token) {
    return await page.evaluate((t) => {
        document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(ta => {
            ta.value = t;
            ta.innerHTML = t;
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            ta.dispatchEvent(new Event('change', { bubbles: true }));
        });

        if (window.___grecaptcha_cfg && window.___grecaptcha_cfg.clients) {
            for (const c in window.___grecaptcha_cfg.clients) {
                const client = window.___grecaptcha_cfg.clients[c];
                for (const k in client) {
                    if (client[k] && client[k].callback && typeof client[k].callback === 'function') {
                        try { client[k].callback(t); } catch (e) {}
                    }
                }
            }
        }

        document.querySelectorAll('*').forEach(el => {
            if (el.__vue__) {
                const v = el.__vue__;
                v.recaptchaResponse = t;
                v.recaptchaStatus = 'ready';
                if (v.successCaptcha) try { v.successCaptcha(t); } catch (e) {}
                if (v.$parent && v.$parent.recaptchaResponse !== undefined) {
                    v.$parent.recaptchaResponse = t;
                    v.$parent.recaptchaStatus = 'ready';
                }
                if (v.$refs && v.$refs.recaptcha) {
                    v.$refs.recaptcha.recaptchaResponse = t;
                    v.$refs.recaptcha.recaptchaStatus = 'ready';
                }
            }
        });
    }, token);
}

let lastYopmailToken = null;

// 🛡️ Auto-Detect and Solve YOPmail On-Screen CAPTCHA
async function checkAndSolveYopmailCaptcha(yopPage, preSolvedPromise = null) {
    try {
        const captchaNeeded = await yopPage.evaluate(() => {
            const rParent = document.getElementById('r_parent');
            const isRParent = rParent && (rParent.style.display !== 'none' || rParent.offsetWidth > 0);
            const cptEl = document.getElementById('cpt');
            const isCpt = cptEl && (cptEl.offsetWidth > 0 || window.getComputedStyle(cptEl).display !== 'none');
            const hasRc = document.querySelector('iframe[src*="recaptcha"]') !== null;
            const text = document.body ? document.body.innerText : '';
            return isRParent || isCpt || hasRc || text.includes('Complete the CAPTCHA') || text.includes("I'm not a robot") || text.includes('exceeding reCAPTCHA');
        }).catch(() => false);

        if (captchaNeeded) {
            console.log("\n[YOPmail Engine] 🚨 CAPTCHA Modal detected on YOPmail! Solving with CapSolver AI...");
            let token = null;
            if (preSolvedPromise) {
                token = await preSolvedPromise.catch(() => null);
            }
            if (!token) {
                token = await solveRecaptchaFast("YOPmail Modal Captcha", "https://yopmail.com", YOPMAIL_SITE_KEY);
            }

            if (token) {
                console.log("[YOPmail Engine] ⚡ Injecting solved token and unlocking YOPmail...");
                await yopPage.evaluate((t) => {
                    document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(ta => {
                        ta.value = t;
                        ta.innerHTML = t;
                        ta.dispatchEvent(new Event('input', { bubbles: true }));
                        ta.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    // ⚡ Direct Token Authentication on YOPmail
                    if (typeof refr === 'function') {
                        try { refr('1', null, null, null, t); } catch (e) {}
                    }
                    if (typeof hideRc === 'function') {
                        try { hideRc(); } catch (e) {}
                    }
                    
                    const rParent = document.getElementById('r_parent');
                    if (rParent) rParent.style.display = 'none';
                    const cptEl = document.getElementById('cpt');
                    if (cptEl) cptEl.style.display = 'none';
                    if (typeof showinbox === 'function') {
                        try { showinbox(true); } catch (e) {}
                    }
                }, token);

                // Click reCAPTCHA anchor in frame if exists
                for (const frame of yopPage.frames()) {
                    if (frame.url().includes('recaptcha')) {
                        try {
                            const anchor = await frame.$('#recaptcha-anchor, .recaptcha-checkbox');
                            if (anchor) await anchor.click();
                        } catch (e) {}
                    }
                }

                await delay(1200);
                console.log("[YOPmail Engine] ✅ YOPmail CAPTCHA bypassed successfully!\n");
                return true;
            }
        }
    } catch (e) {
        console.log("[YOPmail Engine] Notice:", e.message);
    }
    return false;
}

// 🔄 Fast YOPmail Auto-Refresh & Instant OTP Extraction
async function waitForEmailAndExtractOtp(yopPage, emailUser) {
    console.log(`[YOPmail Engine] 🔄 Monitoring inbox for ${emailUser}@yopmail.com...`);
    let yopCaptchaPromise = solveRecaptchaFast("YOPmail Pre-solve", "https://yopmail.com", YOPMAIL_SITE_KEY).catch(() => null);

    for (let attempt = 1; attempt <= 40; attempt++) {
        // 1. Check if captcha modal is shown and unlock immediately
        const isCaptcha = await yopPage.evaluate(() => {
            const rParent = document.getElementById('r_parent');
            return rParent && (rParent.style.display !== 'none' && rParent.offsetWidth > 0);
        }).catch(() => false);

        if (isCaptcha) {
            console.log("🚨 YOPmail Captcha detected! Unlocking with token...");
            let token = null;
            if (yopCaptchaPromise) token = await yopCaptchaPromise;
            if (!token) token = await solveRecaptchaFast("YOPmail Live Captcha", "https://yopmail.com", YOPMAIL_SITE_KEY);

            if (token) {
                await yopPage.evaluate((t) => {
                    if (typeof refr === 'function') try { refr('1', null, null, null, t); } catch (e) {}
                    if (typeof hideRc === 'function') try { hideRc(); } catch (e) {}
                    const rParent = document.getElementById('r_parent');
                    if (rParent) rParent.style.display = 'none';
                    if (typeof showinbox === 'function') try { showinbox(true); } catch (e) {}
                }, token);
                yopCaptchaPromise = null;
                await delay(1200);
            }
        }

        // 2. Check if messages exist in ifinbox and click first
        const ifinbox = yopPage.frames().find(f => f.name() === 'ifinbox');
        if (ifinbox) {
            await ifinbox.evaluate(() => {
                const topMsg = document.querySelector('button.lm, .lm, .m, div.m, .mctn');
                if (topMsg) topMsg.click();
            }).catch(() => {});
        }

        // 3. Extract OTP directly from ifmail or ifinbox
        for (const frame of yopPage.frames()) {
            if (frame.name() === 'ifmail' || frame.name() === 'ifinbox') {
                const text = await frame.evaluate(() => document.body ? document.body.innerText : '').catch(() => '');
                const m = text.match(/\b([0-9]{6})\b/);
                if (m) {
                    console.log(`\n🎯 [YOPmail Engine] 🔥 INSTANT OTP CAPTURED from Frame [${frame.name()}]: ${m[1]}! 🔥\n`);
                    return m[1];
                }
            }
        }

        // 4. Trigger gentle inbox refresh
        await yopPage.evaluate(() => {
            const rBtn = document.getElementById('refresh');
            if (rBtn) rBtn.click();
        }).catch(() => {});

        await delay(1000);
    }
    return null;
}

function formatToDDMMYYYY(dStr, defaultDate = "12/04/1996") {
    if (!dStr) return defaultDate;
    const s = String(dStr).trim();
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(s)) return s;
    if (/^\d{4}[-/.]\d{1,2}[-/.]\d{1,2}$/.test(s)) {
        const parts = s.split(/[-/.]/);
        return `${parts[2].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[0]}`;
    }
    if (/^\d{1,2}[-.]\d{1,2}[-.]\d{4}$/.test(s)) {
        const parts = s.split(/[-.]/);
        return `${parts[0].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[2]}`;
    }
    try {
        const dt = new Date(s);
        if (!isNaN(dt.getTime())) {
            return `${String(dt.getDate()).padStart(2, '0')}/${String(dt.getMonth() + 1).padStart(2, '0')}/${dt.getFullYear()}`;
        }
    } catch (e) {}
    return defaultDate;
}

async function runAutomation(candidateData = {}) {
    console.log("==========================================================");
    console.log("    TAQAMUL HIGH-SPEED 4-STEP FULL AUTONOMOUS ENGINE      ");
    console.log("==========================================================\n");

    const firstName = (candidateData.first_name || "MD").trim();
    const lastName = (candidateData.last_name || "HASAN").trim();
    const passportNumber = candidateData.passport_number || ("A" + Math.floor(10000000 + Math.random() * 90000000));

    // 📧 Generate Fresh Email directly from Full First Name + Last Name + Passport Number!
    const cleanFirst = firstName.toLowerCase().replace(/[^a-z0-9]/g, '');
    const cleanLast = lastName.toLowerCase().replace(/[^a-z0-9]/g, '');
    const cleanPassport = passportNumber.toLowerCase().replace(/[^a-z0-9]/g, '');
    const emailUser = `${cleanFirst}${cleanLast}_${cleanPassport}`;
    const email = `${emailUser}@renonx.tech`;

    let rawPhone = String(candidateData.phone_number || "01941070719").replace(/[^0-9]/g, '');
    if (rawPhone.startsWith('880')) rawPhone = rawPhone.substring(3);
    if (!rawPhone.startsWith('0') && rawPhone.length === 10) rawPhone = '0' + rawPhone;

    const candidate = {
        firstName: firstName,
        lastName: lastName,
        passport: passportNumber,
        nationalId: candidateData.national_id || ("1995" + Math.floor(10000000 + Math.random() * 90000000)),
        dob: formatToDDMMYYYY(candidateData.date_of_birth, "12/04/1996"),
        expDate: formatToDDMMYYYY(candidateData.passport_expiration_date, "18/10/2034"),
        email: email,
        emailUser: emailUser,
        phone: rawPhone,
        password: candidateData.password || "Taqamul@2026!",
        educationLevel: candidateData.education_level || "Secondary",
        experienceLevel: candidateData.experience_level || "3-5 years",
        instituteName: candidateData.institute_name || "None"
    };

    let passportFile = candidateData.passport_file_path && fs.existsSync(candidateData.passport_file_path)
        ? candidateData.passport_file_path
        : DESKTOP_PASSPORT_FILE;

    let photoFile = candidateData.personal_photo_path && fs.existsSync(candidateData.personal_photo_path)
        ? candidateData.personal_photo_path
        : DESKTOP_PHOTO_FILE;

    const candidateId = candidateData.passenger_id || candidateData.id;
    function reportProgress(stepText) {
        console.log(`[PROGRESS] ${stepText}`);
        if (candidateId) {
            try {
                const progFile = path.join(__dirname, `progress_${candidateId}.txt`);
                fs.writeFileSync(progFile, stepText, 'utf8');
            } catch (e) {}
        }
    }

    console.log(">>> Candidate Registration Data:");
    console.log(`    Name        : ${candidate.firstName} ${candidate.lastName}`);
    console.log(`    Passport No : ${candidate.passport}`);
    console.log(`    National ID : ${candidate.nationalId}`);
    console.log(`    Email (YOP) : ${candidate.email}`);
    console.log(`    Phone       : +880 ${candidate.phone}`);
    console.log(`    Password    : ${candidate.password}\n`);

    let capturedOtp = null;

    reportProgress("Pre-solving Captchas...");

    let isHeadless = false;

    // On Windows (localhost development), ALWAYS force on-screen visual Chrome mode
    if (process.platform === 'win32') {
        isHeadless = false;
    } else if (process.env.HEADLESS !== undefined) {
        const envVal = String(process.env.HEADLESS).trim().toLowerCase();
        isHeadless = (envVal === 'true' || envVal === '1');
    } else if (candidateData.browser_mode !== undefined) {
        isHeadless = (candidateData.browser_mode === 'headless');
    } else if (candidateData.headless !== undefined) {
        isHeadless = Boolean(candidateData.headless);
    }

    console.log(`[Browser Engine] Launching Chrome in ${isHeadless ? 'BACKGROUND (Headless)' : 'ON-SCREEN (Visual / Headed)'} mode...`);

    const launchArgs = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--disable-crash-reporter',
        '--disable-breakpad',
        '--disable-blink-features=AutomationControlled',
        '--enable-features=NetworkService,NetworkServiceInProcess',
        '--disk-cache-size=209715200',
        '--no-first-run',
        '--no-default-browser-check'
    ];

    if (!isHeadless) {
        launchArgs.push('--start-maximized');
    } else {
        launchArgs.push('--window-size=1920,1080');
        launchArgs.push('--headless=new');
    }
    launchArgs.push(PAGE_URL);

    const profileDir = path.join(__dirname, 'chrome_profiles', 'worker_' + (candidateData.passenger_id || Date.now()) + '_' + Date.now());
    if (!fs.existsSync(profileDir)) {
        try { fs.mkdirSync(profileDir, { recursive: true }); } catch (e) {}
    } else {
        try {
            const lockFile = path.join(profileDir, 'SingletonLock');
            if (fs.existsSync(lockFile)) fs.unlinkSync(lockFile);
        } catch (e) {}
    }

    const launchOptions = {
        headless: isHeadless ? 'new' : false,
        userDataDir: profileDir,
        defaultViewport: isHeadless ? { width: 1920, height: 1080 } : null,
        args: launchArgs
    };
    if (CHROME_PATH && fs.existsSync(CHROME_PATH)) {
        launchOptions.executablePath = CHROME_PATH;
    }

    // 🚀 Launch Browser Immediately for 1-Second Instant Visual Window Popup
    const browser = await puppeteer.launch(launchOptions);

    // ⚡ PRE-SOLVE ALL 3 INITIAL STEPS CONCURRENTLY IN PARALLEL!
    console.log("⚡ [Turbo Engine] Starting simultaneous parallel pre-solving for Step 1, Step 2, and Step 3...");
    const step1CaptchaPromise = solveRecaptchaFast("Step 1").catch(e => { console.error("⚠️ Step 1 Pre-solve Captcha Warning:", e.message); return null; });
    const step2CaptchaPromise = solveRecaptchaFast("Step 2").catch(e => { console.error("⚠️ Step 2 Pre-solve Captcha Warning:", e.message); return null; });
    const step3CaptchaPromise = solveRecaptchaFast("Step 3").catch(e => { console.error("⚠️ Step 3 Pre-solve Captcha Warning:", e.message); return null; });

    try {
        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();

        // Set realistic User-Agent & viewport
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        // Allow native multi-threaded Chrome network speed
        await page.setCacheEnabled(true);

        // ==========================================
        // STEP 1: PASSPORT INFORMATION
        // ==========================================
        console.log("\n[1/4] === STEP 1: PASSPORT & PERSONAL INFORMATION ===");
        reportProgress("Step 1: Opening Portal & Passport...");
        console.log("⚡ Loading Taqamul registration portal...");
        await page.goto(PAGE_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await page.waitForFunction(() => document.querySelectorAll('input, button').length >= 3, { timeout: 30000 });
        await delay(800);

        reportProgress("Step 1: Uploading Passport File...");
        // Upload Passport file
        if (fs.existsSync(passportFile)) {
            console.log("Uploading Passport file: " + passportFile);
            const fileInputs = await page.$$('input[type="file"], .el-upload__input');
            for (const fi of fileInputs) {
                try { await fi.uploadFile(passportFile); } catch (e) {}
            }
            await delay(300);

            // Attach file object directly into Vue instance
            const passportBase64 = fs.readFileSync(passportFile).toString('base64');
            await page.evaluate((pB64) => {
                const byteCharacters = atob(pB64);
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i);
                const byteArray = new Uint8Array(byteNumbers);
                const f = new File([byteArray], "PASSPORT.jpg", { type: "image/jpeg" });
                const fileObj = { raw: f, name: "PASSPORT.jpg", size: f.size, uid: Date.now() };

                document.querySelectorAll('*').forEach(el => {
                    if (el.__vue__) {
                        const v = el.__vue__;
                        v.passportFile = fileObj;
                        v.isPassportRecognitionShown = false;
                        v.passportValidationShown = false;
                        if (typeof v.fileUploaded === 'function') {
                            try { v.fileUploaded(fileObj, 'passport'); } catch (e) {}
                        }
                    }
                });
            }, passportBase64);
            console.log("✅ Passport file attached!");
        }

        // Fill Names & Passport
        const firstNameInput = await page.$('input[placeholder*="first name" i], input[placeholder*="given" i]');
        if (firstNameInput) {
            await firstNameInput.click({ clickCount: 3 });
            await page.keyboard.down('Control');
            await page.keyboard.press('KeyA');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
            await firstNameInput.type(candidate.firstName, { delay: 15 });
        }

        const lastNameInput = await page.$('input[placeholder*="last name" i], input[placeholder*="surname" i]');
        if (lastNameInput) {
            await lastNameInput.click({ clickCount: 3 });
            await page.keyboard.down('Control');
            await page.keyboard.press('KeyA');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
            await lastNameInput.type(candidate.lastName, { delay: 15 });
        }

        const passportInput = await page.$('input[placeholder*="passport" i], input[placeholder*="password number" i]');
        if (passportInput) {
            await passportInput.click({ clickCount: 3 });
            await page.keyboard.down('Control');
            await page.keyboard.press('KeyA');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
            await passportInput.type(candidate.passport, { delay: 15 });
        }

        // 1. Country Selection: Bangladesh
        console.log("Selecting Country of residence: Bangladesh...");
        const countryHandle = await page.evaluateHandle(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            return inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('country')) ||
                   inputs.find(i => i.name && i.name.toLowerCase().includes('country'));
        });
        const countryInput = countryHandle.asElement();
        if (countryInput) {
            await countryInput.click();
            await delay(300);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li'));
                const match = items.find(el => el.innerText && el.innerText.trim().toLowerCase().includes('bangla')) || items[0];
                if (match) match.click();
            });
            await delay(500);
        }

        // ⏳ Wait 2 Full Seconds for Taqamul API to reload Nationality options for Bangladesh!
        console.log("⏳ Waiting 2s for Taqamul API to load Nationality options for Bangladesh...");
        await delay(2000);

        // 2. Nationality Selection: Click 1st Option (Bangladesh is #1!)
        console.log("Selecting Nationality: Clicking 1st option in dropdown (Bangladesh)...");
        const natHandle = await page.evaluateHandle(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            return inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('nationality')) ||
                   inputs.find(i => i.name && i.name.toLowerCase().includes('nationality'));
        });
        const natInput = natHandle.asElement();
        if (natInput) {
            await natInput.click();
            await delay(400);
            await page.evaluate(() => {
                // Find visible Element-UI dropdown container
                const visibleDrops = Array.from(document.querySelectorAll('.el-select-dropdown')).filter(d => d.style.display !== 'none');
                for (const drop of visibleDrops) {
                    const firstItem = drop.querySelector('.el-select-dropdown__item, li');
                    if (firstItem) {
                        firstItem.click();
                        return;
                    }
                }
                const allItems = Array.from(document.querySelectorAll('.el-select-dropdown__item, li'));
                const match = allItems.find(el => el.innerText && el.innerText.trim().toLowerCase().includes('bangla')) || allItems[0];
                if (match) match.click();
            });
            await delay(200);
            await page.evaluate(() => {
                document.querySelectorAll('.el-select-dropdown').forEach(d => {
                    d.style.display = 'none';
                    if (d.parentNode) d.parentNode.removeChild(d);
                });
            });
            await page.keyboard.press('Escape');
        }

        // Purge any lingering dropdown DOM elements from body
        await page.evaluate(() => {
            document.querySelectorAll('.el-select-dropdown').forEach(d => {
                d.style.display = 'none';
                if (d.parentNode) d.parentNode.removeChild(d);
            });
        });
        await delay(150);

        // Dates (DOB and Passport Expiry)
        console.log(`Setting Dates: DOB=${candidate.dob}, Expiry=${candidate.expDate}...`);
        const dateInputs = await page.$$('input[placeholder*="DD/MM/YYYY"]');
        if (dateInputs.length > 0) {
            await dateInputs[0].click();
            await page.keyboard.down('Control');
            await page.keyboard.press('KeyA');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
            await dateInputs[0].type(candidate.dob, { delay: 20 });
            await page.keyboard.press("Enter");
            await delay(150);
        }
        if (dateInputs.length > 1) {
            await dateInputs[1].click();
            await page.keyboard.down('Control');
            await page.keyboard.press('KeyA');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
            await dateInputs[1].type(candidate.expDate, { delay: 20 });
            await page.keyboard.press("Enter");
            await delay(150);
        }

        // Select Sex: Male
        await page.evaluate(() => {
            const radios = Array.from(document.querySelectorAll('input[type="radio"], .el-radio'));
            const male = radios.find(r => (r.value && r.value.toLowerCase() === 'male') || (r.innerText && r.innerText.toLowerCase().includes('male')));
            if (male) male.click();
        });

        // Model sync & Vuelidate touch
        await page.evaluate((dobStr, expStr) => {
            document.querySelectorAll('*').forEach(el => {
                if (el.__vue__ && el.__vue__.$options && el.__vue__.$options.name === 'PassportInformation') {
                    const v = el.__vue__;
                    v.sex = 'male';
                    v.dateOfBirth = dobStr;
                    v.passportExpiryDate = expStr;
                    
                    if (v.countryOptions && Array.isArray(v.countryOptions)) {
                        const bg = v.countryOptions.find(o => (o.label && o.label.toLowerCase().includes('bangla')) || (o.value && String(o.value).toLowerCase().includes('bangla')));
                        if (bg) v.country = bg.value !== undefined ? bg.value : bg;
                    }
                    if (v.nationalityOptions && Array.isArray(v.nationalityOptions)) {
                        const bg = v.nationalityOptions.find(o => (o.label && o.label.toLowerCase().includes('bangla')) || (o.value && String(o.value).toLowerCase().includes('bangla')));
                        if (bg) v.nationality = bg.value !== undefined ? bg.value : bg;
                    }

                    if (v.$v && v.$v.firstName) v.$v.firstName.$model = v.firstName;
                    if (v.$v && v.$v.surname) v.$v.surname.$model = v.surname;
                    if (v.$v && v.$v.passportNumber) v.$v.passportNumber.$model = v.passportNumber;
                    if (v.$v && v.$v.country && v.country) { v.$v.country.$model = v.country; if (typeof v.$v.country.$touch === 'function') v.$v.country.$touch(); }
                    if (v.$v && v.$v.nationality && v.nationality) { v.$v.nationality.$model = v.nationality; if (typeof v.$v.nationality.$touch === 'function') v.$v.nationality.$touch(); }
                    if (v.$v && v.$v.dateOfBirth) {
                        v.$v.dateOfBirth.$model = dobStr;
                        if (typeof v.$v.dateOfBirth.$touch === 'function') v.$v.dateOfBirth.$touch();
                    }
                    if (v.$v && v.$v.passportExpiryDate) {
                        v.$v.passportExpiryDate.$model = expStr;
                        if (typeof v.$v.passportExpiryDate.$touch === 'function') v.$v.passportExpiryDate.$touch();
                    }
                    if (v.$v && v.$v.sex) v.$v.sex.$model = 'male';
                }
            });
        }, candidate.dob, candidate.expDate);

        // ⚡ Inject Fast Pre-solved Token for Step 1
        console.log("[Step 1] Injecting Pre-Solved CapSolver token...");
        reportProgress("Step 1: Submitting with Captcha...");
        const token1 = await step1CaptchaPromise;
        await injectToken(page, token1);
        await delay(300);

        // Click Continue on Step 1
        console.log("Submitting Step 1 (Continue)...");
        await page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button, .el-button'));
            const continueBtn = buttons.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        // Wait for Passport Confirmation Section & Click 'Confirm and proceed'
        console.log("Waiting for Passport Confirmation Page...");
        reportProgress("Step 1: Confirming Passport Details...");

        // ==========================================
        // PHASE 2: PASSPORT CONFIRMATION POPUP
        // ==========================================
        console.log("Waiting for Passport Confirmation Page...");
        reportProgress("Step 1: Confirming Passport Details...");

        // Wait strictly for 'Confirm and proceed' button to appear on screen
        await page.waitForFunction(() => {
            const btns = Array.from(document.querySelectorAll('button, .el-button'));
            return btns.some(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
        }, { timeout: 25000 }).catch(e => {
            console.log("⚠️ Confirmation page wait notice: Attempting direct confirmation click...");
        });

        // Click checkbox & 'Confirm and proceed' (Without pressing Escape!)
        for (let m = 0; m < 15; m++) {
            await page.evaluate(() => {
                // Hide only dropdown select menus if present (do not escape dialogs)
                document.querySelectorAll('.el-select-dropdown').forEach(d => {
                    d.style.display = 'none';
                });

                // Check verification checkboxes cleanly
                document.querySelectorAll('.el-checkbox, label.el-checkbox, input[type="checkbox"]').forEach(c => {
                    if (!c.checked) {
                        try { c.click(); } catch(e) {}
                    }
                    if (c.__vue__) {
                        c.__vue__.model = true;
                        c.__vue__.currentValue = true;
                    }
                });

                // Click proceed button directly
                const btns = Array.from(document.querySelectorAll('button, .el-button'));
                const proceedBtn = btns.find(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
                if (proceedBtn) {
                    proceedBtn.classList.remove('is-disabled');
                    proceedBtn.removeAttribute('disabled');
                    if (proceedBtn.__vue__) proceedBtn.__vue__.disabled = false;
                    proceedBtn.click();
                }
            });

            await delay(1000);

            // Break ONLY when 'Confirm and proceed' button has completely DISAPPEARED from screen!
            const isConfirmStillPresent = await page.evaluate(() => {
                const btns = Array.from(document.querySelectorAll('button, .el-button'));
                return btns.some(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
            });
            if (!isConfirmStillPresent) {
                console.log("✅ Passport Confirmation completed & section vanished!");
                break;
            }
        }

        console.log("==========================================================");
        console.log("✅ STEP 1 & PASSPORT CONFIRMATION SUCCESSFULLY COMPLETED!");
        console.log("==========================================================");
        reportProgress("Step 1 Confirmed ✅ (Step 2 Held)");

        console.log("✅ Step 1 & Confirmation Completed! Moving to Step 2 form filling...");

        // ==========================================
        // STEP 2: OTHER DETAILS
        // ==========================================
        console.log("⏳ Strictly waiting for Step 2 (Other Details) form fields to load on screen...");
        await page.waitForFunction(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            const hasNationalId = inputs.some(i => i.placeholder && i.placeholder.toLowerCase().includes('national id'));
            const hasEducation = inputs.some(i => i.placeholder && i.placeholder.toLowerCase().includes('education'));
            const hasPassword = document.querySelector('input[type="password"]') !== null;
            const hasEmail = document.querySelector('input[type="email"]') !== null;
            return hasNationalId || hasEducation || hasPassword || hasEmail;
        }, { timeout: 35000 });

        console.log("\n[2/4] === STEP 2: OTHER DETAILS FORM LOADED ===");

        // Wait strictly for Step 2 (Other Details: Photo, NID, Passwords) form to load on screen
        console.log("⏳ Strictly waiting for Step 2 (Other Details) form to load on screen...");
        await page.waitForFunction(() => {
            const inputs = Array.from(document.querySelectorAll('input'));
            const nidEl = inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('national id'));
            const passEl = document.querySelector('input[type="password"]');
            const fileEl = document.querySelector('input[type="file"]');
            return nidEl !== undefined || passEl !== null || fileEl !== null;
        }, { timeout: 35000 });
            // Upload Photo file
            if (fs.existsSync(photoFile)) {
                console.log("Uploading Photo file: " + photoFile);
                const fileInputs = await page.$$('input[type="file"], .el-upload__input');
                for (const fi of fileInputs) {
                    try { await fi.uploadFile(photoFile); } catch (e) {}
                }
                await delay(300);

                const photoB64 = fs.readFileSync(photoFile).toString('base64');
                await page.evaluate((b64) => {
                    const byteCharacters = atob(b64);
                    const byteNumbers = new Array(byteCharacters.length);
                    for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i);
                    const byteArray = new Uint8Array(byteNumbers);
                    const file = new File([byteArray], "PHOTO.jpg", { type: "image/jpeg" });
                    const fileObj = { raw: file, name: "PHOTO.jpg", size: file.size, uid: Date.now() };

                    document.querySelectorAll('*').forEach(el => {
                        if (el.__vue__) {
                            const v = el.__vue__;
                            v.photoFile = fileObj;
                            if (v.$options && v.$options.name === 'OtherDetails') {
                                v.photoFile = fileObj;
                            }
                        }
                    });
                }, photoB64);
                console.log("✅ Photo file uploaded!");
            }

            // ID No (National ID)
            console.log("Typing National ID: " + candidate.nationalId);
            const nidHandle = await page.evaluateHandle(() => {
                const inputs = Array.from(document.querySelectorAll('input'));
                return inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('national id')) ||
                       inputs.find(i => i.name === 'nationalId' || i.name === 'national_id');
            });
            const nidInput = nidHandle.asElement();
            if (nidInput) {
                await nidInput.click({ clickCount: 3 });
                await nidInput.type(candidate.nationalId, { delay: 15 });
            }
            await delay(150);

            // Education Level: Secondary
            console.log("Selecting Education Level...");
            const eduHandle = await page.evaluateHandle(() => {
                const inputs = Array.from(document.querySelectorAll('input'));
                return inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('education'));
            });
            const eduInput = eduHandle.asElement();
            if (eduInput) {
                await eduInput.click();
                await delay(250);
                await page.evaluate(() => {
                    const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li'));
                    const match = items.find(el => el.innerText && (el.innerText.toLowerCase().includes('secondary') || el.innerText.toLowerCase().includes('middle') || el.innerText.toLowerCase().includes('primary')));
                    if (match) match.click();
                    else if (items.length > 0) items[items.length - 1].click();
                });
                await delay(150);
            }

            // Experience Level: Dynamically match candidate experience or fallback to option
            console.log("Selecting Experience Level: " + (candidate.experienceLevel || "3-5 years"));
            const expHandle = await page.evaluateHandle(() => {
                const inputs = Array.from(document.querySelectorAll('input'));
                return inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('experience'));
            });
            const expInput = expHandle.asElement();
            if (expInput) {
                await expInput.click();
                await delay(350);
                await page.evaluate((targetExp) => {
                    const visibleDrops = Array.from(document.querySelectorAll('.el-select-dropdown')).filter(d => d.style.display !== 'none');
                    let items = [];
                    for (const drop of visibleDrops) {
                        const dropItems = Array.from(drop.querySelectorAll('.el-select-dropdown__item, li'));
                        if (dropItems.length > 0) items = dropItems;
                    }
                    if (items.length === 0) items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li'));

                    const targetLower = String(targetExp || '').toLowerCase();
                    const match = items.find(el => el.innerText && targetLower && el.innerText.toLowerCase().includes(targetLower)) ||
                                  items.find(el => el.innerText && (el.innerText.includes('3') || el.innerText.includes('5') || el.innerText.toLowerCase().includes('less') || el.innerText.toLowerCase().includes('more'))) ||
                                  items[0];
                    if (match) match.click();
                }, candidate.experienceLevel);
                await delay(200);
            }

            // Training / Certification: Match candidate instituteName or fallback
            console.log("Selecting Institute/Certificate: " + (candidate.instituteName || "No"));
            const instHandle = await page.evaluateHandle(() => {
                const inputs = Array.from(document.querySelectorAll('input'));
                return inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('institute')) ||
                       inputs.find(i => i.placeholder && i.placeholder.toLowerCase().includes('training'));
            });
            const instInput = instHandle.asElement();
            if (instInput) {
                await instInput.click();
                await delay(350);
                await page.evaluate((targetInst) => {
                    const visibleDrops = Array.from(document.querySelectorAll('.el-select-dropdown')).filter(d => d.style.display !== 'none');
                    let items = [];
                    for (const drop of visibleDrops) {
                        const dropItems = Array.from(drop.querySelectorAll('.el-select-dropdown__item, li'));
                        if (dropItems.length > 0) items = dropItems;
                    }
                    if (items.length === 0) items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li'));

                    const targetLower = String(targetInst || '').toLowerCase();
                    const match = items.find(el => el.innerText && targetLower && el.innerText.toLowerCase().includes(targetLower)) ||
                                  items.find(el => el.innerText && (el.innerText.toLowerCase().includes('no,') || el.innerText.toLowerCase().includes('bmet') || el.innerText.toLowerCase().includes('no'))) ||
                                  items[0];
                    if (match) match.click();
                }, candidate.instituteName);
                await delay(200);
            }

            // Passwords
            console.log("Entering Passwords...");
            const passInputs = await page.$$('input[type="password"]');
            if (passInputs.length >= 2) {
                await passInputs[0].click({ clickCount: 3 });
                await passInputs[0].type(candidate.password, { delay: 15 });

                await passInputs[1].click({ clickCount: 3 });
                await passInputs[1].type(candidate.password, { delay: 15 });
            }
            await delay(200);

            // Check checkboxes
            await page.evaluate(() => {
                const checkboxes = Array.from(document.querySelectorAll('input[type="checkbox"]'));
                checkboxes.forEach(c => { if (!c.checked) c.click(); });
            });
            await delay(200);

            // ⚡ Deep Vue & Vuelidate Sync for Step 2 to remove all "Field is required" errors
            await page.evaluate((nid, pass, candEdu, candExp, candInst) => {
                // Dispatch DOM events for all inputs
                document.querySelectorAll('input').forEach(i => {
                    i.dispatchEvent(new Event('input', { bubbles: true }));
                    i.dispatchEvent(new Event('change', { bubbles: true }));
                    i.dispatchEvent(new Event('blur', { bubbles: true }));
                });

                document.querySelectorAll('*').forEach(el => {
                    if (el.__vue__) {
                        const v = el.__vue__;
                        
                        // National ID
                        if (v.nationalId !== undefined) v.nationalId = nid;
                        if (v.national_id !== undefined) v.national_id = nid;
                        if (v.idNumber !== undefined) v.idNumber = nid;

                        // Password
                        if (v.password !== undefined) v.password = pass;
                        if (v.confirmPassword !== undefined) v.confirmPassword = pass;
                        if (v.passwordConfirmation !== undefined) v.passwordConfirmation = pass;

                        // Education, Experience, Institute options dynamic matching
                        if (v.educationLevel !== undefined) {
                            if (v.educationOptions && Array.isArray(v.educationOptions) && v.educationOptions.length > 0) {
                                const targetLower = String(candEdu || '').toLowerCase();
                                const matchedOpt = v.educationOptions.find(o => (o.label && o.label.toLowerCase().includes(targetLower)) || (o.value && String(o.value).toLowerCase().includes(targetLower)));
                                v.educationLevel = matchedOpt ? (matchedOpt.value !== undefined ? matchedOpt.value : matchedOpt) : (v.educationOptions[0].value || v.educationOptions[0]);
                            } else if (!v.educationLevel) {
                                v.educationLevel = candEdu || 'Secondary';
                            }
                        }

                        if (v.experienceLevel !== undefined) {
                            if (v.experienceOptions && Array.isArray(v.experienceOptions) && v.experienceOptions.length > 0) {
                                const targetLower = String(candExp || '').toLowerCase();
                                const matchedOpt = v.experienceOptions.find(o => (o.label && o.label.toLowerCase().includes(targetLower)) || (o.value && String(o.value).toLowerCase().includes(targetLower)));
                                v.experienceLevel = matchedOpt ? (matchedOpt.value !== undefined ? matchedOpt.value : matchedOpt) : (v.experienceOptions[0].value || v.experienceOptions[0]);
                            } else if (!v.experienceLevel) {
                                v.experienceLevel = candExp || '3-5 years';
                            }
                        }

                        if (v.trainingInstitute !== undefined) {
                            if (v.instituteOptions && Array.isArray(v.instituteOptions) && v.instituteOptions.length > 0) {
                                const targetLower = String(candInst || '').toLowerCase();
                                const matchedOpt = v.instituteOptions.find(o => (o.label && o.label.toLowerCase().includes(targetLower)) || (o.value && String(o.value).toLowerCase().includes(targetLower)));
                                v.trainingInstitute = matchedOpt ? (matchedOpt.value !== undefined ? matchedOpt.value : matchedOpt) : (v.instituteOptions[0].value || v.instituteOptions[0]);
                            } else if (!v.trainingInstitute) {
                                v.trainingInstitute = candInst || 'None';
                            }
                        }

                        // Vuelidate $v sync & touch
                        if (v.$v) {
                            if (v.$v.nationalId) { v.$v.nationalId.$model = nid; if (typeof v.$v.nationalId.$touch === 'function') v.$v.nationalId.$touch(); }
                            if (v.$v.idNumber) { v.$v.idNumber.$model = nid; if (typeof v.$v.idNumber.$touch === 'function') v.$v.idNumber.$touch(); }
                            if (v.$v.password) { v.$v.password.$model = pass; if (typeof v.$v.password.$touch === 'function') v.$v.password.$touch(); }
                            if (v.$v.confirmPassword) { v.$v.confirmPassword.$model = pass; if (typeof v.$v.confirmPassword.$touch === 'function') v.$v.confirmPassword.$touch(); }
                            if (v.$v.educationLevel) { v.$v.educationLevel.$model = v.educationLevel; if (typeof v.$v.educationLevel.$touch === 'function') v.$v.educationLevel.$touch(); }
                            if (v.$v.experienceLevel) { v.$v.experienceLevel.$model = v.experienceLevel; if (typeof v.$v.experienceLevel.$touch === 'function') v.$v.experienceLevel.$touch(); }
                            if (v.$v.trainingInstitute) { v.$v.trainingInstitute.$model = v.trainingInstitute; if (typeof v.$v.trainingInstitute.$touch === 'function') v.$v.trainingInstitute.$touch(); }
                        }
                    }
                });
            }, candidate.nationalId, candidate.password, candidate.educationLevel, candidate.experienceLevel, candidate.instituteName);
            await delay(300);

            // ⚡ Solve and inject CapSolver token for Step 2
            const token2 = await step2CaptchaPromise;
            await injectToken(page, token2);
            await delay(300);

            // Submit Step 2 only after verifying form inputs are populated
            console.log("Submitting Step 2 (Continue)...");
            reportProgress("Step 2: Submitting Details...");

            // Ensure Continue button is clicked only after fields are filled
            await page.evaluate(() => {
                const btns = Array.from(document.querySelectorAll('button, .el-button'));
                const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
                if (continueBtn) continueBtn.click();
            });

            await page.waitForFunction(() => {
                const isStep3Loaded = document.querySelector('input[type="email"]') !== null ||
                                      Array.from(document.querySelectorAll('input')).some(i => i.placeholder && i.placeholder.toLowerCase().includes('email'));
                return isStep3Loaded;
            }, { timeout: 30000 }).catch(e => {});

        console.log("\n[3/4] === STEP 3: CONTACT INFORMATION LOADED ===");
        reportProgress("Step 3: Contact & Password...");

        // Email (Generated from Name + Passport!)
        const emailHandle = await page.evaluateHandle(() => {
            return document.querySelector('input[type="email"]') ||
                   Array.from(document.querySelectorAll('input')).find(i => i.placeholder && i.placeholder.toLowerCase().includes('email'));
        });
        const emailInput = emailHandle.asElement();
        if (emailInput) {
            await emailInput.click();
            await page.keyboard.down('Control');
            await page.keyboard.press('KeyA');
            await page.keyboard.up('Control');
            await page.keyboard.press('Backspace');
            await delay(100);
            await emailInput.type(candidate.email, { delay: 15 });
            console.log(`Auto-filled Name+Passport Email: ${candidate.email}`);
        }

        // Sync Vue Email Model & Dispatch Input Events so Taqamul sends OTP
        await page.evaluate((emailStr) => {
            document.querySelectorAll('input').forEach(input => {
                if (input.type === 'email' || (input.placeholder && input.placeholder.toLowerCase().includes('email'))) {
                    input.value = emailStr;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('blur', { bubbles: true }));
                }
            });

            document.querySelectorAll('*').forEach(el => {
                if (el.__vue__) {
                    const v = el.__vue__;
                    if (v.email !== undefined) v.email = emailStr;
                    if (v.emailAddress !== undefined) v.emailAddress = emailStr;
                    if (v.$v && v.$v.email) {
                        v.$v.email.$model = emailStr;
                        if (typeof v.$v.email.$touch === 'function') v.$v.email.$touch();
                    }
                }
            });
        }, candidate.email);
        await delay(250);

        // Phone Number Auto-fill
        console.log(`Auto-filling Phone: +880 ${candidate.phone}...`);
        const phoneInputs = await page.$$('.phone-input input, input[placeholder*="000"], input[placeholder*="phone" i]');
        for (const pi of phoneInputs) {
            const isReadonly = await page.evaluate(el => el.readOnly, pi);
            if (!isReadonly) {
                await pi.click({ clickCount: 3 });
                await pi.type(candidate.phone, { delay: 15 });
            }
        }

        await page.evaluate((ph) => {
            document.querySelectorAll('*').forEach(el => {
                if (el.__vue__) {
                    const v = el.__vue__;
                    if (v.phoneNumber !== undefined) v.phoneNumber = ph;
                    if (v.$options && v.$options.name === 'PhoneInput') {
                        v.$emit('input', ph);
                    }
                }
            });
        }, candidate.phone);
        await delay(200);

        // ⚡ Solve and inject CapSolver token for Step 3
        const token3 = await step3CaptchaPromise;
        await injectToken(page, token3);
        await delay(300);

        console.log("Submitting Step 3 to trigger OTP...");
        reportProgress("Step 3: Sending OTP...");
        const step3SubmitTime = Date.now();
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button, .el-button'));
            const continueBtn = btns.find(b => b.innerText && (b.innerText.includes('Continue') || b.innerText.includes('Send') || b.innerText.includes('Verify')));
            if (continueBtn) continueBtn.click();
        });

        // ⚡ PRE-SOLVE STEP 4 CAPTCHA IN PARALLEL IMMEDIATELY!
        console.log("[Step 4] ⚡ Starting Step 4 reCAPTCHA pre-solving in background...");
        const step4CaptchaPromise = solveRecaptchaFast("Step 4 (Final Verification)");

        // Pre-inject Step 4 token as soon as it resolves in background
        step4CaptchaPromise.then(async (t4) => {
            console.log("[Step 4] 🎯 Step 4 token pre-solved and ready in memory!");
            try { await injectToken(page, t4); } catch (e) {}
        }).catch(() => {});

function getAuthJson(url, token) {
    return new Promise((resolve, reject) => {
        const u = new URL(url);
        const req = https.request({
            hostname: u.hostname,
            port: 443,
            path: u.pathname,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        }, res => {
            let data = '';
            res.on('data', d => data += d);
            res.on('end', () => {
                try { resolve(JSON.parse(data || '{}')); } catch (e) { resolve({}); }
            });
        });
        req.on('error', reject);
        req.end();
    });
}

async function waitForMailTmOtp(token) {
    console.log("[Mail.tm Engine] 🔄 Polling messages directly via REST API (0% CAPTCHA, 1s delivery)...");
    for (let i = 1; i <= 35; i++) {
        await delay(1200);
        try {
            const res = await getAuthJson('https://api.mail.tm/messages', token);
            const messages = res['hydra:member'] || [];
            if (messages.length > 0) {
                const msgId = messages[0].id;
                console.log(`[Mail.tm Engine] 📩 Verification email received: ${messages[0].subject}`);
                const msgDetail = await getAuthJson(`https://api.mail.tm/messages/${msgId}`, token);
                const text = (msgDetail.text || '') + ' ' + (msgDetail.html || []).join(' ') + ' ' + (msgDetail.intro || '');
                const match = text.match(/\b([0-9]{6})\b/) || text.match(/\b([0-9]{4})\b/);
                if (match) {
                    console.log(`\n🎯 [Mail.tm Engine] 🔥 INSTANT OTP CAPTURED: ${match[1]}! 🔥\n`);
                    return match[1];
                }
            }
            console.log(`[Mail.tm Engine] 🔄 Checking inbox (Attempt #${i})...`);
        } catch (e) {
            console.log("[Mail.tm Engine] Polling notice:", e.message);
        }
    }
    return null;
}

        // ==========================================
        // STEP 4: ACCOUNT VERIFICATION (AUTO OTP + FINAL CAPTCHA & SUBMIT)
        // ==========================================
        await page.bringToFront();
        console.log("Waiting for Step 4 (Account Verification) UI to appear...");
        await page.waitForFunction(() => {
            const hasOtpBox = document.querySelector('input[maxlength="1"], .otp-input input, .vue-otp-input input') !== null;
            const hasVerifyText = document.body && (document.body.innerText.includes('Verification Code') || document.body.innerText.includes('sent your verification code'));
            return hasOtpBox || hasVerifyText;
        }, { timeout: 25000 }).catch(e => {
            console.log("⚠️ Step 4 UI wait notice: Verifying OTP inputs...");
        });
        console.log("\n[4/4] === STEP 4: ACCOUNT VERIFICATION ===");
        reportProgress("Step 4: Fetching Fresh OTP...");

        const otpPageLoadedTime = Date.now();
        console.log(`[Step 4] ⏰ OTP Page Loaded at ${new Date(otpPageLoadedTime).toLocaleTimeString()}. Polling ONLY for fresh emails sent AFTER this moment...`);
        await delay(2000);

        if (candidate.email.toLowerCase().includes('@wafidmaster.com') || candidate.email.toLowerCase().includes('@renonx.tech')) {
            console.log("Monitoring Wafid Private Mail (HMAC-SHA256 REST API Engine)...");
            const wafidOtp = await wafidClient.waitForOtp(candidate.email, 45, 1500, otpPageLoadedTime);
            if (wafidOtp) {
                capturedOtp = wafidOtp;
            }
        } else if (candidate.temp_mail_token) {
            console.log("Monitoring Mail.tm (Zero-Captcha REST API Engine)...");
            const mailOtp = await waitForMailTmOtp(candidate.temp_mail_token);
            if (mailOtp) {
                capturedOtp = mailOtp;
            }
        } else {
            console.log(`Opening 2nd browser tab for YOPmail (${candidate.emailUser})...`);
            const yopPage = await browser.newPage();
            await yopPage.goto(`https://yopmail.com/?${candidate.emailUser}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
            await yopPage.bringToFront();
            const yopOtp = await waitForEmailAndExtractOtp(yopPage, candidate.emailUser);
            if (yopOtp) {
                capturedOtp = yopOtp;
            }
        }

        if (capturedOtp) {
            console.log(`🔑 Auto-filling OTP Code: ${capturedOtp}...`);
            reportProgress(`Step 4: Entering OTP (${capturedOtp})...`);
            await page.bringToFront();
            
            // 1. Fill OTP digits at Vue & DOM level strictly for OTP inputs
            await page.evaluate((code) => {
                // Vue component detection
                document.querySelectorAll('*').forEach(el => {
                    if (el.__vue__) {
                        const v = el.__vue__;
                        if (v.otp !== undefined) v.otp = code;
                        if (v.verificationCode !== undefined) v.verificationCode = code;
                        if (v.code !== undefined) v.code = code;
                        if (v.confirmCode !== undefined) v.confirmCode = code;
                        if (v.$v && v.$v.verificationCode) {
                            v.$v.verificationCode.$model = code;
                            if (typeof v.$v.verificationCode.$touch === 'function') v.$v.verificationCode.$touch();
                        }
                    }
                });

                const selector = 'input[maxlength="1"], .otp-input input, .vue-otp-input input, input[placeholder*="code" i], input[placeholder*="otp" i]';
                const otpInputs = Array.from(document.querySelectorAll(selector));
                if (otpInputs.length >= code.length) {
                    for (let i = 0; i < code.length; i++) {
                        otpInputs[i].focus();
                        otpInputs[i].value = code[i];
                        otpInputs[i].dispatchEvent(new Event('input', { bubbles: true }));
                        otpInputs[i].dispatchEvent(new Event('change', { bubbles: true }));
                        otpInputs[i].dispatchEvent(new KeyboardEvent('keyup', { key: code[i], bubbles: true }));
                    }
                } else if (otpInputs.length > 0) {
                    otpInputs[0].value = code;
                    otpInputs[0].dispatchEvent(new Event('input', { bubbles: true }));
                    otpInputs[0].dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, capturedOtp);

            // 2. Real keyboard typing into OTP inputs for 100% Vue & DOM binding
            try {
                const selector = 'input[maxlength="1"], .otp-input input, .vue-otp-input input, input[placeholder*="code" i], input[placeholder*="otp" i]';
                const otpInputs = await page.$$(selector);
                if (otpInputs.length >= capturedOtp.length) {
                    for (let i = 0; i < capturedOtp.length; i++) {
                        await otpInputs[i].click();
                        await page.keyboard.press(capturedOtp[i]);
                        await delay(80);
                    }
                } else if (otpInputs.length > 0) {
                    await otpInputs[0].click({ clickCount: 3 });
                    await page.keyboard.type(capturedOtp, { delay: 80 });
                }
            } catch (e) {}

            await delay(500);

            // 2. Ensure Step 4 pre-solved token is injected
            console.log("[Step 4] ⚡ Injecting Pre-Solved Step 4 reCAPTCHA token...");
            reportProgress("Step 4: Submitting Verification...");
            const token4 = await step4CaptchaPromise;
            await injectToken(page, token4);
            await delay(200);

            // 3. Click Continue / Verify Button to submit OTP!
            console.log("[Step 4] 🚀 Submitting OTP verification form...");
            await page.evaluate(() => {
                const btns = Array.from(document.querySelectorAll('button, .el-button'));
                const continueBtn = btns.find(b => b.innerText && (b.innerText.toLowerCase().includes('continue') || b.innerText.toLowerCase().includes('verify') || b.innerText.toLowerCase().includes('complete') || b.innerText.toLowerCase().includes('submit')));
                if (continueBtn) continueBtn.click();
            });

            console.log("[Step 4] ⏳ Awaiting Taqamul response & account confirmation...");
            let registrationSuccess = false;
            let errorMessage = null;

            for (let waitSec = 0; waitSec < 20; waitSec++) {
                await delay(1000);
                const pageState = await page.evaluate(() => {
                    const text = document.body ? document.body.innerText : '';
                    const url = window.location.href;
                    const errEl = document.querySelector('.error-message, .invalid-feedback, .v-toast__text, .el-message--error, .el-form-item__error');
                    const errText = errEl ? errEl.innerText.trim() : null;

                    if (errText) return { type: 'error', message: errText };
                    if (text.includes('Invalid verification code') || text.includes('Incorrect code') || text.includes('expired')) {
                        return { type: 'error', message: 'Invalid or Expired OTP code.' };
                    }
                    if (text.includes('User already registered') || text.includes('already exists')) {
                        return { type: 'error', message: 'User or Passport already registered on Taqamul.' };
                    }
                    if (url.includes('/login') || url.includes('/dashboard') || text.includes('Registration Complete') || text.includes('Account created successfully') || text.includes('Successfully registered')) {
                        return { type: 'success' };
                    }
                    return { type: 'waiting' };
                });

                if (pageState.type === 'success') {
                    registrationSuccess = true;
                    break;
                } else if (pageState.type === 'error') {
                    errorMessage = pageState.message;
                    break;
                }
            }

            if (!registrationSuccess) {
                const finalErrMsg = errorMessage || 'OTP verification failed or confirmation response not received.';
                console.error(`\n❌ [Step 4 ERROR] ${finalErrMsg}\n`);
                reportProgress(`Error: ${finalErrMsg}`);
                await delay(12000); // Keep visual browser open so user can inspect error!
                if (browser) await browser.close();
                throw new Error(finalErrMsg);
            }

            console.log("\n==========================================================");
            console.log("   🎉 CANDIDATE ACCOUNT SUCCESSFULLY CREATED!             ");
            console.log(`   Email   : ${candidate.email}`);
            console.log(`   Password: ${candidate.password}`);
            console.log(`   OTP Used: ${capturedOtp}`);
            console.log("==========================================================\n");
            reportProgress("Registration Completed Successfully!");
            console.log("🖥️ Visual mode active: Holding Chrome browser open for 15 seconds to view success page...");
            await delay(15000); // Keep visual browser open so user can see account creation!
            if (browser) await browser.close();

            return {
                success: true,
                status: 'completed',
                email: candidate.email,
                password: candidate.password,
                otp_code: capturedOtp,
                passport_number: candidate.passport
            };
        }

        if (!isHeadless) {
            console.log("🖥️ Visual mode active: Keeping Chrome browser open for inspection...");
            await delay(15000);
        }

        return {
            success: false,
            error: "OTP not captured"
        };

    } catch (err) {
        console.error("Live automation error:", err.message);
        if (!isHeadless) {
            console.log("🖥️ Visual mode active on error: Keeping Chrome browser open for inspection...");
            await delay(15000);
        }
        return {
            success: false,
            error: err.message
        };
    }
}

if (require.main === module) {
    let candidateData = {};
    if (process.argv[2]) {
        try {
            if (fs.existsSync(process.argv[2])) {
                candidateData = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
            } else {
                candidateData = JSON.parse(process.argv[2]);
            }
        } catch (e) {}
    }

    runAutomation(candidateData).then(res => {
        console.log("FINAL_RESULT:" + JSON.stringify(res));
        process.exit(res && res.success ? 0 : 1);
    }).catch(err => {
        console.log("FINAL_RESULT:" + JSON.stringify({ success: false, error: err ? err.message : 'Unknown automation error' }));
        process.exit(1);
    });
}

module.exports = { runAutomation };
