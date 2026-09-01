const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const fs = require('fs');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const PAGE_URL = "https://svp-international.pacc.sa/auth/register?role=labor";
const USER_PASSPORT_FILE = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";

const delay = (ms) => new Promise(r => setTimeout(r, ms));

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
    await delay(1500);

    // 1. Upload PASSPORT.jpg
    const fileInputs = await page.$$('input[type="file"], .el-upload__input');
    for (const fi of fileInputs) {
        try { await fi.uploadFile(USER_PASSPORT_FILE); } catch (e) {}
    }
    await delay(500);

    const passportBase64 = fs.readFileSync(USER_PASSPORT_FILE).toString('base64');
    await page.evaluate((b64) => {
        const byteCharacters = atob(b64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i);
        const byteArray = new Uint8Array(byteNumbers);
        const f = new File([byteArray], "PASSPORT.jpg", { type: "image/jpeg" });
        const fileObj = { raw: f, name: "PASSPORT.jpg", size: f.size, uid: Date.now() };

        document.querySelectorAll('*').forEach(el => {
            if (el.__vue__ && el.__vue__.$options && el.__vue__.$options.name === 'PassportInformation') {
                const v = el.__vue__;
                v.passportFile = fileObj;
                v.isPassportRecognitionShown = false;
                v.passportValidationShown = false;
            }
        });
    }, passportBase64);
    await delay(1000);

    // 2. Select Country: Bangladesh
    console.log("Selecting Country: Bangladesh from dropdown...");
    const countryInput = await page.$('input[placeholder*="country" i]');
    if (countryInput) {
        await countryInput.click();
        await delay(400);
        await page.evaluate(() => {
            const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
            const match = items.find(el => el.innerText && el.innerText.trim().toLowerCase() === 'bangladesh');
            if (match) match.click();
        });
        await delay(500);
        await page.mouse.click(50, 50);
    }

    await delay(2000);

    const state = await page.evaluate(() => {
        let res = {};
        document.querySelectorAll('*').forEach(el => {
            if (el.__vue__ && el.__vue__.$options && el.__vue__.$options.name === 'PassportInformation') {
                const v = el.__vue__;
                res = {
                    selectedCountry: v.selectedCountry,
                    selectedNationality: v.selectedNationality,
                    nationalitiesLength: v.nationalities ? v.nationalities.length : null,
                    nationalityIsLoading: v.nationalityIsLoading
                };
            }
        });
        return res;
    });

    console.log("VUE STATE AFTER 2s:", JSON.stringify(state, null, 2));

    await delay(2000);
    await browser.close();
})();
