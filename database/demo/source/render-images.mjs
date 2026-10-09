// Development helper: renders the artificial demo illustrations to JPEG.
// Usage (host, once): node database/demo/source/render-images.mjs
// Output: database/demo/media/*.jpg. No network access is used.
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { scenes } from './illustrations.js';

const out = join(dirname(fileURLToPath(import.meta.url)), '..', 'media');
mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
for (const [name, scene] of Object.entries(scenes)) {
    const page = await browser.newPage({ viewport: { width: scene.width, height: scene.height } });
    await page.route(/^https?:/, route => route.abort());
    await page.setContent(`<body style="margin:0"><svg xmlns="http://www.w3.org/2000/svg" width="${scene.width}" height="${scene.height}" viewBox="0 0 ${scene.width} ${scene.height}">${scene.draw(scene.width, scene.height)}</svg></body>`);
    await page.screenshot({ path: join(out, `${name}.jpg`), type: 'jpeg', quality: 78 });
    await page.close();
    console.log(`${name}.jpg ${scene.width}×${scene.height}`);
}
await browser.close();
