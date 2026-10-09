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
        // Scan the settled state: short UI transitions (e.g. the search overlay fade) must finish first.
        await page.waitForFunction(() => document.getAnimations().every(animation => animation.playState !== 'running'));
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
        expect(result.violations.map(v => `${v.id}: ${v.help}: ${v.nodes.map(n => n.html).join('; ')}`)).toEqual([]);
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
        await page.locator('summary', { hasText: 'Testinformation aufklappen' }).focus();
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
        // The editor offers the same save action in the sticky bar and beside the fields.
        await page.getByRole('button', { name: 'Speichern', exact: true }).first().click();
        await expect(page.getByText('Änderungen wurden gespeichert.')).toBeVisible();
        await expect(page.locator('#blocks_0_type')).toHaveValue('text');
        await context.close();
    });
    async function login(context, baseURL) {
        await context.addCookies([{ name: fixture.cookieName, value: fixture.cookie, url: baseURL, httpOnly: true }]);
    }
    async function reflow(page) {
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    }

    for (const width of [320, 390, 768, 1024, 1440]) {
        test(`public journeys reflow at ${width}px`, async ({ page, context }) => {
            await page.setViewportSize({ width, height: 960 });
            for (const path of ['/', '/buergerservice', '/buergerservice/a-z', '/browser-test-service', '/browser-test-article', '/browser-test-event', '/dokumente', '/browser-test-notice', '/bekanntmachungen?archiv=1', '/verzeichnisse', '/suche?q=Browser', '/kontakt', '/unbekannte-testseite']) {
                const response = await page.goto(path);
                expect(response.status()).toBe(path === '/unbekannte-testseite' ? 404 : 200);
                await reflow(page);
                await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            }
            expect(await context.cookies()).toHaveLength(1); // Only /kontakt starts the approved limited session.
        });
    }

    test('public templates, results and empty state pass axe and stay stateless', async ({ page, context }) => {
        for (const path of ['/', '/buergerservice', '/buergerservice/a-z', '/browser-test-article', '/browser-test-event', '/dokumente', '/browser-test-notice', '/bekanntmachungen?archiv=1', '/verzeichnisse', '/suche?q=Browser', '/suche?q=xyzabcnichtvorhanden', '/unbekannte-testseite']) {
            await page.goto(path); await accessible(page);
        }
        expect(await context.cookies()).toEqual([]);
    });

    test('desktop navigation and modal search manage keyboard focus', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 960 });
        await page.goto('/');
        const branch = page.locator('#site-navigation summary', { hasText: 'Browser Test Navigation' });
        await branch.focus(); await page.keyboard.press('Enter');
        await expect(page.getByRole('link', { name: 'Browser Test Service Link' })).toBeVisible();
        await page.keyboard.press('Escape'); await expect(branch).toBeFocused();
        const trigger = page.locator('[data-search-trigger]:visible');
        await trigger.focus(); await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        const input = page.locator('#overlay-search'); await expect(input).toBeFocused();
        await input.fill('Browser Test Service');
        await expect(page.locator('#overlay-search-suggestions [role=option]').first()).toBeVisible();
        await page.keyboard.press('ArrowDown');
        await expect(input).toHaveAttribute('aria-activedescendant', /suggestions-0$/);
        await expect(input).toBeFocused(); await accessible(page);
        await page.keyboard.press('Escape');
        await expect(input).not.toHaveAttribute('aria-activedescendant');
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).not.toBeVisible(); await expect(trigger).toBeFocused();
        await trigger.click(); await input.fill('Browser Test Service');
        await expect(page.locator('#overlay-search-suggestions [role=option]').first()).toBeVisible();
        await page.keyboard.press('ArrowDown'); await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/browser-test-service$/);
    });

    test('mobile menu and search are separate keyboard journeys', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 }); await page.goto('/');
        const menu = page.locator('#site-navigation > summary');
        await menu.focus(); await page.keyboard.press('Enter');
        const branch = page.locator('#site-navigation summary', { hasText: 'Browser Test Navigation' });
        await branch.focus(); await page.keyboard.press('Enter');
        await expect(page.getByRole('link', { name: 'Browser Test Service Link' })).toBeVisible();
        await page.keyboard.press('Escape'); await expect(branch).toBeFocused();
        await page.keyboard.press('Escape'); await expect(menu).toBeFocused();
        await expect(page.locator('#site-navigation')).not.toHaveAttribute('open');
        await page.locator('[data-search-trigger]:visible').click(); await accessible(page); await reflow(page);
    });

    test('normal search and navigation work without JavaScript', async ({ browser, baseURL }) => {
        const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const page = await context.newPage(); await page.goto('/');
        await page.locator('#site-navigation summary', { hasText: 'Browser Test Navigation' }).click();
        await expect(page.getByRole('link', { name: 'Browser Test Service Link' })).toBeVisible();
        await page.locator('[data-search-trigger]:visible').click();
        await page.locator('#results-search').fill('Browser Test Service');
        await page.locator('form:has(#results-search)').getByRole('button', { name: 'Suchen', exact: true }).click();
        await expect(page.getByRole('link', { name: 'Browser Test Service', exact: true }).last()).toBeVisible();
        expect(await context.cookies()).toEqual([]); await context.close();
    });

    test('CMS screens and quality workflow pass axe', async ({ page, context, baseURL }) => {
        await login(context, baseURL);
        for (const path of ['/verwaltung', '/verwaltung/inhalte', '/verwaltung/artikel', `/verwaltung/artikel/${fixture.articleId}`, `/verwaltung/buergerservice/${fixture.serviceId}`, `/verwaltung/veranstaltungen/${fixture.eventId}`, '/verwaltung/dokumente', '/verwaltung/personen', '/verwaltung/aemter', '/verwaltung/medien', `/verwaltung/medien/${fixture.mediaId}`, '/verwaltung/freigaben', `/verwaltung/freigaben/${fixture.proposalId}`, `/verwaltung/seiten/${fixture.pageId}/versionen`, `/verwaltung/seiten/${fixture.pageId}/versionen/${fixture.revisionNumber}`]) {
            const response = await page.goto(path); expect(response.status(), path).toBe(200);
            await page.locator('details.editor-section').evaluateAll(sections => sections.forEach(section => section.open = true));
            await accessible(page);
        }
        await page.getByText('Wiederherstellung bestätigen', { exact: true }).click();
        await expect(page.getByRole('button', { name: 'Diese Version wiederherstellen' })).toBeVisible();
    });

    for (const width of [320, 390, 768, 1024, 1440]) {
        test(`CMS workspace reflows at ${width}px`, async ({ page, context, baseURL }) => {
            await login(context, baseURL); await page.setViewportSize({ width, height: 960 });
            for (const path of ['/verwaltung', '/verwaltung/inhalte', `/verwaltung/artikel/${fixture.articleId}`, `/verwaltung/buergerservice/${fixture.serviceId}`, `/verwaltung/veranstaltungen/${fixture.eventId}`, `/verwaltung/seiten/${fixture.pageId}`, '/verwaltung/medien', `/verwaltung/freigaben/${fixture.proposalId}`, `/verwaltung/seiten/${fixture.pageId}/versionen`]) {
                await page.goto(path); await reflow(page);
            }
        });
    }

    test('block controls add, reorder and mark removal with keyboard and confirmation', async ({ page, context, baseURL }) => {
        await login(context, baseURL); await page.goto(`/verwaltung/seiten/${fixture.pageId}`);
        await page.locator('details.editor-section').filter({ has: page.locator('#blocks') }).evaluate(section => { section.open = true; });
        const editor = page.locator('[data-row-editor=blocks]');
        const first = editor.locator('[data-editor-row]').filter({ has: page.locator('#blocks_0_type') });
        const down = first.getByRole('button', { name: 'Nach unten', exact: true });
        await down.focus(); await page.keyboard.press('Enter'); await expect(down).toBeFocused();
        await expect(editor.getByRole('status')).toHaveText('Eintrag nach unten verschoben.');
        const before = await editor.locator('[data-editor-row]:visible').count();
        await editor.getByRole('button', { name: '+ Text', exact: true }).focus(); await page.keyboard.press('Enter');
        await expect(editor.locator('[data-editor-row]:visible')).toHaveCount(before + 1);
        page.once('dialog', dialog => dialog.accept());
        await first.getByRole('button', { name: 'Entfernen', exact: true }).click();
        await expect(first.locator('input[name$="[_remove]"]')).toBeChecked();
        await first.locator('input[name$="[_remove]"]').uncheck(); await expect(first).not.toHaveClass(/is-removed/);
        await accessible(page);
    });

    test('contact keeps valid details in memory after errors and clears the message', async ({ page }) => {
        await page.goto('/kontakt');
        await page.locator('#contact_name').fill('Synthetischer Kontakt');
        await page.locator('#contact_email').fill('browser@example.test');
        await page.locator('#contact_message').fill('');
        await page.getByRole('button', { name: 'Nachricht senden' }).click();
        await expect(page.locator('.error-summary')).toBeFocused();
        await expect(page.locator('#contact_name')).toHaveValue('Synthetischer Kontakt');
        await expect(page.locator('#contact_email')).toHaveValue('browser@example.test');
        await expect(page.locator('#contact_message')).toHaveValue('');
        expect(await page.evaluate(() => localStorage.length + sessionStorage.length)).toBe(0);
        await accessible(page);
    });

    test('forced colors and reduced motion retain usable focus and controls', async ({ page }) => {
        await page.emulateMedia({ forcedColors: 'active', reducedMotion: 'reduce' });
        await page.goto('/'); await page.keyboard.press('Tab');
        await expect(page.getByRole('link', { name: 'Zum Inhalt springen' })).toBeFocused();
        await page.locator('[data-search-trigger]:visible').click();
        await expect(page.locator('#overlay-search')).toBeFocused();
        await accessible(page);
    });

});
