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

test('no browser storage is used on the public site', async ({ page }) => {
    await page.goto('/');

    const storage = await page.evaluate(() => ({ local: localStorage.length, session: sessionStorage.length }));
    expect(storage).toEqual({ local: 0, session: 0 });
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
