import { defineConfig, devices } from '@playwright/test';

/**
 * Browser smoke tests (accessibility with axe-core, privacy, keyboard, reflow).
 * They run against an already running application, e.g. the Docker setup:
 *   docker compose up -d && npm test
 * Override the target with BASE_URL=http://… npm test
 */
export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    reporter: [['list']],
    use: {
        baseURL: process.env.BASE_URL ?? 'http://localhost:8088',
        locale: 'de-DE',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
