const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const path = require('path');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.goto("https://svp-international.pacc.sa/auth/register?role=labor", {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        await delay(2000);

        // 1. Inspect file upload elements
        const fileInputs = await page.$$('input[type="file"]');
        console.log("File inputs found:", fileInputs.length);

        if (fileInputs.length > 0) {
            await fileInputs[0].uploadFile(path.resolve("E:\\taqamul\\web\\bot\\valid_passport.jpg"));
            console.log("Uploaded valid_passport.jpg");
            await delay(1500);
        }

        // 2. Inspect all select elements and placeholders
        const selects = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('.el-select, input')).map(el => ({
                tag: el.tagName,
                className: el.className,
                placeholder: el.getAttribute('placeholder'),
                value: el.value || ''
            }));
        });
        console.log("Selects & Inputs:", JSON.stringify(selects, null, 2));

        // 3. Test selecting Country
        console.log("Clicking country...");
        const countryEl = await page.$('.el-select:nth-of-type(1) input, input[placeholder*="country" i]');
        if (countryEl) {
            await countryEl.click();
            await delay(500);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = items.find(el => el.innerText && el.innerText.toLowerCase().includes('bangladesh'));
                if (match) match.click();
            });
            await delay(500);
        }

        // 4. Test selecting Nationality
        console.log("Clicking nationality...");
        const natEl = await page.$('.el-select:nth-of-type(2) input, input[placeholder*="nationality" i]');
        if (natEl) {
            await natEl.click();
            await delay(500);
            const foundItems = await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const visible = items.filter(el => el.offsetParent !== null);
                const list = visible.map(el => el.innerText.trim());
                const match = visible.find(el => el.innerText && el.innerText.toLowerCase().includes('bangla'));
                if (match) match.click();
                return { visibleCount: visible.length, matchFound: !!match, sample: list.slice(0, 10) };
            });
            console.log("Nationality options result:", foundItems);
        }

        await delay(1000);
        await page.screenshot({ path: "E:\\taqamul\\web\\bot\\inspect_result.png" });
        console.log("Saved inspect_result.png");

    } catch (e) {
        console.error("Error:", e.message);
    } finally {
        await browser.close();
    }
})();
