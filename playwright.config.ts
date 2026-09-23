import { defineConfig, devices } from '@playwright/test';

const phpExecutable = process.env.PHP_BINARY ?? 'php';
const playwrightPort = process.env.PLAYWRIGHT_PORT ?? '8010';
const baseUrl = `http://127.0.0.1:${playwrightPort}`;

export default defineConfig({
    testDir: './tests/e2e',
    use: {
        baseURL: baseUrl,
        channel: process.env.PLAYWRIGHT_BROWSER_CHANNEL,
        ...devices['Desktop Chrome'],
    },
    webServer: {
        command: `"${phpExecutable}" artisan serve --host=127.0.0.1 --port=${playwrightPort}`,
        reuseExistingServer: !process.env.CI,
        url: `${baseUrl}/up`,
    },
});
