import { test, expect } from '@playwright/test';

for (const width of [1024, 1440]) {
    test(`header mouse targets bridge visual gaps at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        const items = page.locator('.main-nav__list > .main-nav__item');
        const pair = await items.evaluateAll(elements => elements.findIndex((element, index) => {
            const next = elements[index + 1];
            return element.hasAttribute('data-nav-item') && next?.hasAttribute('data-nav-item')
                && Math.abs(element.getBoundingClientRect().y - next.getBoundingClientRect().y) < 1;
        }));
        test.skip(pair < 0, 'Requires two adjacent managed submenu entries.');
        const first = items.nth(pair), second = items.nth(pair + 1);
        const a = await first.boundingBox(), b = await second.boundingBox();
        const y = a.y + a.height / 2;
        const gap = b.x - (a.x + a.width);

        // Every point in the visible space belongs to one of the two entries,
        // including just above the labels; geometry and visible gaps stay intact.
        const uncovered = await page.evaluate(({ left, right, ys }) => {
            const points = [];
            for (const y of ys) for (let x = left; x < right; x += .25) {
                if (!document.elementFromPoint(x, y)?.closest('[data-nav-item]')) points.push({ x, y });
            }
            return points;
        }, { left: a.x + a.width, right: b.x, ys: [y, a.y - 2] });
        expect(uncovered).toEqual([]);

        await page.mouse.move(a.x + a.width / 2, y);
        await expect(first.locator('details')).toHaveAttribute('open');
        if (gap > 0) {
            // Pause longer than the close delay inside each half of the gap.
            await page.mouse.move(a.x + a.width + gap / 4, y);
            await page.waitForTimeout(300);
            await expect(first.locator('details')).toHaveAttribute('open');
            await page.mouse.move(b.x - gap / 4, y);
        } else {
            await page.mouse.move(b.x + 1, y);
        }
        await expect(second.locator('details')).toHaveAttribute('open');
        await expect(first.locator('details')).not.toHaveAttribute('open');
        expect(await first.boundingBox()).toEqual(a);
        expect(await second.boundingBox()).toEqual(b);

        await page.mouse.move(2, 700);
        await expect(second.locator('details')).not.toHaveAttribute('open');
        const toggle = first.locator('summary');
        await toggle.focus(); await page.keyboard.press('Enter');
        await expect(first.locator('details')).toHaveAttribute('open');
        await page.keyboard.press('Escape');
        await expect(first.locator('details')).not.toHaveAttribute('open');
        await expect(toggle).toBeFocused();
    });
}
