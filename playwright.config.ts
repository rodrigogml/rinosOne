import { defineConfig, devices } from '@playwright/test';

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
        command: `py -3 -m http.server ${playwrightPort} --directory public`,
        reuseExistingServer: !process.env.CI,
        url: `${baseUrl}/build/manifest.json`,
    },
});
