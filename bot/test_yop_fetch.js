const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const CAPSOLVER_KEY = "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const YOPMAIL_SITE_KEY = "6LcG5v8SAAAAAOdAn2iqMEQTdVyX8t0w9T3cpdN2";
const EMAIL_USER = "taqamul_57552528"; // From user's screenshot!

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

async function solveRecaptchaFast() {
    console.log("[CapSolver] ⚡ Solving YOPmail reCAPTCHA...");
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: "https://yopmail.com",
            websiteKey: YOPMAIL_SITE_KEY
        }
    });

    const taskId = createRes.taskId;
    for (let i = 0; i < 70; i++) {
        await delay(350);
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: taskId
        });

        if (result.status === "ready") {
            console.log("[CapSolver] 🎯 Solved!");
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
    console.log("Navigating to YOPmail for " + EMAIL_USER + "...");
    await page.goto(`https://yopmail.com/?${EMAIL_USER}`, { waitUntil: 'domcontentloaded' });
    await delay(2000);

    const token = await solveRecaptchaFast();

    console.log("Unlocking email and fetching content...");
    const emailHtml = await page.evaluate(async (t) => {
        const ifinboxDoc = document.getElementById('ifinbox')?.contentDocument;
        let mid = '';
        if (ifinboxDoc) {
            const m = ifinboxDoc.querySelector('.m, button.lm, .lm');
            if (m) mid = m.id;
        }
        const u = 'mail?b=' + login + '&id=m' + mid + '&r_c=' + encodeURIComponent(t);
        mailnav(u);

        try {
            const res = await fetch(u);
            return await res.text();
        } catch (e) {
            return null;
        }
    }, token);

    await delay(2500);

    // Extract OTP from emailHtml or ifmail frame
    let otp = null;
    if (emailHtml) {
        const match = emailHtml.match(/\b([0-9]{6})\b/) || emailHtml.match(/\b([0-9]{4})\b/);
        if (match) otp = match[1];
    }

    if (!otp) {
        const ifmail = page.frames().find(f => f.name() === 'ifmail');
        if (ifmail) {
            const text = await ifmail.evaluate(() => document.body ? document.body.innerText : '');
            const match = text.match(/\b([0-9]{6})\b/) || text.match(/\b([0-9]{4})\b/);
            if (match) otp = match[1];
        }
    }

    console.log(`\n========================================`);
    console.log(`   🎯 EXTRACTED OTP: ${otp} 🎯`);
    console.log(`========================================\n`);

    await delay(5000);
    await browser.close();
})();
