const axios = require('axios');
const https = require('https');

const CAPSOLVER_KEY = process.env.CAPSOLVER_API_KEY || "CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133";
const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
const LOGIN_PAGE_URL = "https://svp-international.pacc.sa/auth/login?role=labor";

const email = process.argv[2] || "pool__485381@wafidmaster.com";
const password = process.argv[3] || "Taqamul@2723!";

const delay = (ms) => new Promise(r => setTimeout(r, ms));

async function solveRecaptcha() {
    console.log("[CapSolver AI ⚡] Solving Google reCAPTCHA v2 via Pure HTTP API...");
    const res = await axios.post("https://api.capsolver.com/createTask", {
        clientKey: CAPSOLVER_KEY,
        task: {
            type: "ReCaptchaV2TaskProxyLess",
            websiteURL: LOGIN_PAGE_URL,
            websiteKey: SITE_KEY
        }
    });

    if (!res.data || res.data.errorId !== 0) {
        throw new Error("CapSolver Task creation failed: " + JSON.stringify(res.data));
    }

    const taskId = res.data.taskId;
    for (let i = 0; i < 40; i++) {
        await delay(1500);
        const poll = await axios.post("https://api.capsolver.com/getTaskResult", {
            clientKey: CAPSOLVER_KEY,
            taskId: taskId
        });

        if (poll.data.status === "ready") {
            console.log("[CapSolver AI ✅] reCAPTCHA Solved in " + ((i + 1) * 1.5) + "s!");
            return poll.data.solution.gRecaptchaResponse;
        }
    }
    throw new Error("CapSolver Timeout");
}

(async () => {
    console.log(`\n==========================================================`);
    console.log(`   PURE HTTP REQUEST LOGIN ENGINE (ZERO BROWSER / ZERO RAM)   `);
    console.log(`   Email   : ${email}`);
    console.log(`   Password: ${password}`);
    console.log(`==========================================================\n`);

    try {
        const captchaToken = await solveRecaptcha();

        // Configure custom HTTPS agent to allow modern TLS ciphers
        const agent = new https.Agent({
            keepAlive: true,
            rejectUnauthorized: false
        });

        const instance = axios.create({
            httpsAgent: agent,
            timeout: 15000,
            headers: {
                'Host': 'svp-international.pacc.sa',
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept': 'application/json, text/plain, */*',
                'Accept-Language': 'en-US,en;q=0.9,bn;q=0.8',
                'Accept-Encoding': 'gzip, deflate, br',
                'Content-Type': 'application/json',
                'X-Tenant-Name': 'svp-international',
                'Origin': 'https://svp-international.pacc.sa',
                'Referer': 'https://svp-international.pacc.sa/auth/login?role=labor',
                'Sec-Ch-Ua': '"Chromium";v="122", "Not(A:Brand";v="24", "Google Chrome";v="122"',
                'Sec-Ch-Ua-Mobile': '?0',
                'Sec-Ch-Ua-Platform': '"Windows"',
                'Sec-Fetch-Dest': 'empty',
                'Sec-Fetch-Mode': 'cors',
                'Sec-Fetch-Site': 'same-origin'
            }
        });

        // Step 1: GET initial session cookies from landing page
        console.log("[Pure HTTP] Step 1: Requesting landing page for session cookies...");
        const initRes = await instance.get("https://svp-international.pacc.sa/auth/login?role=labor");
        
        let cookies = [];
        if (initRes.headers['set-cookie']) {
            cookies = initRes.headers['set-cookie'].map(c => c.split(';')[0]);
            console.log("[Pure HTTP] Received Cookies:", cookies.join('; '));
        }

        const authHeaders = {
            'Cookie': cookies.join('; ')
        };

        // Step 2: POST Login payload directly to Taqamul Auth API
        console.log("[Pure HTTP] Step 2: Sending Login payload via HTTP POST...");
        
        const loginPayload = {
            email: email,
            password: password,
            recaptcha_token: captchaToken,
            g_recaptcha_response: captchaToken,
            role: "labor"
        };

        const endpoints = [
            "https://svp-international.pacc.sa/api/v1/auth/login",
            "https://svp-international.pacc.sa/api/v1/individual_labor_space/auth/login",
            "https://svp-international.pacc.sa/api/v1/individual_labor_space/login"
        ];

        for (const url of endpoints) {
            console.log(`Testing Pure HTTP Endpoint: ${url}...`);
            try {
                const res = await instance.post(url, loginPayload, { headers: authHeaders });
                console.log(`HTTP Status (${url}): ${res.status}`);
                console.log("Response Data:", JSON.stringify(res.data, null, 2));

                if (res.status === 200 || res.status === 201) {
                    console.log("\n🎯 PURE HTTP LOGIN SUCCESSFUL!");
                    break;
                }
            } catch (err) {
                console.log(`Endpoint ${url} Status:`, err.response?.status || err.message);
                if (err.response?.data) {
                    console.log("Error Body:", JSON.stringify(err.response.data).substring(0, 300));
                }
            }
        }

    } catch (err) {
        console.error("Pure HTTP Engine Error:", err.message);
    }
})();
