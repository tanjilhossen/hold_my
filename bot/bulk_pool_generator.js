const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');
const { WafidMailClient } = require('./wafid_mail');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const USER_PASSPORT_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";
const USER_PHOTO_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PHOTO.jpg";
const PAGE_URL = "https://svp-international.pacc.sa/auth/register?role=labor";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const STATUS_FILE = path.join(__dirname, '..', 'storage', 'app', 'bulk_pool_status.json');

const capsolverKey = process.env.CAPSOLVER_API_KEY || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const wafidKeyId = process.env.WAFID_MAIL_KEY_ID || "ak_live_f845898cbeb87d63e21d04a6";
const wafidBaseUrl = process.env.WAFID_MAIL_BASE_URL || "https://mail.wafidmaster.com";

let wafidSecretKey = "";
try {
    const s = execSync(`"D:\\xampp\\php\\php.exe" "E:\\taqamul\\web\\artisan" tinker --execute="echo \\App\\Models\\Setting::get('wafid_mail_secret_key', env('WAFID_MAIL_SECRET_KEY'));"`).toString().trim();
    if (s) wafidSecretKey = s;
} catch (e) {}

const wafidClient = new WafidMailClient(wafidBaseUrl, wafidKeyId, wafidSecretKey);
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

function logStatus(data) {
    try {
        let existing = {};
        if (fs.existsSync(STATUS_FILE)) {
            existing = JSON.parse(fs.readFileSync(STATUS_FILE, 'utf8') || '{}');
        }
        const logs = existing.logs || [];
        if (data.log) {
            const timeStr = new Date().toLocaleTimeString('en-US');
            logs.unshift(`[${timeStr}] ${data.log}`);
            if (logs.length > 50) logs.pop();
        }
        const merged = { ...existing, ...data, logs: logs };
        delete merged.log;
        fs.writeFileSync(STATUS_FILE, JSON.stringify(merged, null, 2));
    } catch (e) {}
}

function postJson(url, payload) {
    return new Promise((resolve, reject) => {
        const data = JSON.stringify(payload);
        const u = new URL(url);
        const req = https.request({
            hostname: u.hostname,
            port: 443,
            path: u.pathname,
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Content-Length': data.length }
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

async function solveRecaptchaFast(label = "Captcha") {
    const startTime = Date.now();
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: capsolverKey,
        task: { type: "ReCaptchaV2TaskProxyLess", websiteURL: PAGE_URL, websiteKey: SITE_KEY }
    });

    if (createRes.errorId !== 0 || !createRes.taskId) {
        throw new Error("CapSolver error: " + JSON.stringify(createRes));
    }

    const taskId = createRes.taskId;
    for (let i = 0; i < 120; i++) {
        await delay(200);
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: capsolverKey,
            taskId: taskId
        });

        if (result.status === "ready") {
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

function getRandomCandidate() {
    const firstNames = ['MD', 'MOHAMMED', 'ABDUL', 'KAZI', 'SHEIKH', 'AL'];
    const lastNames = ['RAHMAN', 'ISLAM', 'HOSSAIN', 'AHMED', 'KARIM', 'ALI', 'KHAN', 'CHOWDHURY', 'HASAN', 'UDDIN', 'MIAH', 'ALAM'];
    const fn = firstNames[Math.floor(Math.random() * firstNames.length)];
    const ln = lastNames[Math.floor(Math.random() * lastNames.length)];
    const randNum = Math.floor(100000 + Math.random() * 900000);
    const emailUser = `pool_${fn.toLowerCase()}${ln.toLowerCase()}_${randNum}`;
    
    // Strictly 4 uppercase English letters
    const prefixes = ['BGDA', 'BGDB', 'BGDC', 'BGDE', 'BDKA', 'BDKB', 'BMEA', 'BMET', 'DHKP', 'CTGQ', 'SYLA', 'RAJX', 'KHLA', 'BARA'];
    const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
    const passport = `${prefix}${Math.floor(100000 + Math.random() * 900000)}`;
    const passPin = Math.floor(1000 + Math.random() * 9000);
    const password = `Taqamul@${passPin}!`;

    const birthYear = 1992 + Math.floor(Math.random() * 8);
    const birthMonth = String(Math.floor(1 + Math.random() * 12)).padStart(2, '0');
    const birthDay = String(Math.floor(1 + Math.random() * 28)).padStart(2, '0');
    const dob = `${birthDay}/${birthMonth}/${birthYear}`;

    const expYear = 2032 + Math.floor(Math.random() * 4);
    const expDate = `${birthDay}/${birthMonth}/${expYear}`;

    return {
        firstName: fn,
        lastName: ln,
        fullName: `${fn} ${ln}`,
        emailUser: emailUser,
        email: `${emailUser}@renonx.tech`,
        passport: passport,
        password: password,
        dob: dob,
        expDate: expDate
    };
}

async function createSingleCandidateAccount(index, total) {
    const candidate = getRandomCandidate();
    console.log(`\n======================================================`);
    console.log(`🚀 [${index}/${total}] CREATING POOL ACCOUNT: ${candidate.fullName}`);
    console.log(`   Email: ${candidate.email} | Passport: ${candidate.passport}`);
    console.log(`======================================================`);

    logStatus({
        current: index,
        last_account: candidate,
        log: `🚀 [${index}/${total}] Registering ${candidate.fullName} (${candidate.email})...`
    });

    try {
        await wafidClient.createMailbox(candidate.emailUser);
    } catch (e) {}

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        userDataDir: path.join(__dirname, 'chrome_cache_data'),
        defaultViewport: { width: 1920, height: 1080 },
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--enable-features=NetworkService,NetworkServiceInProcess',
            '--window-size=1920,1080',
            '--no-first-run',
            '--no-default-browser-check'
        ]
    });

    const pages = await browser.pages();
    const page = pages.length > 0 ? pages[0] : await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

    try {
        // PRE-SOLVE ALL 3 INITIAL STEPS CONCURRENTLY IN PARALLEL!
        const step1CaptchaPromise = solveRecaptchaFast("Step 1");
        const step2CaptchaPromise = solveRecaptchaFast("Step 2");
        const step3CaptchaPromise = solveRecaptchaFast("Step 3");

        await page.goto(PAGE_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await page.waitForFunction(() => document.querySelectorAll('input, button').length >= 3, { timeout: 30000 });
        await delay(800);

        // Upload PASSPORT.jpg from Desktop
        if (fs.existsSync(USER_PASSPORT_FILE)) {
            const fileInputs = await page.$$('input[type="file"], .el-upload__input');
            for (const fi of fileInputs) {
                try { await fi.uploadFile(USER_PASSPORT_FILE); } catch (e) {}
            }
            await delay(300);

            const passportB64 = fs.readFileSync(USER_PASSPORT_FILE).toString('base64');
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
            }, passportB64);
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

        await delay(1500);

        // 2. Nationality Selection: Bangladesh
        const natInput = await page.$('input[placeholder*="nationality" i]');
        if (natInput) {
            await natInput.click();
            await delay(400);
            await page.evaluate(() => {
                const visible = Array.from(document.querySelectorAll('.el-select-dropdown')).filter(d => d.style.display !== 'none');
                for (const drop of visible) {
                    const firstItem = drop.querySelector('.el-select-dropdown__item, li');
                    if (firstItem) { firstItem.click(); return; }
                }
                const allItems = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = allItems.find(el => el.innerText && el.innerText.trim().toLowerCase() === 'bangladesh') || allItems[0];
                if (match) match.click();
            });
            await delay(300);
            await page.mouse.click(50, 50);
        }

        // Dates
        const dateInputs = await page.$$('input[placeholder*="DD/MM/YYYY"]');
        if (dateInputs.length > 0) {
            await dateInputs[0].click();
            await page.keyboard.down('Control'); await page.keyboard.press('KeyA'); await page.keyboard.up('Control'); await page.keyboard.press('Backspace');
            await dateInputs[0].type(candidate.dob, { delay: 20 });
            await page.keyboard.press("Enter");
            await delay(150);
        }
        if (dateInputs.length > 1) {
            await dateInputs[1].click();
            await page.keyboard.down('Control'); await page.keyboard.press('KeyA'); await page.keyboard.up('Control'); await page.keyboard.press('Backspace');
            await dateInputs[1].type(candidate.expDate, { delay: 20 });
            await page.keyboard.press("Enter");
            await delay(150);
        }
        await page.mouse.click(50, 50);

        // Sex: Male
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

        // Inject Step 1 Token
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
        await page.waitForFunction(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            return btns.some(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
        }, { timeout: 15000 });

        // Confirm Modal
        // Confirm Passport Modal using real Puppeteer click
        console.log("Confirming Modal ('Confirm and proceed')...");
        try {
            const dialogCheckbox = await page.$('.el-dialog input[type="checkbox"], .el-checkbox__original, input[type="checkbox"]');
            if (dialogCheckbox) {
                await dialogCheckbox.click();
                await delay(300);
            }
            const dialogBtns = await page.$$('.el-dialog button, .el-dialog .el-button');
            for (const b of dialogBtns) {
                const text = await page.evaluate(el => el.innerText, b);
                if (text && text.toLowerCase().includes('confirm and proceed')) {
                    await b.click();
                    break;
                }
            }
        } catch (e) {}

        await delay(300);

        // Fallback DOM click
        await page.evaluate(() => {
            const checkboxes = Array.from(document.querySelectorAll('.el-dialog input[type="checkbox"], input[type="checkbox"]'));
            checkboxes.forEach(c => {
                c.checked = true;
                c.dispatchEvent(new Event('input', { bubbles: true }));
                c.dispatchEvent(new Event('change', { bubbles: true }));
            });
            const btns = Array.from(document.querySelectorAll('.el-dialog button, button, .el-button'));
            const proceedBtn = btns.find(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
            if (proceedBtn) proceedBtn.click();
        });

        console.log("✅ Step 1 Completed! Moving to Step 2...");

        // ==========================================
        // STEP 2: OTHER DETAILS
        // ==========================================
        await page.waitForFunction(() => {
            return document.querySelector('input[placeholder*="national ID" i], input[name="nationalId"], input[placeholder*="education" i]') !== null;
        }, { timeout: 35000 });

        // Upload PHOTO.jpg from Desktop
        if (fs.existsSync(USER_PHOTO_FILE)) {
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
                        if (v.$options && v.$options.name === 'OtherDetails') v.photoFile = fileObj;
                    }
                });
            }, photoB64);
        }

        // Fill National ID
        const nidInput = await page.$('input[placeholder*="national ID" i], input[name="nationalId"]');
        if (nidInput) {
            await nidInput.type(`199${Math.floor(1000000000 + Math.random() * 9000000000)}`, { delay: 10 });
        }

        // Select Education & Experience
        const dropdownInputs = await page.$$('input[placeholder*="Select" i], input[placeholder*="education" i], input[placeholder*="experience" i]');
        for (const drop of dropdownInputs) {
            await drop.click();
            await delay(200);
            await page.evaluate(() => {
                const visible = Array.from(document.querySelectorAll('.el-select-dropdown')).filter(d => d.style.display !== 'none');
                for (const d of visible) {
                    const first = d.querySelector('.el-select-dropdown__item, li');
                    if (first) { first.click(); return; }
                }
            });
            await delay(150);
        }

        // Institute Name
        const instInput = await page.$('input[placeholder*="institute" i], input[placeholder*="school" i]');
        if (instInput) await instInput.type('Dhaka Technical Institute', { delay: 10 });

        // Passwords
        const pInputs = await page.$$('input[type="password"]');
        if (pInputs.length > 0) await pInputs[0].type(candidate.password, { delay: 10 });
        if (pInputs.length > 1) await pInputs[1].type(candidate.password, { delay: 10 });

        // Inject Step 2 Token
        const token2 = await step2CaptchaPromise;
        await injectToken(page, token2);
        await delay(300);

        // Submit Step 2
        await page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button, .el-button'));
            const continueBtn = buttons.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        // ==========================================
        // STEP 3: CONTACT INFORMATION
        // ==========================================
        const step4CaptchaPromise = solveRecaptchaFast("Step 4");

        await page.waitForFunction(() => {
            return document.querySelector('input[type="email"], input[placeholder*="email" i]') !== null;
        }, { timeout: 35000 });

        const emailInput = await page.$('input[type="email"], input[placeholder*="email" i]');
        if (emailInput) await emailInput.type(candidate.email, { delay: 15 });

        const phoneInput = await page.$('input[type="tel"], input[placeholder*="phone" i], input[placeholder*="mobile" i]');
        if (phoneInput) await phoneInput.type(`17${Math.floor(10000000 + Math.random() * 90000000)}`, { delay: 10 });

        // Inject Step 3 Token
        const token3 = await step3CaptchaPromise;
        await injectToken(page, token3);
        await delay(300);

        const step3SubmitTime = Date.now();

        // Submit Step 3
        await page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button, .el-button'));
            const continueBtn = buttons.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        // ==========================================
        // STEP 4: ACCOUNT VERIFICATION (AUTO OTP)
        // ==========================================
        await page.waitForFunction(() => {
            const otpBox = document.querySelector('.otp-input input, .vue-otp-input input, input[maxlength="1"]');
            const emIn = document.querySelector('input[type="email"]');
            return otpBox !== null || !emIn;
        }, { timeout: 35000 });

        console.log("Waiting for instant OTP from Wafid Private Mail server...");
        const capturedOtp = await wafidClient.waitForOtp(candidate.emailUser, 45, 1500, step3SubmitTime);

        if (!capturedOtp) {
            throw new Error("OTP timeout on Wafid Private Mail server");
        }

        console.log(`🎯 OTP Captured from Wafid Mail: ${capturedOtp}! Auto-filling...`);

        // Fill OTP
        await page.evaluate((code) => {
            const otpInputs = Array.from(document.querySelectorAll('.otp-input input, .vue-otp-input input, input[maxlength="1"], input[type="tel"]'));
            if (otpInputs.length >= code.length) {
                for (let i = 0; i < code.length; i++) {
                    otpInputs[i].value = code[i];
                    otpInputs[i].dispatchEvent(new Event('input', { bubbles: true }));
                    otpInputs[i].dispatchEvent(new Event('change', { bubbles: true }));
                }
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

        // Inject Step 4 Token
        const token4 = await step4CaptchaPromise;
        await injectToken(page, token4);
        await delay(200);

        // Submit Step 4
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button, .el-button'));
            const continueBtn = btns.find(b => b.innerText && (b.innerText.toLowerCase().includes('continue') || b.innerText.toLowerCase().includes('verify') || b.innerText.toLowerCase().includes('complete') || b.innerText.toLowerCase().includes('submit')));
            if (continueBtn) continueBtn.click();
        });

        await delay(3000);

        // Acquire Taqamul Bearer Token
        console.log("Logging into Taqamul for live Bearer Token...");
        let liveToken = null;
        try {
            const loginCaptcha = await solveRecaptchaFast("Taqamul Login");
            const loginRes = await postJson("https://svp-international-api.pacc.sa/api/v1/users/login?locale=en", {
                user: { email: candidate.email, password: candidate.password, role: "labor" },
                recaptcha_response: loginCaptcha
            });
            if (loginRes.token) {
                liveToken = loginRes.token;
            }
        } catch (e) {}

        // Add to PHP DB INSTANTLY (1-by-1 live insertion)
        const phpPayload = JSON.stringify({
            name: candidate.fullName,
            email: candidate.email,
            password: candidate.password,
            token: liveToken,
            status: liveToken ? 'active' : 'idle'
        });

        execSync(`"D:\\xampp\\php\\php.exe" "E:\\taqamul\\web\\artisan" tinker --execute="
            \\$acc = json_decode('${phpPayload.replace(/'/g, "\\'")}', true);
            \\$service = app(\\App\\Services\\TaqamulTokenService::class);
            \\$pool = \\$service->getPoolAccounts();
            \\$pool[] = [
                'id' => time() . rand(10, 99),
                'name' => \\$acc['name'],
                'email' => \\$acc['email'],
                'password' => \\$acc['password'],
                'token' => \\$acc['token'],
                'token_expires_at' => date('Y-m-d H:i:s', strtotime('+48 hours')),
                'status' => \\$acc['status']
            ];
            \\$service->savePoolAccounts(\\$pool);
        "`);

        logStatus({
            log: `✅ [${index}/${total}] ${candidate.fullName} added to Candidate Pool! (${candidate.email})`
        });

        console.log(`\n🎉 [${index}/${total}] SUCCESS: Account created and added to Candidate Pool! (${candidate.email})`);
        return true;

    } catch (err) {
        console.error(`❌ [${index}/${total}] Failed:`, err.message);
        logStatus({
            log: `⚠️ [${index}/${total}] Account registration attempt failed: ${err.message}`
        });
        return false;
    } finally {
        await browser.close().catch(() => {});
    }
}

// MAIN RUNNER
(async () => {
    const args = process.argv.slice(2);
    let targetCount = 50;

    for (const arg of args) {
        if (arg.startsWith('--count=')) targetCount = parseInt(arg.split('=')[1]) || 50;
    }

    logStatus({
        running: true,
        target: targetCount,
        current: 0,
        success_count: 0,
        failed_count: 0,
        log: `🌟 Bulk Candidate Pool Generator started! Target: ${targetCount} accounts.`
    });

    let successCount = 0;
    let failedCount = 0;

    for (let i = 1; i <= targetCount; i++) {
        const ok = await createSingleCandidateAccount(i, targetCount);
        if (ok) successCount++;
        else failedCount++;

        logStatus({
            current: i,
            success_count: successCount,
            failed_count: failedCount
        });

        await delay(1000);
    }

    logStatus({
        running: false,
        log: `🎉 Bulk Generator completed! Created ${successCount}/${targetCount} accounts successfully.`
    });
})();
