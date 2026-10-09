// Automated accessibility smoke tests (axe-core, WCAG 2.2 A/AA rules).
// Automated checks find only part of all barriers – they do NOT prove
// accessibility. See docs/accessibility.md for the manual test plan.
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const pages = [
    { name: 'public placeholder', path: '/' },
    { name: 'public contact form', path: '/kontakt' },
    { name: 'contact confirmation', path: '/kontakt/bestaetigung' },
    { name: 'admin login', path: '/verwaltung/login' },
    { name: 'password forgotten', path: '/verwaltung/passwort-vergessen' },
    { name: 'not found page', path: '/gibt-es-nicht' },
];

async function expectNoViolations(page) {
    const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length}×)`);
    expect(summary, summary.join('\n')).toEqual([]);
}

for (const { name, path } of pages) {
    test(`${name} has no detectable axe violations`, async ({ page }) => {
        await page.goto(path);
        await expectNoViolations(page);
    });
}

test('login validation errors are accessible and receive focus', async ({ page }) => {
    await page.goto('/verwaltung/login');
    await page.getByRole('button', { name: 'Anmelden' }).click();

    await expect(page).toHaveTitle(/^Fehler: Anmelden/);
    const summary = page.getByRole('heading', { name: 'Bitte prüfen Sie Ihre Angaben' });
    await expect(summary).toBeVisible();
    await expect(page.locator('.error-summary')).toBeFocused();

    const email = page.getByLabel('E-Mail-Adresse');
    await expect(email).toHaveAttribute('aria-invalid', 'true');
    await expect(email).toHaveAccessibleDescription(/Fehler:/);

    await expectNoViolations(page);
});

test('skip link is the first focusable element and moves focus to main', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Tab');

    const skip = page.getByRole('link', { name: 'Zum Inhalt springen' });
    await expect(skip).toBeFocused();
    await expect(skip).toBeInViewport();

    await page.keyboard.press('Enter');
    await expect(page.locator('main#inhalt')).toBeFocused();
});

test('login form is fully keyboard operable in logical order', async ({ page }) => {
    await page.goto('/verwaltung/login');

    const order = [];
    for (let i = 0; i < 5; i++) {
        await page.keyboard.press('Tab');
        order.push(await page.evaluate(() => document.activeElement?.id || document.activeElement?.textContent?.trim()));
    }

    expect(order).toEqual(['Zum Inhalt springen', 'email', 'password', 'Anmelden', 'Passwort vergessen?']);
});

test('content reflows at 320 CSS pixels without horizontal scrolling', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 640 });

    for (const path of ['/', '/verwaltung/login']) {
        await page.goto(path);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, path).toBeLessThanOrEqual(0);
    }
});

test('contact validation errors are accessible and never retain the message', async ({ page }) => {
    await page.goto('/kontakt');
    await page.getByRole('button', { name: 'Nachricht senden' }).click();
    await expect(page).toHaveTitle(/^Fehler: Kontakt/);
    await expect(page.locator('.error-summary')).toBeFocused();
    await expect(page.getByLabel('E-Mail (Pflichtfeld)')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByLabel('Nachricht (Pflichtfeld)')).toHaveValue('');
    await expectNoViolations(page);
});

test('contact form reflows and has no third-party requests', async ({ page, context }) => {
    const external = [];
    page.on('request', request => {
        if (new URL(request.url()).origin !== new URL(test.info().project.use.baseURL).origin) external.push(request.url());
    });
    await page.setViewportSize({ width: 320, height: 640 });
    await page.goto('/kontakt');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    expect(overflow).toBeLessThanOrEqual(0);
    expect(external).toEqual([]);
    expect((await context.cookies()).map(c => c.name)).not.toContain('XSRF-TOKEN');
});
