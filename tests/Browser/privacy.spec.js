// Privacy by default: no third-party requests, no cookies for visitors.
import { test, expect } from '@playwright/test';

for (const path of ['/', '/verwaltung/login', '/gibt-es-nicht']) {
    test(`${path} makes no third-party requests`, async ({ page, baseURL }) => {
        const origin = new URL(baseURL).origin;
        const foreign = [];
        page.on('request', (request) => {
            const url = request.url();
            if (!url.startsWith('data:') && new URL(url).origin !== origin) {
                foreign.push(url);
            }
        });

        await page.goto(path);
        await page.waitForLoadState('networkidle');

        expect(foreign).toEqual([]);
    });
}

test('anonymous visitor of the public site receives no cookies', async ({ page, context }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    expect(await context.cookies()).toEqual([]);
});

test('ordinary browsing and opening preferences never write storage; only explicit choices do', async ({ page, context }) => {
    // Catch attempted writes too, including entries created and immediately deleted.
    await context.addInitScript(() => {
        window.__storageMutations = [];
        for (const method of ['setItem', 'removeItem', 'clear']) {
            const original = Storage.prototype[method];
            Storage.prototype[method] = function (...args) {
                window.__storageMutations.push({ area: this === localStorage ? 'local' : 'session', method, key: args[0] ?? null });
                return original.apply(this, args);
            };
        }
    });
    for (const path of ['/', '/buergerservice', '/veranstaltungen', '/suche?q=Rathaus', '/gibt-es-nicht']) {
        const response = await page.goto(path);
        expect(response.status()).toBe(path === '/gibt-es-nicht' ? 404 : 200);
        expect(response.headers()['set-cookie']).toBeUndefined();
        await page.waitForLoadState('networkidle');
        // The intentionally minimal error template has no settings panel.
        if (response.status() !== 404) {
            await page.locator('.display-launcher').click();
            await page.keyboard.press('Escape');
        }
        await page.emulateMedia({ reducedMotion: 'reduce' });
        expect(await page.evaluate(() => window.__storageMutations)).toEqual([]);
        expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
        expect(await context.cookies()).toEqual([]);
    }
    await page.goto('/');
    await page.locator('.display-launcher').click();
    await page.getByLabel('Dunkler Modus', { exact: true }).check();
    expect(await page.evaluate(() => window.__storageMutations)).toEqual([
        { area: 'local', method: 'setItem', key: 'merching.display-preferences.v1' },
    ]);
    expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([1, 0]);
    expect(await context.cookies()).toEqual([]);

    // Restoring a previous choice on navigation and in a new tab is read-only.
    await page.goto('/');
    await expect(page.locator('html')).toHaveClass(/display-dark/);
    expect(await page.evaluate(() => window.__storageMutations)).toEqual([]);
    const other = await context.newPage();
    await other.goto('/veranstaltungen');
    await expect(other.locator('html')).toHaveClass(/display-dark/);
    expect(await other.evaluate(() => window.__storageMutations)).toEqual([]);
    expect(await context.cookies()).toEqual([]);
});

test('strict CSP produces no violations', async ({ page }) => {
    const violations = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error' && /Content Security Policy/i.test(msg.text())) {
            violations.push(msg.text());
        }
    });

    for (const path of ['/', '/verwaltung/login', '/verwaltung/passwort-vergessen']) {
        await page.goto(path);
    }

    expect(violations).toEqual([]);
});
