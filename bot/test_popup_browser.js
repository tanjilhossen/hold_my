const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const fs = require('fs');

(async () => {
    console.log("🚀 Testing On-Screen Visual Chrome Browser Launch...");
    let CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
    if (!fs.existsSync(CHROME_PATH)) {
        CHROME_PATH = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
    }

    try {
        const browser = await puppeteer.launch({
            headless: false,
            executablePath: fs.existsSync(CHROME_PATH) ? CHROME_PATH : undefined,
            args: ['--start-maximized', '--no-sandbox', '--disable-setuid-sandbox']
        });

        console.log("✅ Chrome Browser process created! Opening page...");
        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();
        await page.goto("https://svp-international.pacc.sa/auth/register?role=labor");
        console.log("✅ Taqamul portal page loaded inside Chrome browser!");
        
        await new Promise(resolve => setTimeout(resolve, 8000));
        await browser.close();
        console.log("✅ Test browser closed clean.");
    } catch (e) {
        console.error("❌ Test Browser Launch Failed:", e.message);
    }
})();
