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
    console.log("Requesting CapSolver token for YOPmail...");
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
        headless: false,
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
        
        // Directly call refr with token as 5th argument!
        if (typeof refr === 'function') {
            try { refr('1', null, null, null, t); } catch (e) {}
        }
        if (typeof hideRc === 'function') {
            try { hideRc(); } catch (e) {}
        }
        const rParent = document.getElementById('r_parent');
        if (rParent) rParent.style.display = 'none';
        if (typeof showinbox === 'function') {
            try { showinbox(true); } catch (e) {}
        }
    }, token);

    await new Promise(r => setTimeout(r, 4000));

    const finalState = await page.evaluate(() => {
        const rParent = document.getElementById('r_parent');
        const ifinbox = document.getElementById('ifinbox');
        const ifinboxDoc = ifinbox ? ifinbox.contentDocument : null;
        const msgs = ifinboxDoc ? ifinboxDoc.querySelectorAll('.m, button.lm, .lm') : [];
        return {
            rParentDisplay: rParent ? rParent.style.display : null,
            inboxUrl: ifinboxDoc ? ifinboxDoc.location.href : null,
            messagesCount: msgs.length
        };
    });
    console.log('Final State:', JSON.stringify(finalState));
    await browser.close();
})();
