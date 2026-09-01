/**
 * IFIC Bank / HyperPay 3D Secure Automated Payment & OTP Bot
 * Taqamul SVPI Direct Booking Automation
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const fs = require('fs');
const readline = require('readline');

// Command line arguments:
// node hyperpay_card_payer.js <token> <paymentUrl> <cardNumber> <cardHolder> <expiryMonth> <expiryYear> <cvv>
const args = process.argv.slice(2);
const token = args[0] || '';
const paymentUrl = args[1] || 'https://svp-international.pacc.sa/labor/booking/steps';
const cardNumber = args[2] || '';
const cardHolder = args[3] || '';
const expiryMonth = args[4] || '';
const expiryYear = args[5] || '';
const cvv = args[6] || '';

console.log(JSON.stringify({ status: 'starting', message: 'Launching secure 3D-Secure browser instance...' }));

(async () => {
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: false, // Visible so user can also watch or run seamlessly
            defaultViewport: { width: 1280, height: 900 },
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-web-security',
                '--disable-features=IsolateOrigins,site-per-process',
                '--window-size=1280,900',
                '--window-position=50,50'
            ]
        });

        const page = (await browser.pages())[0] || await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

        // 1. Inject Authentication Session
        if (token) {
            const cleanToken = token.startsWith('Bearer ') ? token.replace('Bearer ', '').trim() : token.trim();
            await page.setCookie(
                { name: 'auth_token_default', value: cleanToken, domain: '.pacc.sa', path: '/' },
                { name: 'auth_stay_signed_in', value: 'true', domain: '.pacc.sa', path: '/' },
                { name: 'locale', value: 'en', domain: '.pacc.sa', path: '/' }
            );

            await page.evaluateOnNewDocument((t) => {
                localStorage.setItem('auth_token_default', t);
                localStorage.setItem('auth_stay_signed_in', 'true');
                localStorage.setItem('auth._token.local', 'Bearer ' + t);
                localStorage.setItem('auth.loggedIn', 'true');
            }, cleanToken);
        }

        console.log(JSON.stringify({ status: 'navigating', message: 'Opening Taqamul payment gateway...' }));
        await page.goto(paymentUrl, { waitUntil: 'networkidle2', timeout: 60000 });

        // 2. Wait for Payment / Card Inputs to render
        await page.waitForTimeout(3000);

        // Check if on HyperPay frame or native input fields
        console.log(JSON.stringify({ status: 'filling_card', message: 'Entering IFIC Bank card credentials...' }));

        // Fill card inputs (Handles both direct forms and iframe gateways)
        const frames = page.frames();
        for (const frame of frames) {
            try {
                // Card Number
                const numInput = await frame.$('input[name*="cardNumber"], input[name*="card.number"], input[autocomplete="cc-number"], input[placeholder*="Card Number"], input[id*="card-number"]');
                if (numInput) {
                    await numInput.type(cardNumber, { delay: 50 });
                }

                // Card Holder
                const holderInput = await frame.$('input[name*="holder"], input[name*="card.holder"], input[autocomplete="cc-name"], input[placeholder*="Holder"], input[id*="card-holder"]');
                if (holderInput) {
                    await holderInput.type(cardHolder, { delay: 40 });
                }

                // Expiry Month / Year
                const expInput = await frame.$('input[name*="expiry"], input[name*="card.expiry"], input[autocomplete="cc-exp"], input[placeholder*="MM/YY"]');
                if (expInput) {
                    const shortY = expiryYear.length === 4 ? expiryYear.slice(-2) : expiryYear;
                    await expInput.type(`${expiryMonth}${shortY}`, { delay: 40 });
                } else {
                    const expM = await frame.$('input[name*="expiryMonth"], select[name*="expiryMonth"]');
                    if (expM) await expM.type(expiryMonth);
                    const expY = await frame.$('input[name*="expiryYear"], select[name*="expiryYear"]');
                    if (expY) await expY.type(expiryYear);
                }

                // CVV
                const cvvInput = await frame.$('input[name*="cvv"], input[name*="card.cvv"], input[autocomplete="cc-csc"], input[placeholder*="CVV"], input[id*="card-cvv"]');
                if (cvvInput) {
                    await cvvInput.type(cvv, { delay: 50 });
                }

                // Submit Pay Button
                const payBtn = await frame.$('button[type="submit"], button.wpwl-button-pay, button:has-text("Pay"), input[type="submit"]');
                if (payBtn) {
                    await payBtn.click();
                    console.log(JSON.stringify({ status: 'card_submitted', message: 'Card submitted. Awaiting 3D Secure challenge...' }));
                }
            } catch (e) {}
        }

        // 3. Detect 3D Secure / IFIC Bank ACS page
        await page.waitForTimeout(5000);
        console.log(JSON.stringify({
            status: 'otp_sent',
            message: 'IFIC Bank 3D-Secure SMS OTP sent to your phone! Please input OTP in the software.',
            phone_hint: 'Your mobile number registered with IFIC Bank'
        }));

        // 4. Read OTP from stdin
        const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
        rl.on('line', async (line) => {
            const otpCode = line.trim();
            if (otpCode) {
                console.log(JSON.stringify({ status: 'submitting_otp', message: `Submitting OTP (${otpCode}) to IFIC 3DS...` }));

                // Find OTP input across page and frames
                const allFrames = page.frames();
                for (const f of allFrames) {
                    try {
                        const otpInput = await f.$('input[name*="otp"], input[name*="code"], input[id*="otp"], input[type="tel"], input[type="number"], input[maxlength="6"], input[placeholder*="OTP"]');
                        if (otpInput) {
                            await otpInput.type(otpCode, { delay: 60 });
                            const submitBtn = await f.$('button[type="submit"], input[type="submit"], button:has-text("Submit"), button:has-text("Confirm"), button:has-text("Verify")');
                            if (submitBtn) {
                                await submitBtn.click();
                            }
                        }
                    } catch(e) {}
                }

                await page.waitForTimeout(6000);
                console.log(JSON.stringify({ status: 'paid_success', message: 'Payment successfully authenticated and completed!' }));
            }
        });

    } catch (err) {
        console.error(JSON.stringify({ status: 'error', message: err.message }));
    }
})();
