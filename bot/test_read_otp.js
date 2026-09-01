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
        req.end();
    });
}

async function solve() {
    const res = await postJson('https://api.capsolver.com/createTask', {
        clientKey: CAPSOLVER_KEY,
        task: { type: 'ReCaptchaV2TaskProxyLess', websiteURL: 'https://yopmail.com', websiteKey: YOPMAIL_SITE_KEY }
    });
    for (let i = 0; i < 40; i++) {
        await new Promise(r => setTimeout(r, 300));
        const check = await postJson('https://api.capsolver.com/getTaskResult', { clientKey: CAPSOLVER_KEY, taskId: res.taskId });
        if (check.status === 'ready') return check.solution.gRecaptchaResponse;
    }
}

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        headless: true,
        args: ['--no-sandbox']
    });
    const page = await browser.newPage();
    console.log('Navigating to YOPmail...');
    await page.goto('https://yopmail.com/?mohammadkarim_aa31179084', { waitUntil: 'networkidle2' });
    
    console.log('Solving token with CapSolver...');
    const token = await solve();
    console.log('Token received! Executing refr with token...');
    
    await page.evaluate((t) => {
        const ta = document.getElementById('g-recaptcha-response') || document.querySelector('textarea[name="g-recaptcha-response"]');
        if (ta) ta.value = t;
        if (typeof refr === 'function') try { refr('1', null, null, null, t); } catch (e) {}
        if (typeof hideRc === 'function') try { hideRc(); } catch (e) {}
        const rParent = document.getElementById('r_parent');
        if (rParent) rParent.style.display = 'none';
        if (typeof showinbox === 'function') try { showinbox(true); } catch (e) {}
    }, token);

    await new Promise(r => setTimeout(r, 3000));

    // Inspect ifinbox frame
    const ifinbox = page.frames().find(f => f.name() === 'ifinbox');
    if (ifinbox) {
        const info = await ifinbox.evaluate(() => {
            const msgs = Array.from(document.querySelectorAll('.m, button.lm, .lm, div.m'));
            return {
                count: msgs.length,
                firstId: msgs.length > 0 ? msgs[0].id : null,
                firstHtml: msgs.length > 0 ? msgs[0].outerHTML : null
            };
        });
        console.log('ifinbox info:', JSON.stringify(info));

        // Click first message
        await ifinbox.evaluate(() => {
            const topMsg = document.querySelector('button.lm, .lm, .m, div.m, .mctn');
            if (topMsg) topMsg.click();
        });
    }

    await new Promise(r => setTimeout(r, 3000));

    console.log('All Frames after click:', page.frames().map(f => f.name()));

    for (const f of page.frames()) {
        try {
            const text = await f.evaluate(() => document.body ? document.body.innerText : '');
            console.log(`Frame [${f.name()}] text (length ${text.length}): ${text.substring(0, 150)}...`);
            const m = text.match(/\b([0-9]{6})\b/);
            if (m) console.log(`🎯 FOUND OTP in Frame [${f.name()}]: ${m[1]}`);
        } catch (e) {
            console.log(`Frame [${f.name()}] error:`, e.message);
        }
    }

    await browser.close();
})();
