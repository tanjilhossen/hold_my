const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const CAPSOLVER_KEY = process.env.CAPSOLVER_API_KEY || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const YOPMAIL_SITE_KEY = "6LcG5v8SAAAAAOdAn2iqMEQTdVyX8t0w9T3cpdN2";

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

async function solveRecaptchaFast() {
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: "https://yopmail.com",
            websiteKey: YOPMAIL_SITE_KEY
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
            return result.solution.gRecaptchaResponse;
        }

        if (result.status === "failed" || result.errorId !== 0) {
            throw new Error("CapSolver failed: " + JSON.stringify(result));
        }
    }
    throw new Error("CapSolver timeout");
}

async function fetchYopmailOtp(emailOrUser) {
    const user = emailOrUser.replace(/@yopmail\.com$/i, '').trim();

    // ⚡ PRE-SOLVE YOPMAIL RECAPTCHA IN PARALLEL WITH BROWSER LAUNCH!
    const tokenPromise = solveRecaptchaFast().catch(() => null);

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: "new",
        args: [
            '--headless=new',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--window-position=-3000,-3000'
        ]
    });

    try {
        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        const url = `https://yopmail.com/?${encodeURIComponent(user)}`;
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 25000 });
        await delay(800);

        // Check if captcha is shown
        const captchaNeeded = await page.evaluate(() => {
            const rParent = document.getElementById('r_parent');
            const hasRParent = rParent && (rParent.style.display !== 'none' || rParent.offsetWidth > 0);
            const hasRc = document.querySelector('iframe[src*="recaptcha"]') !== null;
            const text = document.body ? document.body.innerText : '';
            return hasRParent || hasRc || text.includes('Complete the CAPTCHA') || text.includes("I'm not a robot");
        }).catch(() => false);

        if (captchaNeeded) {
            const token = await tokenPromise;
            if (token) {
                await page.evaluate((t) => {
                    document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(ta => {
                        ta.value = t;
                        ta.innerHTML = t;
                        ta.dispatchEvent(new Event('input', { bubbles: true }));
                        ta.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    if (typeof refr === 'function') try { refr(1, null, null, null, t); } catch (e) {}
                    if (typeof RcCallback === 'function') try { RcCallback(t); } catch (e) {}
                    if (typeof hideRc === 'function') try { hideRc(); } catch (e) {}
                    
                    const rParent = document.getElementById('r_parent');
                    if (rParent) rParent.style.display = 'none';
                    const cptEl = document.getElementById('cpt');
                    if (cptEl) cptEl.style.display = 'none';
                }, token);
                await delay(1000);
            }
        } else {
            // Click native refresh
            await page.evaluate(() => {
                if (typeof r === 'function') try { r(); } catch (e) {}
                const rBtn = document.getElementById('refresh');
                if (rBtn) rBtn.click();
            }).catch(() => {});
            await delay(800);
        }

        // Open top-most email
        const ifinbox = page.frames().find(f => f.name() === 'ifinbox');
        if (ifinbox) {
            await ifinbox.evaluate(() => {
                const topMsg = document.querySelector('button.lm, .lm, .m');
                if (topMsg) topMsg.click();
            }).catch(() => {});
        }
        await delay(500);

        // Extract 6-digit OTP
        let otp = null;
        const ifmail = page.frames().find(f => f.name() === 'ifmail');
        if (ifmail) {
            const text = await ifmail.evaluate(() => document.body ? document.body.innerText : '').catch(() => '');
            const match = text.match(/\b([0-9]{6})\b/) || text.match(/\b([0-9]{4})\b/);
            if (match) otp = match[1];
        }

        if (!otp && ifinbox) {
            const text = await ifinbox.evaluate(() => document.body ? document.body.innerText : '').catch(() => '');
            const match = text.match(/\b([0-9]{6})\b/);
            if (match) otp = match[1];
        }

        if (otp) {
            return {
                success: true,
                otp_found: true,
                otp: otp,
                subject: 'Taqamul Verification Code',
                preview: `Taqamul OTP: ${otp}`
            };
        }

        return {
            success: true,
            otp_found: false,
            message: 'No new OTP found in YOPmail inbox yet.'
        };

    } catch (err) {
        return {
            success: false,
            otp_found: false,
            error: err.message
        };
    } finally {
        await browser.close();
    }
}

if (require.main === module) {
    const userArg = process.argv[2] || 'test_user_demo';
    fetchYopmailOtp(userArg).then(res => {
        console.log("FINAL_RESULT:" + JSON.stringify(res));
    });
}

module.exports = { fetchYopmailOtp };
