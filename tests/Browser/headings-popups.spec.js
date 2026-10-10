import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('headings and popups with migrated content', () => {
    test.skip(process.env.REAL_MIGRATION !== '1', 'Use the local migration review site.');
    for (const width of [390, 1280, 1366]) {
        test(`readable headings, menus and dialogs at ${width}px`, async ({ page }) => {
            test.setTimeout(120000);
            await page.setViewportSize({ width, height: 900 });
            const oneLine = async locator => expect(await locator.evaluate(e => e.getBoundingClientRect().height <= parseFloat(getComputedStyle(e).lineHeight) + 1)).toBe(true);
            const accessible = async () => {
                await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
                const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
                expect(scan.violations.map(v => `${v.id}: ${v.nodes.map(n => n.html).join('; ')}`)).toEqual([]);
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            };
            for (const path of ['/', '/rathaus-und-politik/verwaltung', '/buergerservice/a-z', '/dokumente', '/leben', '/bauen-und-wirtschaft', '/barrierefreiheit']) {
                await page.goto(path);
                if (width >= 1280) await oneLine(page.locator('h1'));
            }
            await page.goto('/');
            await page.screenshot({ path: `migration-source/preview-headings-${width}.png` });
            if (width < 1024) await page.locator('.menu-toggle').click();
            const submenu = page.locator('summary').filter({ hasText: 'Untermenü Bürgerservice' });
            await submenu.focus();
            await page.keyboard.press('Enter');
            const branch = page.locator('.nav-branch[open]');
            await expect(branch.getByRole('link', { name: 'Ratsinformation', exact: true })).toBeVisible();
            if (width >= 1280) await oneLine(branch.locator('.mega__title'));
            await accessible();
            await page.screenshot({ path: `migration-source/preview-menu-${width}.png` });
            await page.keyboard.press('Escape');
            if (width < 1024 && await page.locator('.site-navigation').getAttribute('open') !== null) await page.locator('.menu-toggle').click();
            await page.locator('[data-search-trigger]:visible').click();
            await expect(page.locator('[data-search-shortcuts]')).toBeVisible();
            await accessible();
            await page.screenshot({ path: `migration-source/preview-search-${width}.png` });
            await page.locator('#overlay-search').fill('Haushalt');
            await expect(page.locator('#overlay-search-suggestions a').first()).toBeVisible();
            await expect(page.locator('[data-search-shortcuts]')).toBeHidden();
            await accessible();
            await page.screenshot({ path: `migration-source/preview-search-results-${width}.png` });
            await page.keyboard.press('Escape');
            await page.keyboard.press('Escape');
            await expect(page.locator('#search-panel')).not.toBeVisible();
            await page.locator('.display-launcher').click();
            await expect(page.locator('#display-panel')).toBeVisible();
            await oneLine(page.locator('#display-panel-title'));
            await accessible();
            await page.screenshot({ path: `migration-source/preview-display-${width}.png` });
            await page.keyboard.press('Escape');
            await expect(page.locator('.display-launcher')).toBeFocused();
        });
    }
});
