const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const path = require('path');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    console.log("Testing file chooser and nationality selection...");
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

        // 1. Test FileChooser Upload
        console.log("1. Triggering File Chooser for Passport Upload...");
        try {
            const [fileChooser] = await Promise.all([
                page.waitForFileChooser({ timeout: 5000 }),
                page.evaluate(() => {
                    const btns = Array.from(document.querySelectorAll('button, .el-upload, div'));
                    const upBtn = btns.find(b => b.innerText && b.innerText.trim().includes('Upload Passport'));
                    if (upBtn) upBtn.click();
                })
            ]);
            await fileChooser.accept([path.resolve("E:\\taqamul\\web\\bot\\valid_passport.jpg")]);
            console.log("✅ Passport file selected via fileChooser!");
            await delay(2000);
        } catch (e) {
            console.log("FileChooser direct fallback:", e.message);
            const fileInput = await page.$('input[type="file"]');
            if (fileInput) {
                await fileInput.uploadFile(path.resolve("E:\\taqamul\\web\\bot\\valid_passport.jpg"));
                console.log("✅ File uploaded via direct file input");
            }
        }

        // 2. Select Country: Bangladesh
        console.log("2. Selecting Country...");
        const countryInput = await page.$('input[placeholder*="country" i]');
        if (countryInput) {
            await countryInput.click();
            await delay(500);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const match = items.find(el => el.innerText && el.innerText.trim() === 'Bangladesh');
                if (match) match.click();
            });
            await delay(400);
        }

        // 3. Select Nationality: Bangladesh
        console.log("3. Selecting Nationality...");
        const natInput = await page.$('input[placeholder*="nationality" i]');
        if (natInput) {
            await natInput.click();
            await delay(600);
            const res = await page.evaluate(() => {
                // Find all dropdown items across the DOM
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item'));
                const visible = items.filter(el => el.offsetParent !== null || el.clientHeight > 0);
                const match = items.find(el => el.innerText && el.innerText.trim() === 'Bangladesh');
                if (match) {
                    match.click();
                    return { found: true, text: match.innerText };
                }
                return { found: false, count: items.length, visibleCount: visible.length };
            });
            console.log("Nationality select result:", res);
            await delay(400);
        }

        await delay(1000);
        await page.screenshot({ path: "E:\\taqamul\\web\\bot\\upload_and_nat_test.png" });
        console.log("Saved screenshot to upload_and_nat_test.png");

    } catch (e) {
        console.error("Error:", e.message);
    } finally {
        await browser.close();
    }
})();
