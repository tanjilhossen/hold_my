// ========================================================
// TAQAMUL 1-CLICK LIVE FORM AUTO-FILLER SCRIPT
// Paste this into Chrome Console (F12 -> Console -> Enter)
// ========================================================

(async function() {
    const delay = ms => new Promise(r => setTimeout(r, ms));
    const randomId = Math.floor(10000000 + Math.random() * 90000000);
    
    const candidate = {
        firstName: "MD",
        lastName: "HASAN",
        passport: "A" + randomId,
        dob: "12/04/1996",
        expDate: "18/10/2034",
        email: `taqamul_${randomId}@yopmail.com`,
        phone: "1712345678",
        password: "Taqamul@2026!"
    };

    console.log("%c[AUTO-FILL] Starting Step 1...", "color: #10b981; font-size: 16px; font-weight: bold;");

    // 1. First Name
    const first = document.querySelector('input[placeholder*="first name"]');
    if (first) {
        first.value = candidate.firstName;
        first.dispatchEvent(new Event('input', { bubbles: true }));
        first.dispatchEvent(new Event('change', { bubbles: true }));
    }
    await delay(400);

    // 2. Last Name
    const last = document.querySelector('input[placeholder*="last name"]');
    if (last) {
        last.value = candidate.lastName;
        last.dispatchEvent(new Event('input', { bubbles: true }));
        last.dispatchEvent(new Event('change', { bubbles: true }));
    }
    await delay(400);

    // 3. Passport
    const pass = document.querySelector('input[placeholder*="password number"], input[placeholder*="passport"]');
    if (pass) {
        pass.value = candidate.passport;
        pass.dispatchEvent(new Event('input', { bubbles: true }));
        pass.dispatchEvent(new Event('change', { bubbles: true }));
    }
    await delay(400);

    // 4. Country
    const country = document.querySelector('input[placeholder*="country"]');
    if (country) {
        country.click();
        await delay(500);
        const item = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span')).find(el => el.innerText && el.innerText.includes('Bangladesh'));
        if (item) item.click();
    }
    await delay(400);

    // 5. Nationality
    const nat = document.querySelector('input[placeholder*="nationality"]');
    if (nat) {
        nat.click();
        await delay(500);
        const item = Array.from(document.querySelectorAll('.el-select-dropdown__item, li, span')).find(el => el.innerText && el.innerText.includes('Bangladeshi'));
        if (item) item.click();
    }
    await delay(400);

    // 6. Dates
    const dates = document.querySelectorAll('input[placeholder*="DD/MM/YYYY"]');
    if (dates.length > 0) {
        dates[0].value = candidate.dob;
        dates[0].dispatchEvent(new Event('input', { bubbles: true }));
        dates[0].dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (dates.length > 1) {
        dates[1].value = candidate.expDate;
        dates[1].dispatchEvent(new Event('input', { bubbles: true }));
        dates[1].dispatchEvent(new Event('change', { bubbles: true }));
    }
    await delay(600);

    console.log("%c[AUTO-FILL] Step 1 Complete! Clicking Continue...", "color: #10b981; font-weight: bold;");
    const continueBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText && b.innerText.includes('Continue'));
    if (continueBtn) continueBtn.click();

    await delay(1500);

    // Confirm Modal
    const confirmBtn = Array.from(document.querySelectorAll('.el-dialog button, .el-message-box button, button')).find(b => b.innerText && (b.innerText.toLowerCase().includes('confirm') || b.innerText.toLowerCase().includes('yes')));
    if (confirmBtn) confirmBtn.click();

    alert(`Step 1 filled!\nCandidate: ${candidate.firstName} ${candidate.lastName}\nPassport: ${candidate.passport}\nEmail: ${candidate.email}`);
})();
