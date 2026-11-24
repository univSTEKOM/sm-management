import { defineConfig, devices } from '@playwright/test';

/**
 * E2E against the local dev server (compose service "app", port 8000).
 * Never point this at staging or production: globalSetup reseeds the database.
 */
export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/e2e/results',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'mobile-360',
            testMatch: /sp04-.*\.spec\.ts/,
            use: { ...devices['Pixel 5'], viewport: { width: 360, height: 800 } },
        },
        {
            // Admin work happens on laptops/desktops.
            name: 'desktop',
            testIgnore: /sp04-.*\.spec\.ts/,
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
