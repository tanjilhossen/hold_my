const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const path = require('path');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const USER_PASSPORT = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";

(async () => {
    console.log("Testing upload with user's real PASSPORT.jpg...");
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

        await page.waitForSelector('input', { visible: true, timeout: 30000 });
        await delay(2000);

        // Find all file inputs including inside shadow DOM or hidden
        const uploadDetails = await page.evaluate(() => {
            const inputs = Array.from(document.querySelectorAll('input[type="file"]'));
            return inputs.map(i => ({
                id: i.id,
                name: i.name,
                className: i.className,
                accept: i.accept,
                outerHTML: i.outerHTML
            }));
        });
        console.log("Upload inputs found:", uploadDetails);

        // Upload using page.$$
        const inputs = await page.$$('input[type="file"]');
        for (const input of inputs) {
            await input.uploadFile(USER_PASSPORT);
            console.log("Uploaded to input!");
        }

        // Trigger change event and check Vue component state
        const vueState = await page.evaluate(() => {
            const inp = document.querySelector('input[type="file"]');
            if (inp) {
                inp.dispatchEvent(new Event('change', { bubbles: true }));
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            }

            let foundFile = false;
            document.querySelectorAll('*').forEach(el => {
                if (el.__vue__) {
                    const v = el.__vue__;
                    if (v.passportFile) foundFile = true;
                    if (v.fileList && v.fileList.length > 0) foundFile = true;
                }
            });
            return { foundFile, bodyPreview: document.body.innerText.substring(0, 300) };
        });

        console.log("Vue state after upload:", vueState);
        await delay(2000);
        await page.screenshot({ path: "E:\\taqamul\\web\\bot\\real_passport_upload_test.png" });
        console.log("Saved real_passport_upload_test.png");

    } catch (e) {
        console.error("Error:", e.message);
    } finally {
        await browser.close();
    }
})();
