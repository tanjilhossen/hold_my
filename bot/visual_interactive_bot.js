const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    console.log("==========================================================");
    console.log("    TAQAMUL LIVE VISUAL AUTOMATION (ON-SCREEN MODE)       ");
    console.log("==========================================================\n");

    const randomId = Math.floor(10000000 + Math.random() * 90000000);
    const candidate = {
        firstName: "MD",
        lastName: "HASAN",
        passport: "A" + randomId,
        nationalId: "1996" + Math.floor(100000000 + Math.random() * 900000000),
        country: "Bangladesh",
        nationality: "Bangladeshi",
        dob: "12/04/1996",
        expDate: "18/10/2034",
        email: `taqamul_${randomId.toString().toLowerCase()}@yopmail.com`,
        phone: "17" + Math.floor(10000000 + Math.random() * 90000000),
        password: "Taqamul@2026!"
    };

    console.log(">>> Candidate Details for this live run:");
    console.log(`    Name    : ${candidate.firstName} ${candidate.lastName}`);
    console.log(`    Passport: ${candidate.passport}`);
    console.log(`    Email   : ${candidate.email}`);
    console.log(`    Password: ${candidate.password}`);
    console.log(`    Phone   : +880 ${candidate.phone}\n`);

    console.log(">>> Launching Google Chrome on your screen...");
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: false,
        defaultViewport: null,
        args: [
            '--start-maximized',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled'
        ]
    });

    try {
        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();

        console.log(">>> [Step 1/4] Opening Taqamul Registration Page...");
        await page.goto("https://svp-international.pacc.sa/auth/register?role=labor", {
            waitUntil: 'networkidle2',
            timeout: 60000
        });

        await delay(3000);

        // ==========================================
        // STEP 1: PASSPORT INFORMATION
        // ==========================================
        console.log(">>> [Step 2/4] Typing First Name: " + candidate.firstName);
        const firstInput = await page.$('input[placeholder*="first name"]');
        if (firstInput) {
            await firstInput.click({ clickCount: 3 });
            await firstInput.type(candidate.firstName, { delay: 120 });
        }
        await delay(600);

        console.log(">>> Typing Last Name: " + candidate.lastName);
        const lastInput = await page.$('input[placeholder*="last name"]');
        if (lastInput) {
            await lastInput.click({ clickCount: 3 });
            await lastInput.type(candidate.lastName, { delay: 120 });
        }
        await delay(600);

        console.log(">>> Typing Passport Number: " + candidate.passport);
        const passInput = await page.$('input[placeholder*="password number"], input[placeholder*="passport"]');
        if (passInput) {
            await passInput.click({ clickCount: 3 });
            await passInput.type(candidate.passport, { delay: 120 });
        }
        await delay(800);

        console.log(">>> Selecting Country: Bangladesh");
        const countryInput = await page.$('input[placeholder*="country"]');
        if (countryInput) {
            await countryInput.click();
            await delay(600);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span'));
                const match = items.find(el => el.innerText && el.innerText.toLowerCase().includes('bangladesh'));
                if (match) match.click();
            });
            await delay(600);
        }

        console.log(">>> Selecting Nationality: Bangladeshi");
        const natInput = await page.$('input[placeholder*="nationality"]');
        if (natInput) {
            await natInput.click();
            await delay(600);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span'));
                const match = items.find(el => el.innerText && el.innerText.toLowerCase().includes('bangladeshi'));
                if (match) match.click();
            });
            await delay(600);
        }

        const dateInputs = await page.$$('input[placeholder*="DD/MM/YYYY"]');
        if (dateInputs.length > 0) {
            console.log(">>> Typing Date of Birth: " + candidate.dob);
            await dateInputs[0].click({ clickCount: 3 });
            await dateInputs[0].type(candidate.dob, { delay: 100 });
            await delay(600);
        }

        if (dateInputs.length > 1) {
            console.log(">>> Typing Passport Expiry: " + candidate.expDate);
            await dateInputs[1].click({ clickCount: 3 });
            await dateInputs[1].type(candidate.expDate, { delay: 100 });
            await delay(600);
        }

        console.log(">>> Clicking 'Continue' button on Step 1...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        await delay(2000);

        console.log(">>> Confirming Passport Validation Popup...");
        await page.evaluate(() => {
            const dialogBtns = Array.from(document.querySelectorAll('.el-dialog button, .el-message-box button, [role="dialog"] button, button'));
            const confirmBtn = dialogBtns.find(b => b.innerText && (b.innerText.toLowerCase().includes('confirm') || b.innerText.toLowerCase().includes('yes')));
            if (confirmBtn) confirmBtn.click();
        });

        await delay(3000);

        // ==========================================
        // STEP 2: QUALIFICATIONS
        // ==========================================
        console.log(">>> [Step 3/4] Qualifications & Experience...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        await delay(3000);

        // ==========================================
        // STEP 3: CONTACT & SECURITY
        // ==========================================
        console.log(">>> [Step 4/4] Entering Email: " + candidate.email);
        const emailInput = await page.$('input[placeholder*="email"], input[type="email"]');
        if (emailInput) {
            await emailInput.click({ clickCount: 3 });
            await emailInput.type(candidate.email, { delay: 100 });
        }
        await delay(600);

        console.log(">>> Entering Phone Number: +880 " + candidate.phone);
        const phoneInput = await page.$('input[placeholder*="phone"], input[type="tel"]');
        if (phoneInput) {
            await phoneInput.click({ clickCount: 3 });
            await phoneInput.type(candidate.phone, { delay: 100 });
        }
        await delay(600);

        console.log(">>> Entering Password: " + candidate.password);
        const passInputs = await page.$$('input[type="password"]');
        if (passInputs.length >= 2) {
            await passInputs[0].click({ clickCount: 3 });
            await passInputs[0].type(candidate.password, { delay: 100 });

            await passInputs[1].click({ clickCount: 3 });
            await passInputs[1].type(candidate.password, { delay: 100 });
        }
        await delay(800);

        console.log(">>> Accepting Terms and Conditions...");
        await page.evaluate(() => {
            const checkboxes = Array.from(document.querySelectorAll('input[type="checkbox"]'));
            checkboxes.forEach(c => {
                if (!c.checked) c.click();
            });
        });
        await delay(1000);

        console.log(">>> Clicking Submit to Request OTP from Taqamul...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && (b.innerText.includes('Continue') || b.innerText.includes('Send') || b.innerText.includes('Verify')));
            if (continueBtn) continueBtn.click();
        });

        console.log("\n==========================================================");
        console.log("   ALL STEPS SUBMITTED! THE BROWSER IS ACTIVE ON SCREEN.  ");
        console.log("   Please look at the browser window to see what happens. ");
        console.log("==========================================================\n");

        // Keep browser open for 10 minutes so user can see and work in it
        await delay(600000);

    } catch (e) {
        console.error("Live automation error:", e.message);
    }
})();
