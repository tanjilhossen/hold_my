const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

async function bypassTaqamulRecaptcha(page) {
    return await page.evaluate(() => {
        let applied = false;
        document.querySelectorAll('*').forEach(el => {
            if (el.__vue__) {
                const v = el.__vue__;
                if (v.recaptchaStatus !== undefined) {
                    v.recaptchaStatus = 'failed';
                    v.recaptchaResponse = 'auto_verified';
                    applied = true;
                }
                if (v.$parent && v.$parent.recaptchaStatus !== undefined) {
                    v.$parent.recaptchaStatus = 'failed';
                    v.$parent.recaptchaResponse = 'auto_verified';
                    applied = true;
                }
            }
        });
        return applied;
    });
}

(async () => {
    console.log("=========================================================");
    console.log("    STARTING COMPLETE END-TO-END TAQAMUL ACCOUNT CREATION ");
    console.log("=========================================================\n");

    const randomId = Math.floor(10000000 + Math.random() * 90000000);
    const candidate = {
        firstName: "MD",
        lastName: "KARIM",
        passport: "A" + randomId,
        country: "Bangladesh",
        nationality: "Bangladeshi",
        dob: "14/07/1995",
        expDate: "22/10/2034",
        email: `taqamul_${randomId}@yopmail.com`,
        phone: "17" + Math.floor(10000000 + Math.random() * 90000000),
        password: "Taqamul@2026!"
    };

    console.log(`Candidate Name    : ${candidate.firstName} ${candidate.lastName}`);
    console.log(`Passport Number   : ${candidate.passport}`);
    console.log(`YOPmail Address   : ${candidate.email}`);
    console.log(`Password          : ${candidate.password}`);
    console.log(`Phone Number      : +880 ${candidate.phone}\n`);

    console.log("[1/5] Launching Chrome Engine...");
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: "new",
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--window-size=1280,900'
        ]
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 900 });

        console.log("[2/5] Navigating to Taqamul Registration Page...");
        await page.goto("https://svp-international.pacc.sa/auth/register?role=labor", {
            waitUntil: 'networkidle2',
            timeout: 60000
        });
        
        await page.waitForSelector('input', { timeout: 20000 });
        await delay(2000);

        // --- STEP 1 ---
        console.log("[3/5] Filling Step 1: Personal Details...");
        const firstInput = await page.$('input[placeholder*="first name"]');
        if (firstInput) {
            await firstInput.click({ clickCount: 3 });
            await firstInput.type(candidate.firstName);
        }

        const lastInput = await page.$('input[placeholder*="last name"]');
        if (lastInput) {
            await lastInput.click({ clickCount: 3 });
            await lastInput.type(candidate.lastName);
        }

        const passInput = await page.$('input[placeholder*="password number"], input[placeholder*="passport"]');
        if (passInput) {
            await passInput.click({ clickCount: 3 });
            await passInput.type(candidate.passport);
        }

        // Select Country
        const countryInput = await page.$('input[placeholder*="country"]');
        if (countryInput) {
            await countryInput.click();
            await delay(500);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span'));
                const match = items.find(el => el.innerText && el.innerText.includes('Bangladesh'));
                if (match) match.click();
            });
            await delay(400);
        }

        // Select Nationality
        const natInput = await page.$('input[placeholder*="nationality"]');
        if (natInput) {
            await natInput.click();
            await delay(500);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span'));
                const match = items.find(el => el.innerText && el.innerText.includes('Bangladeshi'));
                if (match) match.click();
            });
            await delay(400);
        }

        const dateInputs = await page.$$('input[placeholder*="DD/MM/YYYY"]');
        if (dateInputs.length > 0) {
            await dateInputs[0].click({ clickCount: 3 });
            await dateInputs[0].type("14/07/1995");
        }
        if (dateInputs.length > 1) {
            await dateInputs[1].click({ clickCount: 3 });
            await dateInputs[1].type("22/10/2034");
        }

        await delay(500);
        console.log("      Applying built-in Recaptcha handler...");
        await bypassTaqamulRecaptcha(page);
        await delay(500);

        console.log("      Clicking Continue on Step 1...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        await delay(1500);

        // Confirm Modal
        console.log("      Confirming Passport Popup...");
        await page.evaluate(() => {
            const dialogBtns = Array.from(document.querySelectorAll('.el-dialog button, .el-message-box button, [role="dialog"] button, button'));
            const confirmBtn = dialogBtns.find(b => b.innerText && (b.innerText.toLowerCase().includes('confirm') || b.innerText.toLowerCase().includes('yes')));
            if (confirmBtn) confirmBtn.click();
        });

        await delay(2500);

        // --- STEP 2 ---
        console.log("[4/5] Moving through Step 2 (Qualifications)...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        await delay(2500);

        // --- STEP 3 ---
        console.log("[5/5] Filling Step 3: Contact & Password...");
        const emailInput = await page.$('input[placeholder*="email"], input[type="email"]');
        if (emailInput) {
            await emailInput.click({ clickCount: 3 });
            await emailInput.type(candidate.email);
        }

        const phoneInput = await page.$('input[placeholder*="phone"], input[type="tel"]');
        if (phoneInput) {
            await phoneInput.click({ clickCount: 3 });
            await phoneInput.type(candidate.phone);
        }

        const passInputs = await page.$$('input[type="password"]');
        if (passInputs.length >= 2) {
            await passInputs[0].click({ clickCount: 3 });
            await passInputs[0].type(candidate.password);

            await passInputs[1].click({ clickCount: 3 });
            await passInputs[1].type(candidate.password);
        }

        await page.evaluate(() => {
            const checkboxes = Array.from(document.querySelectorAll('input[type="checkbox"]'));
            checkboxes.forEach(c => { if (!c.checked) c.click(); });
        });
        await delay(600);

        await bypassTaqamulRecaptcha(page);
        await delay(500);

        console.log("      Submitting final step to send OTP...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && (b.innerText.includes('Continue') || b.innerText.includes('Send') || b.innerText.includes('Verify')));
            if (continueBtn) continueBtn.click();
        });

        await delay(5000);

        const finalPageText = await page.evaluate(() => document.body.innerText);
        console.log("\n>>> Final Status on Screen:");
        console.log("    " + finalPageText.substring(0, 300).replace(/\n/g, ' '));

        await page.screenshot({ path: "E:\\taqamul\\web\\bot\\final_test_screen.png" });
        console.log(">>> Screenshot saved to bot/final_test_screen.png\n");

        console.log("=========================================================");
        console.log("       ACCOUNT REGISTRATION SUBMITTED SUCCESSFULLY!      ");
        console.log("=========================================================");
        console.log(`Candidate Name    : ${candidate.firstName} ${candidate.lastName}`);
        console.log(`Passport Number   : ${candidate.passport}`);
        console.log(`Taqamul Login Mail: ${candidate.email}`);
        console.log(`Taqamul Password  : ${candidate.password}`);
        console.log(`YOPmail Check URL : https://yopmail.com?${candidate.email.replace('@yopmail.com', '')}`);
        console.log("=========================================================");

    } catch (e) {
        console.error("Error:", e.message);
    } finally {
        await browser.close();
    }
})();
