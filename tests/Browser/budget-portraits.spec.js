import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Read-only checks against the local development demo; no real data is changed.
for (const width of [390, 768, 1024, 1440]) {
    test(`council portraits and yearly budgets reflow accessibly at ${width}px`, async ({ page, context }) => {
        await page.setViewportSize({ width, height: 960 });
        const council = await page.goto('/gemeinderat');
        test.skip(council.status() !== 200 || await page.locator('.member-card').count() === 0, 'Requires the development council demo.');
        const images = page.locator('.member-card__portrait img');
        await expect(images.first()).toBeVisible();
        for (const image of await images.all()) {
            await expect(image).toHaveAttribute('alt', '');
            const box = await image.boundingBox();
            expect(Math.abs(box.width / box.height - .8)).toBeLessThan(.01);
        }
        await expect(page.locator('img[src$="council-placeholder.svg"]').first()).toHaveAttribute('alt', '');
        for (const path of ['/gemeinderat', '/haushaltsplaene', '/haushaltsplaene/2026']) {
            const response = await page.goto(path);
            expect(response.status()).toBe(200);
            await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
            expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)).toBe(false);
            const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
            expect(scan.violations.map(v => `${v.id}: ${v.help}`)).toEqual([]);
        }
        expect(await context.cookies()).toEqual([]);
    });
}

test('budget downloads serve the same stored PDF and do not create a public session', async ({ page, context }) => {
    await page.goto('/haushaltsplaene/2026');
    const download = page.getByRole('link', { name: 'Gesamt-PDF herunterladen' });
    test.skip(await download.count() === 0, 'Requires the published budget demo.');
    const url = await download.getAttribute('href');
    const first = await context.request.get(url), second = await context.request.get(url);
    expect(first.status()).toBe(200);
    expect(first.headers()['content-type']).toBe('application/pdf');
    expect(first.headers()['x-content-type-options']).toBe('nosniff');
    const bytes = await first.body();
    expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
    expect(await second.body()).toEqual(bytes);
    expect(await context.cookies()).toEqual([]);
});
