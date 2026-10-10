import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';

test.describe('migrated email form', () => {
    test.skip(process.env.REAL_MIGRATION !== '1', 'Use the isolated local migration site.');
    for (const width of [320, 390, 768, 1024, 1280, 1440]) {
        test(`legacy categories and complete form remain accessible at ${width}px`, async ({ page, baseURL, context }) => {
            await page.setViewportSize({ width, height: 960 });
            const external = [];
            page.on('request', r => { if (new URL(r.url()).origin !== new URL(baseURL).origin) external.push(r.url()); });
            const response = await page.goto('/email-formular');
            expect(response.status()).toBe(200);
            const definitions = JSON.parse(readFileSync('migration-source/prepared/manifest.json', 'utf8')).contact_routes;
            expect(definitions).toHaveLength(15);
            // The live public dropdown has these aliases; its backing addresses stay private.
            const live = readFileSync('migration-source/live-email-formular.html', 'utf8');
            const options = [...live.match(/<select[^>]+name="recipient"[^>]*>(.*?)<\/select>/s)[1].matchAll(/<option[^>]*>(.*?)<\/option>/gs)].map(m => m[1]);
            expect(await page.locator('#contact_route_id option').allTextContents()).toEqual(['Bitte wählen', ...options]);
            const markup = await page.content();
            for (const topic of definitions) for (const address of topic.recipients) expect(markup).not.toContain(address);
            for (const field of ['contact_name', 'contact_email', 'contact_street', 'contact_postal_code', 'contact_city', 'contact_subject', 'contact_privacy']) {
                await expect(page.locator(`#${field}`)).toHaveAttribute('required');
            }
            await expect(page.locator('#contact_message')).not.toHaveAttribute('required');
            await page.locator('#contact_route_id').selectOption({ label: 'Einwohnermeldewesen, Ausgabe von Ausweisen, Fundbüro' });
            await expect(page.locator('[data-contact-recipient]')).toHaveText('Einwohnermeldewesen, Ausgabe von Ausweisen, Fundbüro');
            await expect(page.getByLabel('Auf dem Postweg')).toBeVisible();
            await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
            const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
            expect(scan.violations.map(v => `${v.id}: ${v.nodes.map(n => n.html).join('; ')}`)).toEqual([]);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            expect(external).toEqual([]);
            expect(await page.evaluate(() => localStorage.length + sessionStorage.length)).toBe(0);
            expect((await context.cookies()).map(c => c.name)).not.toContain('XSRF-TOKEN');
            await page.screenshot({ path: `migration-source/preview-contact-${width}.png`, fullPage: true });
        });
    }
});
