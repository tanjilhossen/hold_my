const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    console.log("=================================================");
    console.log("   OPENING ON-SCREEN VISUAL CHROME BROWSER...    ");
    console.log("=================================================");

    const randomNum = Math.floor(10000000 + Math.random() * 90000000);
    const candidate = {
        firstName: "KAMAL",
        lastName: "UDDIN",
        passport: "A" + randomNum,
        country: "Bangladesh",
        nationality: "Bangladeshi",
        dob: "15/08/1996",
        expDate: "20/12/2034",
        email: `taqamul_k${randomNum}@yopmail.com`,
        phone: "17" + Math.floor(10000000 + Math.random() * 90000000),
        password: "Taqamul@2026!"
    };

    console.log("\n--- Candidate Details ---");
    console.log(`Name    : ${candidate.firstName} ${candidate.lastName}`);
    console.log(`Passport: ${candidate.passport}`);
    console.log(`Email   : ${candidate.email}`);
    console.log(`Password: ${candidate.password}`);
    console.log(`Phone   : +880 ${candidate.phone}`);
    console.log("-------------------------\n");

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: false, // OPEN VISIBLY ON USER SCREEN!
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

        console.log("[1/4] Navigating to Taqamul Registration Page...");
        await page.goto("https://svp-international.pacc.sa/auth/register?role=labor", {
            waitUntil: 'networkidle2',
            timeout: 60000
        });

        await delay(3000);

        // ==========================================
        // STEP 1: PASSPORT & PERSONAL INFORMATION
        // ==========================================
        console.log("[2/4] Filling Step 1: Personal & Passport Info...");

        // First Name
        const firstInput = await page.$('input[placeholder*="first name"]');
        if (firstInput) {
            await firstInput.click({ clickCount: 3 });
            await firstInput.type(candidate.firstName, { delay: 100 });
        }
        await delay(500);

        // Last Name
        const lastInput = await page.$('input[placeholder*="last name"]');
        if (lastInput) {
            await lastInput.click({ clickCount: 3 });
            await lastInput.type(candidate.lastName, { delay: 100 });
        }
        await delay(500);

        // Passport Number
        const passInput = await page.$('input[placeholder*="password number"], input[placeholder*="passport"]');
        if (passInput) {
            await passInput.click({ clickCount: 3 });
            await passInput.type(candidate.passport, { delay: 100 });
        }
        await delay(800);

        // Select Country
        console.log("      Selecting Country: Bangladesh...");
        const countryInput = await page.$('input[placeholder*="country"]');
        if (countryInput) {
            await countryInput.click();
            await delay(800);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span'));
                const match = items.find(el => el.innerText && el.innerText.toLowerCase().includes('bangladesh'));
                if (match) match.click();
            });
            await delay(600);
        }

        // Select Nationality
        console.log("      Selecting Nationality: Bangladeshi...");
        const natInput = await page.$('input[placeholder*="nationality"]');
        if (natInput) {
            await natInput.click();
            await delay(800);
            await page.evaluate(() => {
                const items = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span'));
                const match = items.find(el => el.innerText && el.innerText.toLowerCase().includes('bangladeshi'));
                if (match) match.click();
            });
            await delay(600);
        }

        // Date of Birth & Passport Expiry
        const dateInputs = await page.$$('input[placeholder*="DD/MM/YYYY"]');
        if (dateInputs.length > 0) {
            console.log("      Entering Date of Birth: " + candidate.dob);
            await dateInputs[0].click({ clickCount: 3 });
            await dateInputs[0].type(candidate.dob, { delay: 80 });
            await delay(500);
        }

        if (dateInputs.length > 1) {
            console.log("      Entering Passport Expiry: " + candidate.expDate);
            await dateInputs[1].click({ clickCount: 3 });
            await dateInputs[1].type(candidate.expDate, { delay: 80 });
            await delay(500);
        }

        // Click Continue on Step 1
        console.log("      Clicking Continue on Step 1...");
        await delay(1000);
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        await delay(2000);

        // Passport Confirmation Modal
        console.log("      Checking for Passport Confirmation Popup...");
        await page.evaluate(() => {
            const dialogBtns = Array.from(document.querySelectorAll('.el-dialog button, .el-message-box button, [role="dialog"] button, button'));
            const confirmBtn = dialogBtns.find(b => b.innerText && (b.innerText.toLowerCase().includes('confirm') || b.innerText.toLowerCase().includes('yes')));
            if (confirmBtn) confirmBtn.click();
        });

        await delay(3000);

        // ==========================================
        // STEP 2: QUALIFICATION & EXPERIENCE
        // ==========================================
        console.log("[3/4] Filling Step 2: Qualification & Experience...");
        await delay(1000);

        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && b.innerText.includes('Continue'));
            if (continueBtn) continueBtn.click();
        });

        await delay(3000);

        // ==========================================
        // STEP 3: CONTACT & SECURITY
        // ==========================================
        console.log("[4/4] Filling Step 3: Email, Phone & Password...");

        // Email
        const emailInput = await page.$('input[placeholder*="email"], input[type="email"]');
        if (emailInput) {
            await emailInput.click({ clickCount: 3 });
            await emailInput.type(candidate.email, { delay: 80 });
            console.log(`      Entered Email: ${candidate.email}`);
        }
        await delay(500);

        // Phone Number
        const phoneInput = await page.$('input[placeholder*="phone"], input[type="tel"]');
        if (phoneInput) {
            await phoneInput.click({ clickCount: 3 });
            await phoneInput.type(candidate.phone, { delay: 80 });
            console.log(`      Entered Phone: ${candidate.phone}`);
        }
        await delay(500);

        // Passwords
        const passInputs = await page.$$('input[type="password"]');
        if (passInputs.length >= 2) {
            await passInputs[0].click({ clickCount: 3 });
            await passInputs[0].type(candidate.password, { delay: 80 });

            await passInputs[1].click({ clickCount: 3 });
            await passInputs[1].type(candidate.password, { delay: 80 });
        }
        await delay(800);

        // Terms and Conditions
        await page.evaluate(() => {
            const checkboxes = Array.from(document.querySelectorAll('input[type="checkbox"]'));
            checkboxes.forEach(c => {
                if (!c.checked) c.click();
            });
        });
        await delay(1000);

        // Click Submit / Send OTP
        console.log("      Clicking Submit to trigger OTP from Taqamul server...");
        await page.evaluate(() => {
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => b.innerText && (b.innerText.includes('Continue') || b.innerText.includes('Send') || b.innerText.includes('Verify')));
            if (continueBtn) continueBtn.click();
        });

        console.log("\n=================================================");
        console.log("   BROWSER IS NOW ON SCREEN FOR YOU TO WATCH!   ");
        console.log("   Look at your screen to see the live page.     ");
        console.log("=================================================\n");

        // Keep browser open for 3 minutes so user can observe and interact if desired
        await delay(180000);

    } catch (err) {
        console.error("Live automation error:", err.message);
    } finally {
        await browser.close();
    }
})();
