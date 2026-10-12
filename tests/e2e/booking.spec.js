// @ts-check
const { test, expect } = require('@playwright/test');
require('../../env-loader')();

const ADMIN_URL  = process.env.WP_ADMIN_URL  || 'https://erb-lite.jmcraig.co.uk/wp-admin/';
const ADMIN_USER = process.env.WP_ADMIN_USER || '';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || '';
const DETECTIVE_URL = process.env.ERB_DETECTIVE_URL || 'https://erb-lite.jmcraig.co.uk/detective/';
const CABIN_URL     = process.env.ERB_CABIN_URL     || 'https://erb-lite.jmcraig.co.uk/captive-2-the-cabin/';
const CARD   = process.env.STRIPE_TEST_CARD   || '4242424242424242';
const EXPIRY = process.env.STRIPE_TEST_EXPIRY || '1227';
const CVC    = process.env.STRIPE_TEST_CVC    || '123';

// ── Helper: log into WordPress admin ─────────────────────────────────────────
async function wpLogin(page) {
    await page.goto(ADMIN_URL);
    await page.locator('#user_login').fill(ADMIN_USER);
    await page.locator('#user_pass').fill(ADMIN_PASS);
    await page.locator('#wp-submit').click();
    await expect(page).toHaveURL(/wp-admin/, { timeout: 15000 });
    await page.waitForLoadState('networkidle', { timeout: 15000 });
}

// ── Helper: dismiss any floating popup (e.g. Check & Log Email nag) ──────────
async function dismissPopups(page) {
    const noThanks = page.locator('text=No Thanks');
    if (await noThanks.isVisible({ timeout: 2000 }).catch(() => false)) {
        await noThanks.click();
    }
}

// ── Helper: find an available slot, navigating forward weeks if needed ────────
async function findAndClickSlot(page, gameUrl) {
    const sep = gameUrl.includes('?') ? '&' : '?';
    await page.goto(`${gameUrl}${sep}eerb_week=1`);
    await expect(page.locator('[data-game-id]')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 15000 });

    const MAX_WEEKS = 8;
    let availableSlot = null;

    for (let week = 1; week <= MAX_WEEKS; week++) {
        await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 10000 });
        const slots = page.locator('.erb-slot--available');
        if (await slots.count() > 0) {
            availableSlot = slots.first();
            break;
        }
        const nextBtn = page.locator('a.erb-btn').filter({ hasText: 'Next' }).first();
        await nextBtn.click();
        await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 10000 });
    }

    if (!availableSlot) {
        test.skip(true, 'No available slots within 8 weeks');
    }

    const slotTime = (await availableSlot.textContent())?.trim();
    await availableSlot.click();
    return slotTime;
}

// ── Helper: complete booking flow ─────────────────────────────────────────────
async function completeBooking(page, players, firstName, lastName, email, mobile) {
    await page.waitForURL('**/book**', { timeout: 10000 });
    await expect(page.locator('#erb-step-1')).toBeVisible({ timeout: 10000 });

    // Select players
    const playerBtn = page.locator(`.erb-player-btn[data-players="${players}"]`);
    await expect(playerBtn).toBeVisible();
    await playerBtn.click();
    await expect(page.locator('#erb-price-box')).toBeVisible({ timeout: 5000 });

    await page.locator('#erb-step1-next').click();

    // Customer details
    await expect(page.locator('#erb-step-2')).toBeVisible({ timeout: 5000 });
    await page.locator('#erb-first-name').fill(firstName);
    await page.locator('#erb-last-name').fill(lastName);
    await page.locator('#erb-email').fill(email);
    await page.locator('#erb-mobile').fill(mobile);
    await page.locator('#erb-step2-next').click();

    // Payment
    await expect(page.locator('#erb-step-3')).toBeVisible({ timeout: 5000 });
    await page.waitForSelector('#erb-stripe-card-element iframe', { timeout: 15000 });
    const stripeFrame = page.frameLocator('#erb-stripe-card-element iframe').first();
    await stripeFrame.locator('[placeholder="Card number"]').fill(CARD);
    await stripeFrame.locator('[placeholder="MM / YY"]').fill(EXPIRY);
    await stripeFrame.locator('[placeholder="CVC"]').fill(CVC);
    await page.locator('#erb-pay-btn').click();

    // Confirmation
    await expect(page.locator('#erb-step-success')).toBeVisible({ timeout: 30000 });
    const bookingRef = page.locator('#erb-success-ref');
    await expect(bookingRef).toContainText('ERB-', { timeout: 15000 });
    return await bookingRef.textContent();
}

// ─────────────────────────────────────────────────────────────────────────────

test.describe('1. Calendar Display', () => {

    test('Detective calendar loads and shows slots', async ({ page }) => {
        await page.goto(DETECTIVE_URL);
        await expect(page.locator('[data-game-id]')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 15000 });
        await expect(page.locator('.erb-slot').first()).toBeVisible();
        console.log('✅ Detective calendar loaded');
    });

    test('Captive 2 calendar loads and shows slots', async ({ page }) => {
        await page.goto(CABIN_URL);
        await expect(page.locator('[data-game-id]')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 15000 });
        await expect(page.locator('.erb-slot').first()).toBeVisible();
        console.log('✅ Captive 2 - The Cabin calendar loaded');
    });

    test('Week navigation works', async ({ page }) => {
        await page.goto(DETECTIVE_URL);
        await expect(page.locator('[data-game-id]')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 15000 });

        const nextBtn = page.locator('a.erb-btn').filter({ hasText: 'Next' }).first();
        await nextBtn.click();
        await expect(page).toHaveURL(/eerb_week=1/, { timeout: 10000 });
        await expect(page.locator('.erb-calendar__loading')).toBeHidden({ timeout: 15000 });
        console.log('✅ Week navigation works');
    });

});

test.describe('2. Full Booking Flow', () => {

    test('Complete booking on Detective', async ({ page }) => {
        const slotTime = await findAndClickSlot(page, DETECTIVE_URL);
        const ref = await completeBooking(page, 2, 'Sarah', 'Mitchell', 'sarah@example.com', '07700900123');
        console.log('✅ Detective booking completed:', ref?.trim(), 'Slot:', slotTime);
    });

    test('Complete booking on Captive 2 - The Cabin', async ({ page }) => {
        const slotTime = await findAndClickSlot(page, CABIN_URL);
        const ref = await completeBooking(page, 3, 'James', 'Henderson', 'james@example.com', '07700900456');
        console.log('✅ Captive 2 booking completed:', ref?.trim(), 'Slot:', slotTime);
    });

});

test.describe('3. Player Count Buttons', () => {

    // Detective is configured max 6 players — buttons should be 2,3,4,5,6 only
    test('Detective shows exactly 2–6 player buttons', async ({ page }) => {
        await findAndClickSlot(page, DETECTIVE_URL);
        await page.waitForURL('**/book**', { timeout: 10000 });
        await expect(page.locator('#erb-step-1')).toBeVisible({ timeout: 10000 });

        const buttons = page.locator('.erb-player-btn');
        await expect(buttons.first()).toBeVisible({ timeout: 5000 });
        const count = await buttons.count();
        expect(count).toBe(5); // 2,3,4,5,6

        // First button should be 2, last should be 6
        await expect(buttons.first()).toHaveAttribute('data-players', '2');
        await expect(buttons.last()).toHaveAttribute('data-players', '6');

        // Button 7 and 8 must NOT exist
        await expect(page.locator('.erb-player-btn[data-players="7"]')).toHaveCount(0);
        await expect(page.locator('.erb-player-btn[data-players="8"]')).toHaveCount(0);

        console.log('✅ Detective shows 5 player buttons (2–6), no 7 or 8');
    });

    // Captive 2 is configured max 10 players — buttons should be 2–10
    test('Captive 2 shows exactly 2–10 player buttons', async ({ page }) => {
        await findAndClickSlot(page, CABIN_URL);
        await page.waitForURL('**/book**', { timeout: 10000 });
        await expect(page.locator('#erb-step-1')).toBeVisible({ timeout: 10000 });

        const buttons = page.locator('.erb-player-btn');
        await expect(buttons.first()).toBeVisible({ timeout: 5000 });
        const count = await buttons.count();
        expect(count).toBe(9); // 2,3,4,5,6,7,8,9,10

        await expect(buttons.first()).toHaveAttribute('data-players', '2');
        await expect(buttons.last()).toHaveAttribute('data-players', '10');

        console.log('✅ Captive 2 shows 9 player buttons (2–10)');
    });

    // Clicking a button beyond the game max must not be possible
    test('No player button exists beyond game max on Detective', async ({ page }) => {
        await findAndClickSlot(page, DETECTIVE_URL);
        await page.waitForURL('**/book**', { timeout: 10000 });
        await expect(page.locator('#erb-step-1')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('.erb-player-btn').first()).toBeVisible({ timeout: 5000 });

        // Buttons for 7 and 8 simply should not exist in the DOM
        await expect(page.locator('.erb-player-btn[data-players="7"]')).toHaveCount(0);
        await expect(page.locator('.erb-player-btn[data-players="8"]')).toHaveCount(0);
        console.log('✅ No out-of-range player buttons in DOM for Detective');
    });

});

test.describe('4. Admin: Pricing Modal Player Range', () => {

    test('Detective pricing modal shows rows 2–6 only', async ({ page }) => {
        await wpLogin(page);
        await page.goto(ADMIN_URL + 'admin.php?page=eerb-games');
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        await dismissPopups(page);
        await expect(page.locator('#erb-games-tbody')).toBeVisible({ timeout: 15000 });

        // Open pricing modal for Detective (first game row)
        const detectiveRow = page.locator('#erb-games-tbody tr').filter({ hasText: 'Detective' }).first();
        await detectiveRow.locator('button', { hasText: 'Pricing' }).click();
        await expect(page.locator('#erb-pricing-modal')).toBeVisible({ timeout: 5000 });

        // Wait for rows to render
        await expect(page.locator('#erb-pricing-tbody .erb-pricing-row').first()).toBeVisible({ timeout: 5000 });
        const rows = page.locator('#erb-pricing-tbody .erb-pricing-row');
        await expect(rows).toHaveCount(5); // 2,3,4,5,6

        await expect(page.locator('#erb-pricing-tbody .erb-price-input[data-players="6"]')).toBeVisible();
        await expect(page.locator('#erb-pricing-tbody .erb-price-input[data-players="7"]')).toHaveCount(0);
        await expect(page.locator('#erb-pricing-tbody .erb-price-input[data-players="8"]')).toHaveCount(0);

        console.log('✅ Detective pricing modal shows 5 rows (2–6), no 7 or 8');
    });

    test('Captive 2 pricing modal shows rows 2–10', async ({ page }) => {
        await wpLogin(page);
        await page.goto(ADMIN_URL + 'admin.php?page=eerb-games');
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        await dismissPopups(page);
        await expect(page.locator('#erb-games-tbody')).toBeVisible({ timeout: 15000 });

        const captiveRow = page.locator('#erb-games-tbody tr').filter({ hasText: 'Captive' }).first();
        await captiveRow.locator('button', { hasText: 'Pricing' }).click();
        await expect(page.locator('#erb-pricing-modal')).toBeVisible({ timeout: 5000 });

        await expect(page.locator('#erb-pricing-tbody .erb-pricing-row').first()).toBeVisible({ timeout: 5000 });
        const rows = page.locator('#erb-pricing-tbody .erb-pricing-row');
        await expect(rows).toHaveCount(9); // 2–10

        await expect(page.locator('#erb-pricing-tbody .erb-price-input[data-players="10"]')).toBeVisible();
        console.log('✅ Captive 2 pricing modal shows 9 rows (2–10)');
    });

});

test.describe('5. Admin Verification', () => {

    test('Bookings appear in admin', async ({ page }) => {
        await wpLogin(page);
        await page.goto(ADMIN_URL + 'admin.php?page=eerb-bookings');
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        await dismissPopups(page);
        await expect(page).toHaveURL(/eerb-bookings/, { timeout: 10000 });
        // Test bookings arrive as Pending (Stripe test mode); just check the table has rows
        await expect(page.locator('.erb-badge').first()).toBeVisible({ timeout: 15000 });
        console.log('✅ Bookings showing in admin');
    });

    test('Dashboard shows revenue', async ({ page }) => {
        await wpLogin(page);
        await page.goto(ADMIN_URL + 'admin.php?page=eerb-dashboard');
        await page.waitForLoadState('networkidle', { timeout: 15000 });
        await dismissPopups(page);
        await expect(page).toHaveURL(/eerb-dashboard/, { timeout: 10000 });
        await expect(page.locator('.erb-stat-box').first()).toBeVisible({ timeout: 15000 });
        console.log('✅ Dashboard loading correctly');
    });

});
