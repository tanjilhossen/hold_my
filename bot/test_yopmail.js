const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        headless: false,
        defaultViewport: null,
        args: ['--start-maximized', '--no-sandbox']
    });

    const page = await browser.newPage();
    console.log("Navigating to YOPmail...");
    await page.goto('https://yopmail.com/?taqamul_58123020', { waitUntil: 'domcontentloaded' });
    await new Promise(r => setTimeout(r, 4000));

    console.log("Current URL:", page.url());
    console.log("Frames count:", page.frames().length);

    for (const f of page.frames()) {
        try {
            const data = await f.evaluate(() => {
                return {
                    name: window.name,
                    url: window.location.href,
                    html: document.body ? document.body.innerHTML.slice(0, 300) : '',
                    hasCaptcha: !!document.querySelector('iframe[src*="recaptcha"], .g-recaptcha, #g-recaptcha'),
                    buttons: Array.from(document.querySelectorAll('button, a, div[onclick]')).map(b => ({ id: b.id, class: b.className, text: b.innerText, onclick: b.getAttribute('onclick') })).filter(x => x.id || x.onclick)
                };
            });
            console.log("\n--- FRAME:", f.name(), "---");
            console.log(JSON.stringify(data, null, 2));
        } catch (e) {
            console.log("Frame error:", f.name(), e.message);
        }
    }

    await new Promise(r => setTimeout(r, 10000));
    await browser.close();
})();
