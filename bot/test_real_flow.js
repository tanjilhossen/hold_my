const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');
const fs = require('fs');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const CAPSOLVER_KEY = "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const PAGE_URL = "https://svp-international.pacc.sa/auth/register?role=labor";
const USER_PASSPORT_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";

const delay = (ms) => new Promise(r => setTimeout(r, ms));

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

async function solveRecaptcha() {
    console.log("[CapSolver] ⚡ Solving reCAPTCHA v2 with CapSolver AI...");
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: PAGE_URL,
            websiteKey: SITE_KEY
        }
    });

    const taskId = createRes.taskId;
    for (let i = 0; i < 35; i++) {
        await delay(1000);
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: taskId
        });

        if (result.status === "ready") {
            console.log("[CapSolver] 🎯 reCAPTCHA Solved!");
            return result.solution.gRecaptchaResponse;
        }
    }
    throw new Error("CapSolver timeout");
}

(async () => {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: false,
        defaultViewport: null,
        args: ['--start-maximized', '--no-sandbox']
    });

    const page = await browser.newPage();
    console.log("Navigating to Taqamul Registration...");
    await page.goto(PAGE_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await delay(2000);

    // 1. Upload Passport using FileChooser by clicking Upload Passport button
    console.log("Looking for 'Upload Passport' button...");
    const uploadBtn = await page.waitForSelector('button[data-test-id="uploadPassportBtnId"], .upload-file button, button', { timeout: 10000 }).catch(() => null);

    if (uploadBtn && fs.existsSync(USER_PASSPORT_FILE)) {
        console.log("Clicking 'Upload Passport' and selecting PASSPORT.jpg...");
        
        // Listen for file chooser
        const [fileChooser] = await Promise.all([
            page.waitForFileChooser({ timeout: 5000 }).catch(() => null),
            uploadBtn.click()
        ]);

        if (fileChooser) {
            await fileChooser.accept([USER_PASSPORT_FILE]);
            console.log("✅ File passed to file chooser!");
        } else {
            // Check for hidden file inputs
            const inputs = await page.$$('input[type="file"]');
            for (const inp of inputs) {
                await inp.uploadFile(USER_PASSPORT_FILE).catch(() => {});
            }
        }
    }

    // Wait for OCR processing or manual modal
    console.log("Waiting for form inputs to appear after passport upload...");
    await page.waitForFunction(() => {
        return document.querySelectorAll('input[placeholder*="name" i], input[placeholder*="country" i]').length >= 2 || document.querySelector('.passport-validation-modal, .el-dialog');
    }, { timeout: 20000 }).catch(() => {});
    await delay(1000);

    // If "system could not recognize passport" modal appeared, click Enter Manually
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('.el-dialog button, button'));
        const manualBtn = btns.find(b => b.innerText && (b.innerText.toLowerCase().includes('manual') || b.innerText.toLowerCase().includes('enter')));
        if (manualBtn) manualBtn.click();
    });
    await delay(1000);

    // 2. Fill First Name & Last Name
    const firstName = await page.$('input[placeholder*="first name" i], input[placeholder*="given" i]');
    if (firstName) {
        await firstName.click({ clickCount: 3 });
        await firstName.type("MD", { delay: 30 });
    }

    const lastName = await page.$('input[placeholder*="last name" i], input[placeholder*="surname" i]');
    if (lastName) {
        await lastName.click({ clickCount: 3 });
        await lastName.type("HASAN", { delay: 30 });
    }

    const passInput = await page.$('input[placeholder*="passport" i], input[placeholder*="password number" i]');
    if (passInput) {
        await passInput.click({ clickCount: 3 });
        await passInput.type("A" + Math.floor(10000000 + Math.random() * 90000000), { delay: 30 });
    }

    // 3. Select Country: Bangladesh
    console.log("Selecting Country: Bangladesh...");
    const countryInput = await page.$('input[placeholder*="country" i]');
    if (countryInput) {
        await countryInput.click();
        await delay(500);
        await page.evaluate(() => {
            const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
            const match = items.find(el => el.innerText && el.innerText.trim().toLowerCase() === 'bangladesh');
            if (match) match.click();
        });
    }

    // Wait for nationality list
    console.log("Waiting for nationality list to load...");
    await page.waitForFunction(() => {
        let ready = false;
        document.querySelectorAll('*').forEach(el => {
            if (el.__vue__ && el.__vue__.$options && el.__vue__.$options.name === 'PassportInformation') {
                if (el.__vue__.nationalities && el.__vue__.nationalities.length > 0 && !el.__vue__.nationalityIsLoading) {
                    ready = true;
                }
            }
        });
        return ready;
    }, { timeout: 15000 }).catch(() => {});
    await delay(600);

    // 4. Select Nationality: Bangladesh
    console.log("Selecting Nationality: Bangladesh...");
    const natInput = await page.$('input[placeholder*="nationality" i]');
    if (natInput) {
        await natInput.click();
        await delay(500);
        await page.evaluate(() => {
            const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
            const match = items.find(el => el.innerText && el.innerText.trim().toLowerCase() === 'bangladesh');
            if (match) match.click();
        });
    }
    await delay(400);

    // 5. Fill Dates
    const dateInputs = await page.$$('input[placeholder*="DD/MM/YYYY"]');
    if (dateInputs.length > 0) {
        await dateInputs[0].click({ clickCount: 3 });
        await dateInputs[0].type("12/04/1996", { delay: 30 });
        await page.keyboard.press("Enter");
        await page.mouse.click(50, 50);
        await delay(200);
    }
    if (dateInputs.length > 1) {
        await dateInputs[1].click({ clickCount: 3 });
        await dateInputs[1].type("18/10/2034", { delay: 30 });
        await page.keyboard.press("Enter");
        await page.mouse.click(50, 50);
        await delay(200);
    }

    // 6. NOW SOLVE CAPTCHA WHEN WIDGET IS VISIBLE
    const token = await solveRecaptcha();
    console.log("Injecting solved CapSolver token...");
    await page.evaluate((t) => {
        document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(ta => {
            ta.value = t;
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
                if (typeof v.successCaptcha === 'function') {
                    try { v.successCaptcha(t); } catch (e) {}
                }
                if (v.$refs && v.$refs.recaptcha) {
                    v.$refs.recaptcha.recaptchaResponse = t;
                    v.$refs.recaptcha.recaptchaStatus = 'ready';
                }
            }
        });
    }, token);
    await delay(800);

    // 7. Click Continue
    console.log("Clicking Continue button...");
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('button, .el-button'));
        const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
        if (continueBtn) continueBtn.click();
    });

    await delay(2000);

    // 8. Confirm Modal ("Confirm and proceed")
    console.log("Waiting for 'Confirm and proceed' modal...");
    await page.waitForFunction(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        return btns.some(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
    }, { timeout: 10000 });

    console.log("Ticking modal checkboxes and confirming...");
    await page.evaluate(() => {
        document.querySelectorAll('input[type="checkbox"]').forEach(c => { if (!c.checked) c.click(); });
    });
    await delay(300);
    await page.evaluate(() => {
        const btns = Array.from(document.querySelectorAll('.el-dialog button, button'));
        const proceedBtn = btns.find(b => b.innerText && b.innerText.toLowerCase().includes('confirm and proceed'));
        if (proceedBtn) proceedBtn.click();
    });

    console.log("🎉 SUCCESS: STEP 1 CONFIRMED! STEP 2 IS LOADING!");
    await delay(5000);
    await browser.close();
})();
