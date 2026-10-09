// Regenerate committed identity assets from the exact existing Wappen. No network.
// Run: node scripts/identity/generate.mjs (uses the existing Playwright dev dependency).
import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
const root = resolve(import.meta.dirname, '../..');
const out = resolve(root, 'public/identity');
mkdirSync(out, { recursive: true });
const source = readFileSync(resolve(root, 'resources/images/wappen-merching-prototype.png'));
const logo = `data:image/png;base64,${source.toString('base64')}`;
const font = `data:font/woff;base64,${readFileSync(resolve(root, 'resources/fonts/atkinson-hyperlegible-next-latin-variable.woff')).toString('base64')}`;
const browser = await chromium.launch();
try {
    const pngs = [];
    for (const [name, size, inset, opaque] of [
        ['favicon-16', 16, 0, false], ['favicon-32', 32, 0, false], ['favicon-48', 48, 0, false],
        ['apple-touch-icon', 180, 14, true], ['icon-192', 192, 16, true],
        ['icon-512', 512, 44, true], ['icon-maskable-512', 512, 100, true],
    ]) {
        const page = await browser.newPage({ viewport: { width: size, height: size }, deviceScaleFactor: 1 });
        await page.route(/^https?:/, route => route.abort());
        await page.setContent(`<body style="margin:0;background:${opaque ? '#fff' : 'transparent'};display:grid;place-items:center;height:100vh"><img src="${logo}" style="width:${size - 2 * inset}px;height:${size - 2 * inset}px;object-fit:contain"></body>`);
        await page.locator('img').evaluate(image => image.decode());
        const png = await page.screenshot({ path: `${out}/${name}.png`, omitBackground: !opaque });
        if (size <= 48) pngs.push({ size, png });
        await page.close();
    }
    // ICO with three PNG-backed entries; original pixels are only resampled.
    const header = Buffer.alloc(6 + 16 * pngs.length);
    header.writeUInt16LE(1, 2); header.writeUInt16LE(pngs.length, 4);
    let offset = header.length;
    pngs.forEach(({ size, png }, index) => {
        const entry = 6 + 16 * index;
        header[entry] = header[entry + 1] = size;
        header.writeUInt16LE(1, entry + 4); header.writeUInt16LE(32, entry + 6);
        header.writeUInt32LE(png.length, entry + 8); header.writeUInt32LE(offset, entry + 12);
        offset += png.length;
    });
    writeFileSync(resolve(root, 'public/favicon.ico'), Buffer.concat([header, ...pngs.map(item => item.png)]));
    writeFileSync(`${out}/favicon.svg`, `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><image href="${logo}" width="64" height="64" preserveAspectRatio="xMidYMid meet"/></svg>\n`);
    // Safari's pinned icon needs a single black vector layer. Trace the existing
    // dark pixels (blue eagle/black shield), retaining the gold areas as cutouts.
    // This is a deterministic monochrome conversion, not a redesigned emblem.
    const maskPage = await browser.newPage();
    await maskPage.route(/^https?:/, route => route.abort());
    const mask = await maskPage.evaluate(async source => {
        const image = new Image(); image.src = source; await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
        const context = canvas.getContext('2d'); context.drawImage(image, 0, 0);
        const { data } = context.getImageData(0, 0, canvas.width, canvas.height);
        const ink = (x, y) => {
            const index = 4 * (y * canvas.width + x);
            return data[index + 3] >= 128 && .2126 * data[index] + .7152 * data[index + 1] + .0722 * data[index + 2] < 140;
        };
        let path = '';
        for (let y = 0; y < canvas.height; y++) {
            for (let x = 0; x < canvas.width; x++) {
                if (!ink(x, y)) continue;
                const start = x;
                while (x + 1 < canvas.width && ink(x + 1, y)) x++;
                const length = x - start + 1;
                path += `M${start} ${y}h${length}v1h-${length}z`;
            }
        }
        return { path, width: canvas.width, height: canvas.height };
    }, logo);
    const scale = 16 / Math.max(mask.width, mask.height);
    writeFileSync(`${out}/safari-pinned-tab.svg`, `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="#000" transform="translate(${(16 - mask.width * scale) / 2} ${(16 - mask.height * scale) / 2}) scale(${scale})" d="${mask.path}"/></svg>\n`);
    await maskPage.close();
    const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
    await page.route(/^https?:/, route => route.abort());
    await page.setContent(`<style>@font-face{font-family:Atkinson;src:url('${font}')}*{box-sizing:border-box}body{margin:0;width:1200px;height:630px;background:#fff;color:#172234;font-family:Atkinson,sans-serif;border-top:12px solid #94c3f7}.card{height:100%;display:flex;align-items:center;gap:70px;padding:70px 90px}img{width:240px;height:300px;object-fit:contain}h1{font-size:66px;line-height:1.05;margin:0 0 22px;font-weight:750}p{font-size:28px;line-height:1.4;margin:0;color:#465266}.url{margin-top:45px;font-size:24px;color:#0b5cc2}</style><div class="card"><img src="${logo}"><div><h1>Gemeinde<br>Merching</h1><p>im Landkreis Aichach-Friedberg</p><p class="url">www.gemeinde-merching.de</p></div></div>`);
    await page.evaluate(() => document.fonts.ready);
    await page.locator('img').evaluate(image => image.decode());
    await page.screenshot({ path: `${out}/social-default.png` });
    await page.close();
} finally { await browser.close(); }
console.log('Identity icons and 1200×630 sharing image generated from the existing Wappen.');
