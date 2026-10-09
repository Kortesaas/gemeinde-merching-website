import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';

// The synthetic fixture helper refuses to run outside APP_ENV=local and cleans up after this serial group.
test.describe('local content composition', () => {
    test.describe.configure({ mode: 'serial' });
    test.skip(process.env.PARITY_BROWSER_FIXTURES !== 'docker', 'Opt in to disposable local Docker fixtures with PARITY_BROWSER_FIXTURES=docker.');
    let fixture;
    test.beforeAll(() => {
        fixture = JSON.parse(execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'tests/Browser/parity-fixtures.php'], { encoding: 'utf8' }));
    });
    test.afterAll(() => {
        execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'tests/Browser/parity-fixtures.php', 'cleanup']);
    });

    async function accessible(page) {
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
        expect(result.violations.map(v => `${v.id}: ${v.help}`)).toEqual([]);
    }

    test('blocks and gallery have accessible markup and remain stateless', async ({ page, context, baseURL }) => {
        const external = [];
        page.on('request', request => {
            if (new URL(request.url()).origin !== new URL(baseURL).origin) external.push(request.url());
        });
        await page.goto('/browser-test-composition');
        await expect(page.getByRole('img', { name: 'Synthetisches Galeriebild' })).toBeVisible();
        await accessible(page);
        expect(await context.cookies()).toEqual([]);
        expect(external).toEqual([]);
    });

    test('accordion works with keyboard and JavaScript disabled', async ({ browser, baseURL }) => {
        const context = await browser.newContext({ baseURL, javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto('/browser-test-composition');
        await page.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(page.getByText('Zusätzliche Testinformation.')).toBeVisible();
        await context.close();
    });

    test('service fee table and contextual feedback form pass axe', async ({ page }) => {
        await page.goto('/browser-test-service');
        await expect(page.getByRole('table', { name: 'Gebühren' })).toBeVisible();
        await accessible(page);
        await page.goto('/kontakt?feedback=%2Fbrowser-test-composition');
        await expect(page.getByText(/Fehler melden zu/)).toBeVisible();
        await accessible(page);
    });

    test('row editor can reorder with keyboard without JavaScript', async ({ browser, baseURL }) => {
        // Axe injects JavaScript, so scan the same editor in a separate enabled context.
        const scanContext = await browser.newContext({ baseURL });
        await scanContext.addCookies([{ name: fixture.cookieName, value: fixture.cookie, url: baseURL, httpOnly: true }]);
        const scanPage = await scanContext.newPage();
        await scanPage.goto(`/verwaltung/seiten/${fixture.pageId}`);
        await accessible(scanPage);
        await scanContext.close();

        const context = await browser.newContext({ baseURL, javaScriptEnabled: false });
        await context.addCookies([{ name: fixture.cookieName, value: fixture.cookie, url: baseURL, httpOnly: true }]);
        const page = await context.newPage();
        await page.goto(`/verwaltung/seiten/${fixture.pageId}`);
        await expect(page.getByRole('heading', { name: 'Browser Test Composition', exact: true })).toBeVisible();
        await page.locator('#blocks_1_sort_order').focus();
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.type('0');
        await page.locator('#blocks_0_sort_order').fill('1');
        await page.getByRole('button', { name: 'Speichern', exact: true }).click();
        await expect(page.getByText('Änderungen wurden gespeichert.')).toBeVisible();
        await expect(page.locator('#blocks_0_type')).toHaveValue('text');
        await context.close();
    });
});
