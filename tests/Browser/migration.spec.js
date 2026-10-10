import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('real local migration', () => {
    test.skip(process.env.REAL_MIGRATION !== '1', 'Run against the isolated migration database on port 8089.');
    const paths = ['/', '/willkommen', '/aktuelles', '/aktuelles/vollsperrung-bahnuebergang-zwischen-brunnen-und-st-2052-im-zeitraum-vom-13-10-2026-15-10-2026', '/buergerservice', '/buergerservice/a-z', '/buergerservice/leistungen/personalausweis', '/rathaus-und-politik/verwaltung', '/rathaus-und-politik/gemeinderat', '/haushaltsplaene', '/haushaltsplaene/2026/gemeinde-merching', '/bauen-und-wirtschaft', '/veranstaltungen', '/veranstaltungen/offener-stammtisch-13', '/veranstaltungen/veroeffentlichter-kalender', '/standortfaktoren-leben-und-wohnen-in-merching', '/leben/kinder-und-jugend', '/gastronomiebetriebe', '/formulare', '/ortsrecht', '/gemeindekurier', '/buergerserviceportal', '/ausschreibungen', '/dokumente', '/bekanntmachungen', '/bekanntmachungen/bekanntmachung-der-gebuehrensatzung-zur-friedhofs-und-bestattungssatzung-ab-01-01-2027-10788', '/vereine', '/vereine?page=2', '/verzeichnisse/vereine/bauernverband-merching-1', '/verzeichnisse/gewerbe/buchanzow-kirsten-maklerin-fuer-immobilien-und-finanzdienstleistungen', '/bildergalerie-merching', '/galerien/naherholungsgebiet-mandichosee', '/impressum', '/datenschutz', '/barrierefreiheit'];
    for (const width of [390, 768, 1024, 1440]) {
        test(`real content, images, navigation, privacy and axe at ${width}px`, async ({ page, context, baseURL }) => {
            test.setTimeout(240000);
            await page.setViewportSize({ width, height: 960 });
            const foreign = [];
            page.on('request', r => { if (new URL(r.url()).origin !== new URL(baseURL).origin) foreign.push(r.url()); });
            for (const path of paths) {
                const response = await page.goto(path);
                expect(response.status(), path).toBe(200);
                expect(await page.locator('h1').count()).toBe(1);
                expect(await page.locator('body').innerText()).not.toMatch(/Musterinhalt|Max Mustermann|Erster Bürgermeister \(Demo\)/);
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), path).toBe(true);
                const incorrectTargets = await page.locator('a[href]').evaluateAll(anchors => anchors.filter(a => {
                    const url = new URL(a.href);
                    return ((['http:', 'https:'].includes(url.protocol) && url.origin !== location.origin)
                        || /\.(pdf|docx?|xlsx?|pptx?|odt|ods|odp|rtf|csv|zip)$/i.test(url.pathname)
                        || url.pathname.startsWith('/download/'))
                        && (a.target !== '_blank' || !a.rel.split(' ').includes('noopener') || !a.rel.split(' ').includes('noreferrer'));
                }).map(a => a.outerHTML));
                expect(incorrectTargets, path).toEqual([]);
                if (['/ortsrecht', '/gemeindekurier'].includes(path)) {
                    await expect(page.locator('.page-header__crest')).toBeVisible();
                    await expect(page.locator('.block-image')).toHaveCount(0);
                }
                if (path === '/ausschreibungen') {
                    await expect(page.locator('main')).toContainText('Derzeit sind keine Ausschreibungen veröffentlicht.');
                    await expect(page.locator('.block-navigation')).toHaveCount(0);
                }
                if (path === '/buergerserviceportal' && width > 768) {
                    const text = await page.locator('.editorial-block .prose').boundingBox();
                    const picture = await page.locator('.editorial-block .block-image').boundingBox();
                    expect(picture.x).toBeGreaterThan(text.x + text.width);
                    expect(Math.abs(picture.y - text.y)).toBeLessThan(160);
                }
                if (path === '/bekanntmachungen') {
                    await expect(page.getByRole('heading', {name: 'Bekanntmachungen finden'})).toBeVisible();
                    await expect(page.locator('.catalog-intro .download-list')).toHaveCount(0);
                    await expect(page.locator('.notice-files')).toHaveCount(16);
                }
                // Inspect images below the fold too; full-page capture alone
                // does not load native lazy images in galleries and directories.
                for (const picture of await page.locator('main img').all()) {
                    await picture.scrollIntoViewIfNeeded();
                    await picture.evaluate(image => image.decode().catch(() => {}));
                }
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
                const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
                expect(scan.violations.map(v => `${path}: ${v.id}: ${v.nodes.map(n => n.html).join('; ')}`)).toEqual([]);
                const broken = await page.locator('img').evaluateAll(images => images.filter(i => i.complete && !i.naturalWidth).map(i => i.src));
                expect(broken, path).toEqual([]);
                if (path === '/veranstaltungen/veroeffentlichter-kalender') {
                    const table = page.locator('.controlled-table');
                    expect(await table.locator('th').first().evaluate(e => e.getBoundingClientRect().width)).toBeGreaterThanOrEqual(120);
                    if (width < 1200) expect(await table.evaluate(e => e.scrollWidth > e.parentElement.clientWidth)).toBe(true);
                }
                if (path === '/vereine') {
                    await expect(page.locator('.directory-card')).toHaveCount(24);
                    await expect(page.locator('.directory-source-overview')).not.toHaveAttribute('open');
                    await expect(page.getByRole('link', { name: 'Nächste Seite' })).toBeVisible();
                }
                if (path === '/gastronomiebetriebe') await expect(page.locator('.record-panel')).toHaveCount(4);
                if (path === '/willkommen') expect(await page.locator('.block-image img').first().evaluate(e => e.getBoundingClientRect().width)).toBeLessThanOrEqual(90);
                if (path === '/formulare') {
                    const destinations = await page.locator('main .external-list a, main .download-list .download__title a').evaluateAll(links => links.map(a => a.href));
                    expect(destinations.length).toBe(new Set(destinations).size);
                }
                if (path === '/veranstaltungen' && width <= 768) await expect(page.locator('[data-calendar-disclosure]')).not.toHaveAttribute('open');
                await page.screenshot({ path: `migration-source/visual-review/${width}-${path === '/' ? 'home' : path.slice(1).replace(/[/?=]/g, '-')}.png`, fullPage: true });
            }
            expect(await context.cookies()).toEqual([]);
            expect(foreign).toEqual([]);
            await page.goto('/');
            await expect(page.locator('.home-contact__message svg')).toBeVisible();
            if (width > 768) expect(await page.locator('.greeting__media').evaluate(e => e.getBoundingClientRect().width)).toBeGreaterThan(160);
            await page.locator('.greeting').screenshot({ path: `migration-source/preview-greeting-${width}.png` });
            await page.locator('.site-footer').screenshot({ path: `migration-source/preview-footer-${width}.png` });
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
    test('month arrows update real events, preserve filters and work without JavaScript', async ({ page, browser, baseURL }) => {
        for (const width of [390, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 960 });
            await page.goto('/veranstaltungen?monat=2026-10');
            const before = await page.locator('[data-event-dates]').allTextContents();
            await page.locator('[data-month-nav=next]').click();
            await expect(page.locator('#event-month-title')).toHaveText('November 2026');
            await expect(page.locator('[data-month-nav=next]')).toBeFocused();
            await expect(page.locator('.filter-bar input[name=monat]')).toHaveValue('2026-11');
            expect(await page.locator('[data-event-dates]').allTextContents()).not.toEqual(before);
            expect(await page.locator('[data-event-dates]').evaluateAll(items => items.every(e => e.dataset.eventDates.split(' ').every(date => date.startsWith('2026-11-'))))).toBe(true);
            const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
            expect(scan.violations).toEqual([]);
            await page.locator('[data-month-nav=previous]').click();
            await expect(page.locator('#event-month-title')).toHaveText('Oktober 2026');
        }
        await page.goto('/veranstaltungen?monat=2026-11&q=Stammtisch');
        await expect(page.locator('#event-month-title')).toHaveText('November 2026');
        await page.getByRole('link', {name: 'Filter zurücksetzen'}).click();
        await expect(page.locator('#event-month-title')).toHaveText('November 2026');
        await expect(page.locator('[data-event-dates]')).toHaveCount(17);
        const context = await browser.newContext({ javaScriptEnabled: false, baseURL });
        const native = await context.newPage();
        await native.goto('/veranstaltungen?monat=2026-10');
        await native.locator('[data-month-nav=next]').click();
        await expect(native.locator('#event-month-title')).toHaveText('November 2026');
        await expect(native.getByRole('heading', {name: 'Kommunalpolitischer Stammtisch'})).toBeVisible();
        await context.close();
    });
    test('real department contact stays above long service lists on mobile and tablet', async ({ page }) => {
        for (const width of [390, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 960 });
            await page.goto('/verzeichnisse/stellen/einwohnermeldeamt-6');
            await expect(page.getByRole('link', { name: 'Telefon: 08233/7441-16' })).toBeVisible();
            if (width <= 768) {
                const contact = await page.locator('.content-aside').boundingBox();
                const services = await page.locator('#services').boundingBox();
                expect(contact.y + contact.height).toBeLessThan(services.y);
            }
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
            const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
            expect(scan.violations).toEqual([]);
            await page.screenshot({ path: `migration-source/visual-review/${width}-verzeichnisse-stellen-einwohnermeldeamt-6.png`, fullPage: true });
        }
    });
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
