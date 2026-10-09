import { test, expect } from '@playwright/test';

const origin = 'https://www.gemeinde-merching.de';
const meta = (page, name) => page.locator(`head meta[name="${name}"], head meta[property="${name}"]`).getAttribute('content');

test('public metadata uses the production identity, with no foreign requests or storage writes', async ({ page, context, baseURL }) => {
    const foreign = [], csp = [];
    page.on('request', request => {
        if (!request.url().startsWith('data:') && new URL(request.url()).origin !== new URL(baseURL).origin) foreign.push(request.url());
    });
    page.on('console', message => { if (message.type() === 'error' && /Content Security Policy/i.test(message.text())) csp.push(message.text()); });
    for (const path of ['/', '/buergerservice', '/aktuelles', '/veranstaltungen', '/dokumente', '/verzeichnisse']) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await expect(page.locator('head link[rel="canonical"]')).toHaveCount(1);
        expect(await page.locator('head link[rel="canonical"]').getAttribute('href')).toBe(origin + path);
        expect(await meta(page, 'og:url')).toBe(origin + path);
        expect(await meta(page, 'og:title')).toBe(await page.title());
        expect(await meta(page, 'twitter:title')).toBe(await page.title());
        expect(await meta(page, 'og:description')).toBe(await meta(page, 'description'));
        expect(await meta(page, 'description')).toBeTruthy();
        expect(await meta(page, 'og:image')).toBe(origin + '/identity/social-default.png');
        expect(await meta(page, 'twitter:image')).toBe(await meta(page, 'og:image'));
        expect(await meta(page, 'og:locale')).toBe('de_DE');
        expect(await meta(page, 'robots')).toBe('noindex, nofollow');
        expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
        expect(await context.cookies()).toEqual([]);
    }
    expect(foreign).toEqual([]);
    expect(csp).toEqual([]);
});

test('self-hosted icons, manifest and default sharing image are served with valid types and sizes', async ({ page, request }) => {
    await page.goto('/');
    for (const asset of ['/favicon.ico', '/identity/favicon.svg', '/identity/favicon-32.png', '/identity/apple-touch-icon.png', '/identity/safari-pinned-tab.svg']) {
        const response = await request.get(asset);
        expect(response.status()).toBe(200);
        expect(response.headers()['set-cookie']).toBeUndefined();
        expect(response.headers()['content-type']).toMatch(/image\//);
    }
    const response = await request.get('/site.webmanifest');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/manifest+json');
    const manifest = await response.json();
    expect(manifest.name).toBe('Gemeinde Merching');
    expect(manifest.start_url).toBe('/');
    expect(manifest.icons.some(icon => icon.purpose === 'maskable')).toBe(true);
    for (const icon of manifest.icons) {
        expect(icon.src.startsWith('/identity/')).toBe(true);
        expect((await request.get(icon.src)).status()).toBe(200);
    }
    const dimensions = await page.evaluate(async () => {
        const image = await createImageBitmap(await (await fetch('/identity/social-default.png')).blob());
        const result = [image.width, image.height]; image.close(); return result;
    });
    expect(dimensions).toEqual([1200, 630]);
    await expect(page.locator('head link[rel="manifest"]')).toHaveAttribute('href', '/site.webmanifest');
    await expect(page.locator('head link[rel="apple-touch-icon"]')).toHaveAttribute('sizes', '180x180');
    await expect(page.locator('head link[rel="mask-icon"]')).toHaveAttribute('href', '/identity/safari-pinned-tab.svg');
});

test('share metadata and municipality microdata are server-rendered without JavaScript', async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false });
    try {
        const page = await context.newPage();
        await page.goto('/');
        expect(await meta(page, 'og:image')).toBe(origin + '/identity/social-default.png');
        await expect(page.locator('[itemtype="https://schema.org/GovernmentOrganization"]')).toHaveCount(1);
        await expect(page.locator('[itemtype="https://schema.org/WebSite"]')).toHaveCount(1);
        expect(await page.locator('[itemprop="logo"]').getAttribute('href')).toBe(origin + '/identity/icon-512.png');
        expect(await context.cookies()).toEqual([]);
        await page.goto('/verwaltung/login');
        expect(await meta(page, 'robots')).toBe('noindex, nofollow');
        await expect(page.locator('head meta[property^="og:"], head link[rel="canonical"], [itemscope]')).toHaveCount(0);
    } finally { await context.close(); }
});
