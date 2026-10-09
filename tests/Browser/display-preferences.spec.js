import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const trigger = page => page.locator('.display-launcher');
const dialog = page => page.getByRole('dialog', { name: 'Darstellung & Barrierefreiheit' });
async function open(page, path = '/') {
    await page.goto(path);
    await trigger(page).click();
    await expect(dialog(page)).toBeVisible();
}
async function accessible(page, selector) {
    await page.waitForFunction(() => document.getAnimations().every(animation => animation.playState !== 'running'));
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice']);
    if (selector) builder = builder.include(selector);
    const result = await builder.analyze();
    expect(result.violations.map(v => `${v.id}: ${v.help}: ${v.nodes.map(n => n.html).join('; ')}`)).toEqual([]);
}

test('launcher stays fixed at the lower right and opens a compact anchored overlay', async ({ page }) => {
    for (const width of [390, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await expect(trigger(page)).toBeInViewport();
        const before = await trigger(page).boundingBox();
        await page.evaluate(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' }));
        await expect(trigger(page)).toBeInViewport();
        expect(await trigger(page).boundingBox()).toEqual(before);
        await trigger(page).click();
        await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
        const box = await dialog(page).boundingBox();
        expect(box.width).toBeLessThan(width);
        expect(box.height).toBeLessThan(650);
        expect(box.x + box.width).toBeLessThanOrEqual(width - 15);
        expect(box.y + box.height).toBeLessThan(before.y);
        expect(await dialog(page).evaluate(el => getComputedStyle(el, '::backdrop').backgroundColor)).toBe('rgba(0, 0, 0, 0)');
        await expect(trigger(page)).toHaveAttribute('aria-expanded', 'true');
        await page.mouse.click(5, 5);
        await expect(dialog(page)).not.toBeVisible();
        await expect(trigger(page)).toBeFocused();
        await expect(trigger(page)).toHaveAttribute('aria-expanded', 'false');
    }
});

test('display sheet operates with keyboard, traps modal focus and returns it on Escape', async ({ page }) => {
    await page.goto('/');
    await trigger(page).focus();
    await page.keyboard.press('Enter');
    const close = page.getByRole('button', { name: 'Darstellung schließen' });
    await expect(close).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    expect(await dialog(page).evaluate(el => el.contains(document.activeElement))).toBe(true);
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();
    const dark = page.getByLabel('Dunkler Modus', { exact: true });
    await dark.focus();
    await page.keyboard.press('Space');
    await expect(dark).toBeChecked();
    await page.keyboard.press('Escape');
    await expect(dialog(page)).not.toBeVisible();
    await expect(trigger(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await close.click();
    await expect(trigger(page)).toBeFocused();
});

for (const width of [320, 390, 768, 1024, 1440]) {
    test(`display sheet and 150% text reflow at ${width}px with large control targets`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await open(page);
        await accessible(page, '#display-panel');
        for (let step = 0; step < 4; step++) await page.getByRole('button', { name: 'Schrift vergrößern' }).click();
        await expect(page.locator('[data-font-output]')).toHaveText('150 %');
        await expect(page.getByRole('button', { name: 'Schrift vergrößern' })).toBeDisabled();
        expect(await dialog(page).evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
        const targets = await dialog(page).locator('button, .display-toggle, select').evaluateAll(elements => elements.map(el => {
            const rect = el.getBoundingClientRect(); return { width: rect.width, height: rect.height };
        }));
        for (const rect of targets) { expect(rect.width).toBeGreaterThanOrEqual(44); expect(rect.height).toBeGreaterThanOrEqual(44); }
        await page.keyboard.press('Escape');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        await accessible(page);
        await trigger(page).click();
        await page.getByRole('button', { name: 'Standard', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Schrift verkleinern' })).toBeDisabled();
    });
}

test('light, dark, warm, contrast and colour helpers pass axe in combination', async ({ page }) => {
    test.setTimeout(240_000);
    for (const path of ['/', '/kontakt', '/veranstaltungen']) {
        await open(page, path);
        for (const dark of [false, true]) {
            await page.getByLabel('Dunkler Modus', { exact: true }).setChecked(dark);
            for (const warm of [false, true]) {
                await page.getByLabel('Blaufilter', { exact: true }).setChecked(warm);
                for (const contrast of [false, true]) {
                    await page.getByLabel('Kontrast erhöhen', { exact: true }).setChecked(contrast);
                    for (const vision of ['default', 'red-green', 'blue-yellow']) {
                        await page.getByLabel('Farbwahrnehmung / Farbschwäche', { exact: true }).selectOption(vision);
                        await accessible(page, '#display-panel');
                        await page.keyboard.press('Escape');
                        await accessible(page);
                        await trigger(page).click();
                    }
                }
            }
        }
    }
});

test('manual reduced motion covers existing interactions; OS setting survives reset', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await open(page);
    await page.getByLabel('Bewegungen reduzieren', { exact: true }).check();
    await page.keyboard.press('Escape');
    await page.locator('[data-search-trigger]:visible').click();
    await expect(page.locator('.search-panel')).toBeVisible();
    expect(await page.locator('.search-panel').evaluate(el => getComputedStyle(el).animationName)).toBe('none');
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior)).toBe('auto');
    await page.keyboard.press('Escape');
    await expect(page.locator('.search-panel')).not.toBeVisible();
    await trigger(page).click();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(page.locator('[data-motion-hint]')).toContainText('bereits');
    await page.getByRole('button', { name: 'Alles zurücksetzen' }).click();
    await page.keyboard.press('Escape');
    await page.locator('[data-search-trigger]:visible').click();
    expect(await page.locator('.search-panel').evaluate(el => getComputedStyle(el).animationName)).toBe('none');
    await page.keyboard.press('Escape');
    await expect(page.locator('.search-panel')).not.toBeVisible();
});

test('forced colours remain authoritative even with every colour helper enabled', async ({ page }) => {
    await open(page);
    for (const name of ['Kontrast erhöhen', 'Dunkler Modus', 'Blaufilter']) await page.getByLabel(name, { exact: true }).check();
    await page.getByLabel('Farbwahrnehmung / Farbschwäche', { exact: true }).selectOption('red-green');
    await page.emulateMedia({ forcedColors: 'active' });
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme)).not.toBe('dark');
    expect(await dialog(page).evaluate(el => getComputedStyle(el).forcedColorAdjust)).toBe('auto');
    await accessible(page, '#display-panel');
});

test('hiding images preserves descriptions, linked images, captions, essential images and resets', async ({ page }) => {
    await open(page);
    await page.evaluate(() => {
        const main = document.querySelector('main');
        const figure = document.createElement('figure');
        figure.id = 'image-helper-test';
        const image = document.createElement('img'); image.src = document.querySelector('.brand img').src;
        image.alt = '<Testbild> mit Beschreibung'; image.width = 100; image.height = 100;
        const link = document.createElement('a'); link.href = '/'; link.append(image);
        const caption = document.createElement('figcaption'); caption.textContent = 'Erhaltener Bildkontext';
        const essential = image.cloneNode(); essential.alt = 'Wichtige Information'; essential.setAttribute('data-display-essential', '');
        const decorative = image.cloneNode(); decorative.alt = '';
        figure.append(link, caption, essential, decorative); main.append(figure);
        // Exercise the actual site's hero, cropped portrait/card, gallery and lead-image rules.
        for (const [container, imageClass] of [
            ['home-hero__media', 'home-hero__image'], ['greeting__media', 'greeting__image'],
            ['article-card__media', ''], ['news-item__media', ''], ['lead-image', ''], ['gallery', ''],
        ]) {
            const sample = document.createElement('figure'); sample.className = container;
            const copy = image.cloneNode(); copy.className = imageClass;
            sample.append(copy); main.append(sample);
        }
    });
    const before = await page.locator('main img').evaluateAll(images => images.map(image => {
        const { x, y, width, height } = image.getBoundingClientRect(); return { x, y, width, height };
    }));
    await page.getByLabel('Bilder ausblenden', { exact: true }).check();
    const after = await page.locator('main img').evaluateAll(images => images.map(image => {
        const { x, y, width, height } = image.getBoundingClientRect(); return { x, y, width, height };
    }));
    expect(after).toEqual(before);
    await page.keyboard.press('Escape');
    await expect(page.locator('#image-helper-test img').first()).not.toBeVisible();
    await expect(page.getByRole('link', { name: 'Bild: <Testbild> mit Beschreibung' })).toBeVisible();
    const frame = await page.locator('#image-helper-test .display-image-frame').first().boundingBox();
    const description = await page.locator('#image-helper-test .display-image-description').boundingBox();
    expect(description).toEqual(frame);
    await expect(page.getByText('Erhaltener Bildkontext')).toBeVisible();
    await expect(page.getByRole('img', { name: 'Wichtige Information' })).toBeVisible();
    await expect(page.locator('.site-header .brand img')).toBeVisible();
    expect(await page.locator('#image-helper-test .display-image-description').count()).toBe(1);
    await page.evaluate(() => {
        const image = document.createElement('img'); image.alt = 'Später eingefügtes Bild';
        image.src = document.querySelector('.brand img').src; document.querySelector('#image-helper-test').append(image);
    });
    await expect(page.getByText('Bild: Später eingefügtes Bild')).toBeVisible();
    await trigger(page).click();
    await page.getByRole('button', { name: 'Alles zurücksetzen' }).click();
    await page.keyboard.press('Escape');
    await expect(page.locator('#image-helper-test img').first()).toBeVisible();
    expect(await page.locator('.display-image-description').count()).toBe(0);
    expect(await page.locator('.display-image-frame').count()).toBe(0);
    // A narrow crop still reserves its space; long descriptions can be scrolled by keyboard.
    await page.setViewportSize({ width: 320, height: 900 });
    await page.evaluate(() => {
        const image = document.querySelector('#image-helper-test img');
        image.alt = 'Lange Bildbeschreibung. '.repeat(200);
    });
    const narrowBefore = await page.locator('main img').evaluateAll(images => images.map(image => {
        const { x, y, width, height } = image.getBoundingClientRect(); return { x, y, width, height };
    }));
    await trigger(page).click();
    await page.getByLabel('Bilder ausblenden', { exact: true }).check();
    const narrowAfter = await page.locator('main img').evaluateAll(images => images.map(image => {
        const { x, y, width, height } = image.getBoundingClientRect(); return { x, y, width, height };
    }));
    expect(narrowAfter).toEqual(narrowBefore);
    await page.keyboard.press('Escape');
    const longDescription = page.locator('#image-helper-test .display-image-description').first();
    await expect(longDescription).toHaveAttribute('tabindex', '0');
    await longDescription.focus();
    await page.keyboard.press('ArrowDown');
    await expect.poll(() => longDescription.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
    await accessible(page);
});

test('preferences persist across pages and tabs, sync/reset together, make no external requests and do not affect admin', async ({ page, context, baseURL }) => {
    const external = [], csp = [];
    page.on('request', req => { if (!req.url().startsWith('data:') && new URL(req.url()).origin !== new URL(baseURL).origin) external.push(req.url()); });
    page.on('console', msg => { if (msg.type() === 'error' && /Content Security Policy/i.test(msg.text())) csp.push(msg.text()); });
    await open(page);
    expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
    const other = await context.newPage();
    await open(other, '/veranstaltungen');
    for (const label of ['Kontrast erhöhen', 'Dunkler Modus', 'Bewegungen reduzieren', 'Blaufilter', 'Bilder ausblenden']) await page.getByLabel(label, { exact: true }).check();
    await page.getByLabel('Farbwahrnehmung / Farbschwäche', { exact: true }).selectOption('blue-yellow');
    await page.getByRole('button', { name: 'Schrift vergrößern' }).click();
    expect(await context.cookies()).toEqual([]);
    expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([1, 0]);
    const saved = await page.evaluate(() => JSON.parse(localStorage.getItem('merching.display-preferences.v1')));
    expect(saved).toEqual({ font: 1, vision: 'blue-yellow', contrast: true, dark: true, motion: true, warm: true, images: true });
    for (const box of await dialog(other).getByRole('checkbox').all()) await expect(box).toBeChecked();
    await expect(other.locator('[data-font-output]')).toHaveText('112,5 %');
    await expect(other.getByLabel('Farbwahrnehmung / Farbschwäche', { exact: true })).toHaveValue('blue-yellow');
    await page.keyboard.press('Escape');
    await page.goto('/aktuelles');
    await trigger(page).click();
    for (const box of await dialog(page).getByRole('checkbox').all()) await expect(box).toBeChecked();
    await expect(page.locator('[data-font-output]')).toHaveText('112,5 %');
    await page.getByRole('button', { name: 'Alles zurücksetzen' }).click();
    for (const box of await dialog(page).getByRole('checkbox').all()) await expect(box).not.toBeChecked();
    await expect(page.getByLabel('Farbwahrnehmung / Farbschwäche', { exact: true })).toHaveValue('default');
    await expect(page.locator('[data-font-output]')).toHaveText('100 %');
    expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
    for (const box of await dialog(other).getByRole('checkbox').all()) await expect(box).not.toBeChecked();
    await expect(other.locator('[data-font-output]')).toHaveText('100 %');
    await page.getByLabel('Dunkler Modus', { exact: true }).check();
    await page.reload();
    await trigger(page).click();
    await expect(page.getByLabel('Dunkler Modus', { exact: true })).toBeChecked();
    await other.getByRole('button', { name: 'Alles zurücksetzen' }).click();
    await expect(page.getByLabel('Dunkler Modus', { exact: true })).not.toBeChecked();
    await page.goto('/verwaltung/login');
    await expect(page.locator('[data-display-trigger]')).toHaveCount(0);
    expect(external).toEqual([]); expect(csp).toEqual([]);
});

test('invalid stored preferences are ignored without breaking the panel', async ({ page }) => {
    await page.goto('/');
    for (const invalid of ['not-json', '{}', '{"font":999}', '{"font":0,"vision":"default","contrast":"true"}']) {
        await page.evaluate(value => localStorage.setItem('merching.display-preferences.v1', value), invalid);
        await page.reload();
        await trigger(page).click();
        await expect(page.locator('[data-font-output]')).toHaveText('100 %');
        for (const box of await dialog(page).getByRole('checkbox').all()) await expect(box).not.toBeChecked();
        await page.keyboard.press('Escape');
    }
    await trigger(page).click();
    await page.getByRole('button', { name: 'Alles zurücksetzen' }).click();
    expect(await page.evaluate(() => localStorage.length)).toBe(0);
});

test('blocked storage falls back to functional page-local settings with an explanation', async ({ browser, baseURL }) => {
    for (const failure of ['access', 'write']) {
        const context = await browser.newContext({ baseURL });
        await context.addInitScript(failure => {
            if (failure === 'access') Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Denied', 'SecurityError'); } });
            else Storage.prototype.setItem = () => { throw new DOMException('Full', 'QuotaExceededError'); };
        }, failure);
        const page = await context.newPage();
        await open(page);
        await page.getByLabel('Dunkler Modus', { exact: true }).check();
        await expect(page.locator('html')).toHaveClass(/display-dark/);
        await expect(page.locator('[data-storage-hint]')).toContainText('keine Speicherung');
        await page.getByRole('button', { name: 'Alles zurücksetzen' }).click();
        await expect(page.locator('html')).not.toHaveClass(/display-dark/);
        await page.keyboard.press('Escape');
        await expect(trigger(page)).toBeFocused();
        await context.close();
    }
});

test('without JavaScript the site and native search/navigation remain usable', async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    const page = await context.newPage();
    await page.goto('/');
    await expect(page.locator('[data-display-trigger]:visible')).toHaveCount(0);
    await expect(page.getByText(/Für die Darstellungshilfen benötigen Sie JavaScript/)).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.locator('.site-navigation > summary').focus();
    await page.keyboard.press('Enter'); // Native menu closes, then reopens.
    await page.keyboard.press('Enter');
    await expect(page.locator('.site-navigation__panel')).toBeVisible();
    await page.locator('#home-search').fill('Rathaus');
    await page.locator('#home-search').press('Enter');
    await expect(page).toHaveURL(/\/suche\?q=Rathaus/);
    expect(await context.cookies()).toEqual([]);
    await context.close();
});

async function speechMock(page, type = 'local') {
    await page.addInitScript(({ type }) => {
        window.__read = { utterances: [], canceled: 0, listeners: {} };
        if (type === 'unsupported') { Object.defineProperty(window, 'speechSynthesis', { value: undefined }); return; }
        const voices = [{ name: 'Online', lang: 'de-DE', localService: false }];
        if (type === 'local') voices.push({ name: 'Lokal', lang: 'de-DE', localService: true });
        if (type === 'delayed') window.__read.voices = [];
        else window.__read.voices = voices;
        Object.defineProperty(window, 'SpeechSynthesisUtterance', { value: class { constructor(text) { this.text = text; } } });
        Object.defineProperty(window, 'speechSynthesis', { value: {
            getVoices: () => window.__read.voices,
            addEventListener: (name, fn) => { window.__read.listeners[name] = fn; },
            speak: utterance => { window.__read.utterances.push(utterance); if (type === 'error') utterance.onerror({ error: 'synthesis-failed' }); },
            cancel: () => { window.__read.canceled++; },
        } });
        if (type === 'error') window.__read.voices.push({ name: 'Lokal', lang: 'de-DE', localService: true });
    }, { type });
}

for (const type of ['unsupported', 'remote', 'delayed']) {
    test(`read-aloud fails gracefully with ${type} voices, without a remote fallback`, async ({ page }) => {
        await speechMock(page, type);
        await open(page);
        await expect(page.getByRole('button', { name: 'Vorlesen starten' })).toBeDisabled();
        await expect(page.locator('[data-read-hint]')).toContainText(type === 'unsupported' ? 'unterstützt' : 'Keine lokale deutsche Stimme');
        expect(await page.evaluate(() => window.__read.utterances.length)).toBe(0);
        if (type === 'delayed') {
            await page.evaluate(() => { window.__read.voices.push({ name: 'Lokal', lang: 'de-DE', localService: true }); window.__read.listeners.voiceschanged(); });
            await expect(page.getByRole('button', { name: 'Vorlesen starten' })).toBeEnabled();
        }
    });
}

test('read-aloud uses a local voice, excludes form values, chunks text and can be stopped/reset', async ({ page }) => {
    await speechMock(page);
    await open(page, '/kontakt');
    await page.evaluate(() => {
        document.querySelector('main').insertAdjacentHTML('afterbegin', '<p>Öffentlicher Vorlesetext. '.repeat(40) + '</p>');
        document.querySelector('main').insertAdjacentHTML('afterbegin', '<details><summary>Sichtbare Zusammenfassung</summary><p>Geschlossene Details bleiben geheim.</p></details>');
        document.querySelector('textarea').value = 'Private Nachricht darf nicht vorgelesen werden';
    });
    await page.getByRole('button', { name: 'Vorlesen starten' }).click();
    await expect(dialog(page)).not.toBeVisible();
    await expect(trigger(page)).toBeFocused();
    const utterance = await page.evaluate(() => ({ text: window.__read.utterances[0].text, voice: window.__read.utterances[0].voice }));
    expect(utterance.voice.localService).toBe(true);
    expect(utterance.text.length).toBeLessThanOrEqual(220);
    expect(utterance.text).not.toContain('Private Nachricht');
    expect(utterance.text).not.toContain('Geschlossene Details');
    expect(utterance.text).toContain('Sichtbare Zusammenfassung');
    await page.evaluate(() => window.__read.utterances[0].onend());
    expect(await page.evaluate(() => window.__read.utterances.length)).toBe(2);
    await page.locator('.site-footer [data-read-stop]').click();
    await expect(trigger(page)).toBeFocused();
    await page.evaluate(() => window.__read.utterances[1].onend());
    expect(await page.evaluate(() => window.__read.utterances.length)).toBe(2); // Stale completion cannot restart speech.
    expect(await page.evaluate(() => window.__read.canceled)).toBe(1);
    await trigger(page).click();
    await page.getByRole('button', { name: 'Vorlesen starten' }).click();
    await trigger(page).click();
    await page.getByRole('button', { name: 'Alles zurücksetzen' }).click();
    expect(await page.evaluate(() => window.__read.canceled)).toBe(2);
});

test('read-aloud reports synthesis errors and stops on page departure', async ({ page }) => {
    await speechMock(page, 'error');
    await open(page);
    await page.getByRole('button', { name: 'Vorlesen starten' }).click();
    await trigger(page).click();
    await expect(page.locator('[data-read-status]')).toContainText('gerade nicht möglich');
    await expect(page.getByRole('button', { name: 'Vorlesen starten' })).toBeEnabled();
    await page.evaluate(() => { speechSynthesis.speak = utterance => window.__read.utterances.push(utterance); });
    await page.getByRole('button', { name: 'Vorlesen starten' }).click();
    const before = await page.evaluate(() => window.__read.canceled);
    await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
    expect(await page.evaluate(() => window.__read.canceled)).toBe(before + 1);
});
