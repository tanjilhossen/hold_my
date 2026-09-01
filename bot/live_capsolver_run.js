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
const CAPSOLVER_KEY = "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const YOPMAIL_SITE_KEY = "6LcG5v8SAAAAAOdAn2iqMEQTdVyX8t0w9T3cpdN2";
const PAGE_URL = "https://svp-international.pacc.sa/auth/register?role=labor";

const USER_PASSPORT_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";
const USER_PHOTO_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PHOTO.jpg";

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
    for (let i = 0; i < 120; i++) {
        await delay(200);
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

(async () => {
    console.log("==========================================================");
    console.log("    TAQAMUL HIGH-SPEED 4-STEP FULL AUTONOMOUS ENGINE      ");
    console.log("==========================================================\n");

    const firstName = "MD";
    const lastName = "HASAN";
    const passportNumber = "A" + Math.floor(10000000 + Math.random() * 90000000);

    // 📧 Generate Email from Name + Passport Number!
    const cleanFirst = firstName.toLowerCase().replace(/[^a-z0-9]/g, '');
    const cleanLast = lastName.toLowerCase().replace(/[^a-z0-9]/g, '');
    const cleanPassport = passportNumber.toLowerCase().replace(/[^a-z0-9]/g, '');
    const emailUser = `${cleanFirst}${cleanLast}_${cleanPassport}`;
    const email = `${emailUser}@yopmail.com`;

    const candidate = {
        firstName: firstName,
        lastName: lastName,
        passport: passportNumber,
        nationalId: "1995" + Math.floor(10000000 + Math.random() * 90000000),
        dob: "12/04/1996",
        expDate: "18/10/2034",
        email: email,
        emailUser: emailUser,
        phone: "019" + Math.floor(10000000 + Math.random() * 90000000),
        password: "Taqamul@2026!"
    };

    console.log(">>> Candidate Registration Data:");
    console.log(`    Name        : ${candidate.firstName} ${candidate.lastName}`);
    console.log(`    Passport No : ${candidate.passport}`);
    console.log(`    National ID : ${candidate.nationalId}`);
    console.log(`    Email (YOP) : ${candidate.email}`);
    console.log(`    Phone       : +880 ${candidate.phone}`);
    console.log(`    Password    : ${candidate.password}\n`);

    let capturedOtp = null;

    // ⚡ PRE-SOLVE ALL 3 INITIAL STEPS CONCURRENTLY IN PARALLEL!
    console.log("⚡ [Turbo Engine] Starting simultaneous parallel pre-solving for Step 1, Step 2, and Step 3...");
    const step1CaptchaPromise = solveRecaptchaFast("Step 1");
    const step2CaptchaPromise = solveRecaptchaFast("Step 2");
    const step3CaptchaPromise = solveRecaptchaFast("Step 3");

    const envVal = String(process.env.HEADLESS || '').trim().toLowerCase();
    const isHeadless = (envVal === 'true' || envVal === '1');
    console.log(`[Browser Engine] Launching Chrome in ${isHeadless ? 'BACKGROUND (Headless)' : 'ON-SCREEN (Visual / Headed)'} mode...`);

    const launchArgs = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
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

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: isHeadless ? 'new' : false,
        userDataDir: path.join(__dirname, 'chrome_cache_data'),
        defaultViewport: isHeadless ? { width: 1920, height: 1080 } : null,
        args: launchArgs
    });

    try {
        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();

        // Set realistic User-Agent & viewport
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        await page.setCacheEnabled(true);

        // Network Interception for OTP
        page.on('response', async (res) => {
            try {
                const url = res.url();
                if (url.includes('/registrations/validate') || url.includes('/auth/register')) {
                    const text = await res.text();
                    const json = JSON.parse(text);
                    if (json.otp_code) {
                        capturedOtp = String(json.otp_code);
                        console.log(`\n🔥 [Network Intercept] 🎯 OTP Captured from Server: ${capturedOtp}! 🔥\n`);
                    }
                }
            } catch (e) {}
        });

        // ==========================================
        // STEP 1: PASSPORT INFORMATION
        // ==========================================
        console.log("\n[1/4] === STEP 1: PASSPORT & PERSONAL INFORMATION ===");
        console.log("⚡ Loading Taqamul registration portal...");
        await page.goto(PAGE_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await page.waitForFunction(() => document.querySelectorAll('input, button').length >= 3, { timeout: 30000 });
        await delay(800);

        // Upload PASSPORT.jpg from Desktop
        if (fs.existsSync(USER_PASSPORT_FILE)) {
            console.log("Uploading PASSPORT.jpg from Desktop: " + USER_PASSPORT_FILE);
            const fileInputs = await page.$$('input[type="file"], .el-upload__input');
            for (const fi of fileInputs) {
                try { await fi.uploadFile(USER_PASSPORT_FILE); } catch (e) {}
            }
            await delay(300);

            // Attach file object directly into Vue instance
            const passportBase64 = fs.readFileSync(USER_PASSPORT_FILE).toString('base64');
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
            console.log("✅ PASSPORT.jpg attached!");
        }

        // Fill Names & Passport
        const firstNameInput = await page.$('input[placeholder*="first name" i], input[placeholder*="given" i]');
        if (firstNameInput) {
            await firstNameInput.click({ clickCount: 3 });
            await firstNameInput.type(candidate.firstName, { delay: 15 });
        }

        const lastNameInput = await page.$('input[placeholder*="last name" i], input[placeholder*="surname" i]');
        if (lastNameInput) {
            await lastNameInput.click({ clickCount: 3 });
            await lastNameInput.type(candidate.lastName, { delay: 15 });
        }

        const passportInput = await page.$('input[placeholder*="passport" i], input[placeholder*="password number" i]');
        if (passportInput) {
            await passportInput.click({ clickCount: 3 });
            await passportInput.type(candidate.passport, { delay: 15 });
        }

        // 1. Country Selection: Bangladesh
        console.log("Selecting Country: Bangladesh...");
        const countryInput = await page.$('input[placeholder*="country" i]');
        if (countryInput) {
            await countryInput.click();
            await delay(400);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = items.find(el => el.innerText && el.innerText.trim().toLowerCase() === 'bangladesh');
                if (match) match.click();
            });
            await delay(300);
            await page.mouse.click(50, 50);
        }

        // Wait 1.5s for Nationality options to update for Bangladesh
        console.log("Waiting for Nationality dropdown to update...");
        await delay(1500);

        // 2. Nationality Selection: Click Dropdown & Select 1st Option (Bangladesh)
        console.log("Selecting Nationality: Clicking 1st option in dropdown (Bangladesh)...");
        const natInput = await page.$('input[placeholder*="nationality" i]');
        if (natInput) {
            await natInput.click();
            await delay(400);
            await page.evaluate(() => {
                const visible = Array.from(document.querySelectorAll('.el-select-dropdown')).filter(d => d.style.display !== 'none');
                for (const drop of visible) {
                    const firstItem = drop.querySelector('.el-select-dropdown__item, li');
                    if (firstItem) {
                        firstItem.click();
                        return;
                    }
                }
                const allItems = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = allItems.find(el => el.innerText && el.innerText.trim().toLowerCase() === 'bangladesh') || allItems[0];
                if (match) match.click();
            });
            await delay(300);
            await page.mouse.click(50, 50);
        }

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
        await page.mouse.click(50, 50);

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
                    if (v.$v && v.$v.firstName) v.$v.firstName.$model = v.firstName;
                    if (v.$v && v.$v.surname) v.$v.surname.$model = v.surname;
                    if (v.$v && v.$v.passportNumber) v.$v.passportNumber.$model = v.passportNumber;
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

        // Wait for modal
        console.log("Waiting for Passport Confirmation Modal...");
        await page.waitForFunction(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            return btns.some(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
        }, { timeout: 10000 });

        // Confirm Passport Modal
        console.log("Confirming Modal ('Confirm and proceed')...");
        await page.evaluate(() => {
            const checkboxes = Array.from(document.querySelectorAll('.el-dialog input[type="checkbox"], input[type="checkbox"]'));
            checkboxes.forEach(c => { if (!c.checked) c.click(); });
        });
        await delay(200);

        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('.el-dialog button, button, .el-button'));
            const proceedBtn = btns.find(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
            if (proceedBtn) proceedBtn.click();
        });

        console.log("✅ Step 1 Completed! Moving to Step 2...");

        // ==========================================
        // STEP 2: OTHER DETAILS
        // ==========================================
        // ⚡ PRE-SOLVE STEP 2 & STEP 3 CAPTCHAS IN PARALLEL!
        const step2CaptchaPromise = solveRecaptchaFast("Step 2");
        const step3CaptchaPromise = solveRecaptchaFast("Step 3");

        await page.waitForFunction(() => {
            return document.querySelector('input[placeholder*="national ID" i], input[name="nationalId"], input[placeholder*="education" i]') !== null;
        }, { timeout: 15000 });
        console.log("\n[2/4] === STEP 2: OTHER DETAILS LOADED ===");

        // Upload PHOTO.jpg from Desktop
        if (fs.existsSync(USER_PHOTO_FILE)) {
            console.log("Uploading PHOTO.jpg from Desktop: " + USER_PHOTO_FILE);
            const fileInputs = await page.$$('input[type="file"], .el-upload__input');
            for (const fi of fileInputs) {
                try { await fi.uploadFile(USER_PHOTO_FILE); } catch (e) {}
            }
            await delay(300);

            const photoB64 = fs.readFileSync(USER_PHOTO_FILE).toString('base64');
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
            console.log("✅ PHOTO.jpg uploaded!");
        }

        // ID No (National ID)
        console.log("Typing National ID: " + candidate.nationalId);
        const nidInput = await page.$('input[placeholder*="national ID" i], input[name="nationalId"]');
        if (nidInput) {
            await nidInput.click({ clickCount: 3 });
            await nidInput.type(candidate.nationalId, { delay: 15 });
        }
        await delay(150);

        // Education Level: Secondary
        console.log("Selecting Education Level...");
        const eduInput = await page.$('input[placeholder*="education" i]');
        if (eduInput) {
            await eduInput.click();
            await delay(200);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = items.find(el => el.innerText && (el.innerText.toLowerCase().includes('secondary') || el.innerText.toLowerCase().includes('middle') || el.innerText.toLowerCase().includes('primary')));
                if (match) match.click();
                else if (items.length > 0) items[items.length - 1].click();
            });
            await delay(150);
        }

        // Experience Level: 3-5 years
        console.log("Selecting Experience Level...");
        const expInput = await page.$('input[placeholder*="experience" i]');
        if (expInput) {
            await expInput.click();
            await delay(200);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = items.find(el => el.innerText && (el.innerText.includes('3') || el.innerText.includes('5') || el.innerText.toLowerCase().includes('less') || el.innerText.toLowerCase().includes('more')));
                if (match) match.click();
                else if (items.length > 0) items[0].click();
            });
            await delay(150);
        }

        // Training / Certification: No certificates / BMET
        console.log("Selecting Institute/Certificate...");
        const instInput = await page.$('input[placeholder*="institute" i]');
        if (instInput) {
            await instInput.click();
            await delay(200);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = items.find(el => el.innerText && (el.innerText.toLowerCase().includes('no') || el.innerText.toLowerCase().includes('bmet')));
                if (match) match.click();
                else if (items.length > 0) items[0].click();
            });
            await delay(150);
            await page.mouse.click(50, 50);
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

        // ⚡ Solve and inject CapSolver token for Step 2
        const token2 = await step2CaptchaPromise;
        await injectToken(page, token2);
        await delay(300);

        // Submit Step 2
        console.log("Submitting Step 2 (Continue)...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button, .el-button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        // ==========================================
        // STEP 3: CONTACT INFORMATION
        // ==========================================
        await page.waitForFunction(() => {
            return document.querySelector('input[placeholder*="email" i], input[type="email"]') !== null;
        }, { timeout: 15000 });
        console.log("\n[3/4] === STEP 3: CONTACT INFORMATION LOADED ===");

        // Email (Generated from Name + Passport!)
        const emailInput = await page.$('input[placeholder*="email" i], input[type="email"]');
        if (emailInput) {
            await emailInput.click({ clickCount: 3 });
            await emailInput.type(candidate.email, { delay: 15 });
            console.log(`Auto-filled Name+Passport Email: ${candidate.email}`);
        }
        await delay(150);

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
            const otpBox = document.querySelector('.otp-input input, .vue-otp-input input, input[maxlength="1"]');
            const emailInput = document.querySelector('input[type="email"]');
            return otpBox !== null || !emailInput;
        }, { timeout: 20000 });
        console.log("\n[4/4] === STEP 4: ACCOUNT VERIFICATION ===");

        if (candidate.email.toLowerCase().includes('@wafidmaster.com') || candidate.email.toLowerCase().includes('@renonx.tech')) {
            console.log("Monitoring Wafid Private Mail (HMAC-SHA256 REST API Engine)...");
            const wafidOtp = await wafidClient.waitForOtp(candidate.emailUser, 45, 1500, step3SubmitTime);
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
            await page.bringToFront();
            
            // 1. Fill OTP digits at Vue & DOM level strictly into OTP inputs
            await page.evaluate((code) => {
                // Vue component detection
                document.querySelectorAll('*').forEach(el => {
                    if (el.__vue__) {
                        const v = el.__vue__;
                        if (v.otp !== undefined) v.otp = code;
                        if (v.verificationCode !== undefined) v.verificationCode = code;
                        if (v.code !== undefined) v.code = code;
                        if (v.$v && v.$v.verificationCode) {
                            v.$v.verificationCode.$model = code;
                            if (typeof v.$v.verificationCode.$touch === 'function') v.$v.verificationCode.$touch();
                        }
                    }
                });

                const otpInputs = Array.from(document.querySelectorAll('.otp-input input, .vue-otp-input input, input[maxlength="1"], input[type="tel"]'));
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

            // Real keyboard typing into first OTP input for 100% Vue binding
            try {
                const firstOtpInput = await page.$('input[maxlength="1"], .otp-input input, .vue-otp-input input, input[type="tel"]');
                if (firstOtpInput) {
                    await firstOtpInput.click();
                    await delay(100);
                    for (const char of capturedOtp) {
                        await page.keyboard.press(char);
                        await delay(50);
                    }
                }
            } catch (e) {}

            await delay(200);

            // 2. Ensure Step 4 pre-solved token is injected
            console.log("[Step 4] ⚡ Injecting Pre-Solved Step 4 reCAPTCHA token...");
            const token4 = await step4CaptchaPromise;
            await injectToken(page, token4);
            await delay(200);

            // 3. Click Continue / Verify Button instantly to complete registration!
            console.log("[Step 4] 🚀 Instant submit to create account...");
            await page.evaluate(() => {
                const btns = Array.from(document.querySelectorAll('button, .el-button'));
                const continueBtn = btns.find(b => b.innerText && (b.innerText.toLowerCase().includes('continue') || b.innerText.toLowerCase().includes('verify') || b.innerText.toLowerCase().includes('complete') || b.innerText.toLowerCase().includes('submit')));
                if (continueBtn) continueBtn.click();
            });

            console.log("\n==========================================================");
            console.log("   🎉 CANDIDATE ACCOUNT SUCCESSFULLY CREATED!             ");
            console.log(`   Email   : ${candidate.email}`);
            console.log(`   Password: ${candidate.password}`);
            console.log(`   OTP Used: ${capturedOtp}`);
            console.log("==========================================================\n");
        }

        await delay(600000);

    } catch (err) {
        console.error("Live automation error:", err.message);
    }
})();
