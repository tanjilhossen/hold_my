const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const https = require('https');

const CAPSOLVER_KEY = "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const YOPMAIL_SITE_KEY = "6LcG5v8SAAAAAOdAn2iqMEQTdVyX8t0w9T3cpdN2";

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
        }, res => {
            let body = '';
            res.on('data', d => body += d);
            res.on('end', () => resolve(JSON.parse(body)));
        });
        req.on('error', reject);
        req.write(data);
    });
}

async function solveRecaptchaFast(label = "Captcha", websiteUrl = "https://yopmail.com", websiteKey = YOPMAIL_SITE_KEY) {
    console.log(`[CapSolver AI] ⚡ Solving reCAPTCHA for ${label}...`);
    const createRes = await postJson("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: { type: "ReCaptchaV2TaskProxyLess", websiteURL: websiteUrl, websiteKey: websiteKey }
    });
    for (let i = 0; i < 60; i++) {
        await new Promise(r => setTimeout(r, 250));
        const result = await postJson("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: createRes.taskId
        });
        if (result.status === "ready") return result.solution.gRecaptchaResponse;
    }
}

async function extractYopmailOtpFast(yopPage) {
    let yopCaptchaPromise = solveRecaptchaFast("YOPmail Pre-solve");

    for (let attempt = 1; attempt <= 40; attempt++) {
        // 1. Check if captcha is shown
        const isCaptcha = await yopPage.evaluate(() => {
            const rParent = document.getElementById('r_parent');
            return rParent && (rParent.style.display !== 'none' && rParent.offsetWidth > 0);
        }).catch(() => false);

        if (isCaptcha) {
            console.log("🚨 Captcha detected! Injecting token...");
            const token = await yopCaptchaPromise;
            if (token) {
                await yopPage.evaluate((t) => {
                    if (typeof refr === 'function') try { refr('1', null, null, null, t); } catch (e) {}
                    if (typeof hideRc === 'function') try { hideRc(); } catch (e) {}
                    const rParent = document.getElementById('r_parent');
                    if (rParent) rParent.style.display = 'none';
                    if (typeof showinbox === 'function') try { showinbox(true); } catch (e) {}
                }, token);
                yopCaptchaPromise = null;
                await new Promise(r => setTimeout(r, 1500));
            }
        }

        // 2. Check if messages exist in ifinbox and click first
        const ifinbox = yopPage.frames().find(f => f.name() === 'ifinbox');
        if (ifinbox) {
            await ifinbox.evaluate(() => {
                const topMsg = document.querySelector('button.lm, .lm, .m, div.m');
                if (topMsg) topMsg.click();
            }).catch(() => {});
        }

        // 3. Extract OTP from ifmail or any frame
        for (const frame of yopPage.frames()) {
            if (frame.name() === 'ifmail' || frame.name() === 'ifinbox') {
                const text = await frame.evaluate(() => document.body ? document.body.innerText : '').catch(() => '');
                const m = text.match(/\b([0-9]{6})\b/);
                if (m) {
                    console.log(`🎯 OTP captured from Frame [${frame.name()}]: ${m[1]}`);
                    return m[1];
                }
            }
        }

        // 4. Trigger gentle refresh
        await yopPage.evaluate(() => {
            const rBtn = document.getElementById('refresh');
            if (rBtn) rBtn.click();
        }).catch(() => {});

        await new Promise(r => setTimeout(r, 1000));
    }
    return null;
}

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        headless: false,
        args: ['--no-sandbox']
    });
    const page = await browser.newPage();
    console.log('Navigating to YOPmail...');
    await page.goto('https://yopmail.com/?mohammadkarim_aa31179084', { waitUntil: 'domcontentloaded' });
    const otp = await extractYopmailOtpFast(page);
    console.log('Final Result OTP:', otp);
    await browser.close();
})();
