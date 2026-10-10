import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('real local migration', () => {
    test.skip(process.env.REAL_MIGRATION !== '1', 'Run against the isolated migration database on port 8089.');
    const paths = ['/', '/aktuelles', '/buergerservice/a-z', '/rathaus-und-politik/verwaltung', '/rathaus-und-politik/gemeinderat', '/haushaltsplaene', '/bauen-und-wirtschaft', '/veranstaltungen', '/veranstaltungen/veroeffentlichter-kalender', '/standortfaktoren-leben-und-wohnen-in-merching', '/leben/kinder-und-jugend', '/gastronomiebetriebe', '/formulare', '/impressum', '/datenschutz', '/barrierefreiheit'];
    for (const width of [390, 1440]) {
        test(`real content, images, navigation, privacy and axe at ${width}px`, async ({ page, context, baseURL }) => {
            test.setTimeout(120000);
            await page.setViewportSize({ width, height: 960 });
            const foreign = [];
            page.on('request', r => { if (new URL(r.url()).origin !== new URL(baseURL).origin) foreign.push(r.url()); });
            for (const path of paths) {
                const response = await page.goto(path);
                expect(response.status(), path).toBe(200);
                expect(await page.locator('h1').count()).toBe(1);
                expect(await page.locator('body').innerText()).not.toMatch(/Musterinhalt|Max Mustermann|Erster Bürgermeister \(Demo\)/);
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), path).toBe(true);
                await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
                const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
                expect(scan.violations.map(v => `${path}: ${v.id}: ${v.nodes.map(n => n.html).join('; ')}`)).toEqual([]);
                const broken = await page.locator('img').evaluateAll(images => images.filter(i => i.complete && !i.naturalWidth).map(i => i.src));
                expect(broken, path).toEqual([]);
            }
            expect(await context.cookies()).toEqual([]);
            expect(foreign).toEqual([]);
            await page.goto('/aktuelles');
            const crest = page.locator('.news-preview-crest').first();
            await expect(crest).toBeVisible();
            await expect(page.locator('.article-card').filter({has: page.getByRole('heading', {name: 'Bodenrichtwerte ab 01.01.2026', exact: true})}).locator('.news-preview-crest')).toBeVisible();
            expect(await crest.evaluate(e => getComputedStyle(e).backgroundColor)).toBe('rgb(242, 241, 237)');
            const picture = crest.locator('img');
            await expect(picture).toHaveAttribute('alt', '');
            expect(await picture.evaluate(e => getComputedStyle(e).objectFit)).toBe('contain');
            await page.screenshot({ path: `migration-source/review-news-${width}.png`, fullPage: true });
            await crest.scrollIntoViewIfNeeded();
            await page.screenshot({ path: `migration-source/preview-crest-${width}.png` });
        });
    }
    test('real search, gallery and budget sources are discoverable and downloadable', async ({ page, request }) => {
        await page.goto('/suche?q=Personalausweis');
        await expect(page.locator('.result-list')).toContainText('Personalausweis');
        await page.goto('/haushaltsplaene');
        await expect(page.locator('.budget-list')).toContainText('Grundschulverband');
        const packageLink = page.locator('.budget-list h2 a').first();
        await packageLink.click();
        await expect(page.getByRole('heading', { name: 'Haushaltsplan und Anlagen herunterladen' })).toBeVisible();
        const source = page.locator('a[href*="/quelle/"]').first();
        const response = await request.get(await source.getAttribute('href'));
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toBe('application/pdf');
        await page.goto('/bildergalerie-merching');
        const galleryLink = page.locator('main a[href^="/galerien/"]').first();
        await galleryLink.click();
        await expect(page.locator('.gallery img').first()).toBeVisible();
    });
});
