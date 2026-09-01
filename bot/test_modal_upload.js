const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const path = require('path');
const fs = require('fs');

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const USER_PASSPORT = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";

(async () => {
    console.log("Testing passport modal upload...");
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

        await page.waitForSelector('input, button', { visible: true, timeout: 30000 });
        await delay(1500);

        // 1. Click "Upload Passport" button to open modal
        console.log("1. Clicking 'Upload Passport' button...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const upBtn = btns.find(b => b.innerText && b.innerText.toLowerCase().includes('upload passport'));
            if (upBtn) upBtn.click();
        });

        await delay(1500);

        // 2. Find file input inside the open modal or DOM
        console.log("2. Uploading file to input inside modal...");
        const fileInputs = await page.$$('input[type="file"]');
        console.log("File inputs found after opening modal:", fileInputs.length);

        if (fileInputs.length > 0) {
            await fileInputs[fileInputs.length - 1].uploadFile(USER_PASSPORT);
            await delay(1000);
        }

        // 3. Check for any "Upload" or "Confirm" button inside modal
        await page.evaluate(() => {
            const modalBtns = Array.from(document.querySelectorAll('.el-dialog button, button'));
            const confirmUpload = modalBtns.find(b => b.innerText && (b.innerText.toLowerCase().includes('upload') || b.innerText.toLowerCase().includes('confirm') || b.innerText.toLowerCase().includes('done')));
            if (confirmUpload) confirmUpload.click();
        });

        await delay(2000);

        await page.screenshot({ path: "E:\\taqamul\\web\\bot\\modal_upload_test.png" });
        console.log("Saved modal_upload_test.png");

    } catch (e) {
        console.error("Error:", e.message);
    } finally {
        await browser.close();
    }
})();
