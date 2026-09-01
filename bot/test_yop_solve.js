const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');

const CAPSOLVER_KEY = "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const YOPMAIL_SITE_KEY = "6LcG5v8SAAAAAOdAn2iqMEQTdVyX8t0w9T3cpdN2";
const YOPMAIL_URL = "https://yopmail.com";

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

async function solveYopmailCaptcha() {
    console.log("[CapSolver] ⚡ Solving YOPmail reCAPTCHA...");
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: YOPMAIL_URL,
            websiteKey: YOPMAIL_SITE_KEY
        }
    });

    if (createRes.errorId !== 0 || !createRes.taskId) {
        throw new Error("CapSolver error: " + JSON.stringify(createRes));
    }

    const taskId = createRes.taskId;
    for (let i = 0; i < 30; i++) {
        await delay(1000);
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: taskId
        });

        if (result.status === "ready") {
            console.log("[CapSolver] 🎯 YOPmail Captcha Solved!");
            return result.solution.gRecaptchaResponse;
        }
    }
    throw new Error("CapSolver timeout");
}

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        headless: false,
        defaultViewport: null,
        args: ['--start-maximized', '--no-sandbox']
    });

    const page = await browser.newPage();
    await page.goto('https://yopmail.com/?taqamul_58123020', { waitUntil: 'domcontentloaded' });
    await delay(3000);

    const token = await solveYopmailCaptcha();

    await page.evaluate((t) => {
        const ifinboxDoc = document.getElementById('ifinbox')?.contentDocument;
        let mid = '';
        if (ifinboxDoc) {
            const m = ifinboxDoc.querySelector('.m');
            if (m) mid = m.id;
        }
        const u = 'mail?b=' + login + '&id=m' + mid + '&r_c=' + encodeURIComponent(t);
        mailnav(u);
    }, token);

    await delay(4000);

    const ifmail = page.frames().find(f => f.name() === 'ifmail');
    if (ifmail) {
        const html = await ifmail.evaluate(() => document.body.innerHTML);
        console.log("\n==========================================");
        console.log("FULL EMAIL HTML:\n", html);
        console.log("==========================================\n");

        const otpMatch = html.match(/\b([0-9]{6})\b/) || html.match(/\b([0-9]{4})\b/);
        if (otpMatch) {
            console.log("🔥 EXTRACTED OTP:", otpMatch[1]);
        }
    }

    await delay(5000);
    await browser.close();
})();
