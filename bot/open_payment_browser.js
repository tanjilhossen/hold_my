const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const CHROME_PATH = "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe";

async function openPaymentBrowser(token, targetUrl) {
    console.log("[Payment Browser] Launching logged-in Chrome popup window...");

    const cleanToken = token.startsWith('Bearer ') ? token.replace('Bearer ', '').trim() : token.trim();

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: false,
        defaultViewport: null,
        args: [
            '--start-maximized',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-infobars',
            '--disable-web-security',
            '--window-size=1366,860'
        ]
    });

    const pages = await browser.pages();
    const page = pages[0] || await browser.newPage();

    // 1. Inject Cookies natively before navigation so initial HTTP requests carry authentication
    await page.setCookie(
        {
            name: 'auth_token_default',
            value: cleanToken,
            domain: 'svp-international.pacc.sa',
            path: '/',
            httpOnly: false,
            secure: true,
            sameSite: 'Lax'
        },
        {
            name: 'auth_stay_signed_in',
            value: 'true',
            domain: 'svp-international.pacc.sa',
            path: '/',
            httpOnly: false,
            secure: true,
            sameSite: 'Lax'
        }
    );

    // 2. Pre-inject into localStorage on new documents
    await page.evaluateOnNewDocument((t) => {
        try {
            localStorage.setItem('auth_token_default', t);
            localStorage.setItem('auth_stay_signed_in', 'true');
            localStorage.setItem('auth._token.local', 'Bearer ' + t);
            localStorage.setItem('auth.loggedIn', 'true');
        } catch (e) {}
    }, cleanToken);

    // 3. Navigate directly to booking & payment page
    const destUrl = targetUrl || "https://svp-international.pacc.sa/labor/booking/steps";
    console.log("[Payment Browser] Navigating directly to: " + destUrl);

    await page.goto(destUrl, {
        waitUntil: 'domcontentloaded',
        timeout: 45000
    }).catch(e => console.log("Nav warning:", e.message));

    console.log("[Payment Browser] ✅ Payment page is now open on user's screen with active session!");
}

// Read args
const args = process.argv.slice(2);
const token = args[0] || '';
const targetUrl = args[1] || 'https://svp-international.pacc.sa/labor/booking/steps';

if (token) {
    openPaymentBrowser(token, targetUrl);
} else {
    console.error("Missing token parameter");
}
