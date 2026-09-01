// ==UserScript==
// @name         Taqamul Auto-Login & OTP Bot (Direct Fast)
// @namespace    https://taqamul.local/
// @version      4.0
// @description  Super-Fast Login, reCAPTCHA solver, and live OTP injection for Taqamul Labor Portal
// @match        https://svp-international.pacc.sa/*
// @grant        GM_xmlhttpRequest
// @grant        GM_setValue
// @grant        GM_getValue
// @connect      localhost
// @connect      127.0.0.1
// @connect      api.capsolver.com
// @run-at       document-start
// ==/UserScript==

(function() {
    'use strict';

    const SITE_KEY = "6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy";
    const LOGIN_PAGE_URL = "https://svp-international.pacc.sa/auth/login?role=labor";
    const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    // Parse URL Hash Parameters
    function getHashParams() {
        const hash = window.location.hash.replace(/^#/, '');
        const params = new URLSearchParams(hash);
        return {
            id: params.get('auto_id') || params.get('id'),
            email: params.get('email'),
            password: params.get('pass') || params.get('password'),
            server: params.get('server') || 'http://localhost:8000',
            ck: params.get('ck') || 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133'
        };
    }

    let config = getHashParams();

    // Persist in sessionStorage across redirects
    if (config.email && config.password) {
        sessionStorage.setItem('taqamul_auto_candidate', JSON.stringify(config));
    } else {
        const saved = sessionStorage.getItem('taqamul_auto_candidate');
        if (saved) {
            try { config = JSON.parse(saved); } catch (e) {}
        }
    }

    if (!config || !config.email) {
        console.log("[Taqamul Auto-Bot] No candidate credentials found.");
        return;
    }

    console.log(`[Taqamul Auto-Bot] ⚡ Running auto-login for: ${config.email}`);

    // Create a floating progress badge on screen (TOP RIGHT)
    let badge = document.getElementById('taqamul-bot-badge');
    if (!badge) {
        badge = document.createElement('div');
        badge.id = 'taqamul-bot-badge';
        badge.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999999;
            background: #0f172a;
            color: #f8fafc;
            border: 2px solid #38bdf8;
            border-radius: 16px;
            padding: 14px 18px;
            font-family: ui-sans-serif, system-ui, sans-serif;
            font-size: 13px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.6), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-width: 350px;
            pointer-events: none;
        `;
        badge.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;padding-bottom:5px;">
                <b style="color:#38bdf8;font-size:13px;">⚡ Taqamul Auto-Login Bot</b>
                <span style="font-size:10px;background:#1e293b;color:#38bdf8;padding:2px 6px;border-radius:4px;font-weight:bold;">#${config.id || ''}</span>
            </div>
            <div style="font-size:11px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                Candidate: <b style="color:#fff;">${config.email}</b>
            </div>
            <div id="bot-status-msg" style="color:#34d399;font-weight:600;font-size:12px;">
                ⏳ Initializing Auto-Login...
            </div>
        `;
        const attachBadge = () => {
            if (document.body && !document.getElementById('taqamul-bot-badge')) {
                document.body.appendChild(badge);
            }
        };
        attachBadge();
        document.addEventListener('DOMContentLoaded', attachBadge);
    }

    function updateStatus(msg, color = '#34d399') {
        const el = document.getElementById('bot-status-msg');
        if (el) {
            el.innerHTML = msg;
            el.style.color = color;
        }
    }

    // Set value reliably for Vue / React input bindings
    function setNativeValue(element, value) {
        if (!element) return;
        element.focus();
        const valueSetter = Object.getOwnPropertyDescriptor(element, 'value')?.set;
        const prototype = Object.getPrototypeOf(element);
        const prototypeValueSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
        
        if (prototypeValueSetter && valueSetter !== prototypeValueSetter) {
            prototypeValueSetter.call(element, value);
        } else if (valueSetter) {
            valueSetter.call(element, value);
        } else {
            element.value = value;
        }
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
        element.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: value }));
    }

    // Direct CapSolver Task Execution with Live Second-by-Second Timer
    function solveRecaptchaDirect(capsolverKey, preTaskId = null) {
        return new Promise((resolve, reject) => {
            const startTime = Date.now();
            updateStatus('🧩 Solving reCAPTCHA v2 with CapSolver AI (0.0s)...', '#fbbf24');

            const startPolling = (taskId) => {
                // Fast poll loop every 300ms
                const pollInterval = setInterval(() => {
                    const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
                    updateStatus(`🧩 Solving reCAPTCHA with CapSolver AI (${elapsed}s)...`, '#fbbf24');

                    if (Date.now() - startTime > 50000) {
                        clearInterval(pollInterval);
                        return reject(new Error('CapSolver Timeout (50s)'));
                    }

                    fetch('https://api.capsolver.com/getTaskResult', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            clientKey: capsolverKey,
                            taskId: taskId
                        })
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.status === 'ready') {
                            clearInterval(pollInterval);
                            updateStatus(`✅ reCAPTCHA Solved in ${elapsed}s!`, '#34d399');
                            resolve(result.solution.gRecaptchaResponse);
                        } else if (result.status === 'failed') {
                            clearInterval(pollInterval);
                            reject(new Error('CapSolver Failed: ' + JSON.stringify(result)));
                        }
                    })
                    .catch(() => {});
                }, 300);
            };

            if (preTaskId) {
                console.log(`[Taqamul Auto-Bot] ⚡ Using Pre-Created CapSolver Task: ${preTaskId}`);
                return startPolling(preTaskId);
            }

            fetch('https://api.capsolver.com/createTask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    clientKey: capsolverKey,
                    task: {
                        type: "ReCaptchaV2TaskProxyLess",
                        websiteURL: LOGIN_PAGE_URL,
                        websiteKey: SITE_KEY
                    }
                })
            })
            .then(r => r.json())
            .then(taskData => {
                if (!taskData || taskData.errorId !== 0) {
                    return reject(new Error(taskData ? (taskData.errorDescription || 'Task create failed') : 'Task create failed'));
                }
                startPolling(taskData.taskId);
            })
            .catch(reject);
        });
    }

    // ⚡ Start CapSolver solve immediately in parallel
    const captchaPromise = solveRecaptchaDirect(
        config.ck || 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133',
        config.task_id || null
    );

    // Step 1: Fill Credentials & Submit Form
    async function handleLogin() {
        for (let w = 0; w < 40; w++) {
            const emailInput = document.querySelector('input[type="email"], input[name="email"], input[name="username"], input[placeholder*="email" i], #email');
            const passInput = document.querySelector('input[type="password"], input[name="password"], #password');

            if (emailInput && passInput) {
                setNativeValue(emailInput, config.email);
                setNativeValue(passInput, config.password);

                try {
                    const token = await captchaPromise;
                    updateStatus('🚀 reCAPTCHA Solved! Submitting Login...', '#34d399');

                    document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(ta => {
                        ta.value = token;
                        ta.innerHTML = token;
                        ta.dispatchEvent(new Event('input', { bubbles: true }));
                        ta.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    // Trigger grecaptcha callback if available
                    if (window.___grecaptcha_cfg && window.___grecaptcha_cfg.clients) {
                        for (const c in window.___grecaptcha_cfg.clients) {
                            const client = window.___grecaptcha_cfg.clients[c];
                            for (const k in client) {
                                if (client[k] && client[k].callback && typeof client[k].callback === 'function') {
                                    try { client[k].callback(token); } catch (e) {}
                                }
                            }
                        }
                    }

                    // Trigger Vue hooks
                    document.querySelectorAll('*').forEach(el => {
                        if (el.__vue__) {
                            const v = el.__vue__;
                            if (v.recaptchaResponse !== undefined) v.recaptchaResponse = token;
                            if (v.recaptchaStatus !== undefined) v.recaptchaStatus = 'ready';
                            if (v.successCaptcha) try { v.successCaptcha(token); } catch (e) {}
                        }
                    });

                    await delay(300);

                    // Click Submit Button
                    const btn = document.querySelector('button[type="submit"], form button, .btn-primary, button:has(span)');
                    if (btn) {
                        btn.click();
                        updateStatus('🚀 Submitted! Watching for OTP...', '#38bdf8');
                    }
                } catch (err) {
                    updateStatus('❌ Captcha Error: ' + err.message, '#f87171');
                }
                break;
            }
            await delay(200);
        }
    }

    // Step 2: Fetch OTP via JSONP or Server API
    function fetchOtpFromLocalServer(passengerId) {
        return new Promise((resolve) => {
            // 1. GM_xmlhttpRequest if present
            if (typeof GM_xmlhttpRequest !== 'undefined') {
                GM_xmlhttpRequest({
                    method: 'GET',
                    url: `${config.server}/api/passengers/${passengerId}/check-otp`,
                    onload: (res) => {
                        try {
                            const d = JSON.parse(res.responseText);
                            if (d && d.otp) return resolve(String(d.otp).trim());
                        } catch (e) {}
                        resolve(null);
                    },
                    onerror: () => resolve(null)
                });
                return;
            }

            // 2. JSONP Script Tag (No Mixed Content block on Bookmarklets)
            const cbName = '__taqamulOtp_' + Date.now();
            window[cbName] = function(data) {
                delete window[cbName];
                scriptEl.remove();
                if (data && data.otp) resolve(String(data.otp).trim());
                else resolve(null);
            };

            const scriptEl = document.createElement('script');
            scriptEl.src = `${config.server}/api/passengers/${passengerId}/otp-jsonp?callback=${cbName}&_t=${Date.now()}`;
            scriptEl.onerror = function() {
                scriptEl.remove();
                fetch(`${config.server}/api/passengers/${passengerId}/check-otp`)
                    .then(r => r.json())
                    .then(d => resolve(d?.otp ? String(d.otp).trim() : null))
                    .catch(() => resolve(null));
            };
            document.head.appendChild(scriptEl);
        });
    }

    // Step 3: Monitor for OTP Prompt & Fast Type
    async function handleOtp() {
        let attempts = 0;
        const otpInterval = setInterval(async () => {
            attempts++;
            if (attempts > 90) {
                clearInterval(otpInterval);
                return;
            }

            const allInputs = Array.from(document.querySelectorAll('input'));
            const isOtp = allInputs.some(i => 
                (i.placeholder && i.placeholder.toLowerCase().includes('otp')) ||
                (i.name && i.name.toLowerCase().includes('otp')) ||
                (i.name && i.name.toLowerCase().includes('code')) ||
                (i.className && i.className.includes('otp')) ||
                (i.maxLength === 6 || (i.maxLength === 1 && document.querySelectorAll('input[maxlength="1"]').length >= 4))
            ) || document.body.innerText.includes('verification code') || document.body.innerText.includes('OTP') || document.body.innerText.includes('رمز التحقق');

            if (isOtp) {
                updateStatus(`🔍 Listening for OTP from Mail Server (Poll ${attempts})...`, '#fbbf24');
                
                const otpCode = await fetchOtpFromLocalServer(config.id);

                if (otpCode && otpCode.length === 6) {
                    updateStatus(`🎯 OTP Found: ${otpCode}! Entering OTP...`, '#34d399');

                    // Check if 6 split single-digit inputs exist
                    const splitInputs = Array.from(document.querySelectorAll('input[maxlength="1"], input.otp-input, input[type="tel"]')).filter(el => el.offsetWidth > 0);
                    if (splitInputs.length >= 6) {
                        for (let i = 0; i < 6; i++) {
                            setNativeValue(splitInputs[i], otpCode[i]);
                            await delay(30);
                        }
                    } else {
                        // Single OTP input box
                        const targetInput = allInputs.find(i => 
                            (i.placeholder && i.placeholder.toLowerCase().includes('otp')) ||
                            (i.maxLength === 6) ||
                            (i.name && i.name.toLowerCase().includes('otp')) ||
                            (i.name && i.name.toLowerCase().includes('code')) ||
                            (i.type === 'number' || i.type === 'text')
                        );
                        if (targetInput) {
                            setNativeValue(targetInput, otpCode);
                        }
                    }

                    await delay(400);

                    // Click Confirm / Verify OTP
                    const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));
                    const verifyBtn = buttons.find(b => {
                        const t = (b.innerText || b.value || '').toLowerCase();
                        return t.includes('verify') || t.includes('confirm') || t.includes('submit') || t.includes('تحقق') || t.includes('دخول') || t.includes('login') || t.includes('sign in');
                    });

                    if (verifyBtn) {
                        verifyBtn.click();
                        clearInterval(otpInterval);
                        sessionStorage.removeItem('taqamul_auto_candidate');
                        updateStatus('🎉 OTP Verified! Entering Dashboard...', '#34d399');
                        setTimeout(() => { badge?.remove(); }, 5000);
                    }
                }
            }
        }, 1500);
    }

    // Execute routines
    handleLogin();
    handleOtp();

})();
