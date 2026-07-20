import { defineConfig, devices } from '@playwright/test';

/**
 * Testele e2e rulează împotriva unei stive deja pornite (backend + cele două
 * aplicații) cu datele demo încărcate (`app:demo:seed`). Vezi README.md.
 *
 * URL-urile pot fi suprascrise prin variabile de mediu:
 *   CLIENT_URL (implicit http://localhost:3000), ADMIN_URL (implicit http://localhost:3001)
 *
 * Pe mașini cu un Chromium preinstalat (altă versiune decât cea cerută de
 * Playwright), setați CHROMIUM_PATH către executabil — se folosește acela,
 * fără descărcare. Fără variabilă, Playwright își folosește browserul propriu.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    headless: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    ...devices['Desktop Chrome'],
    ...(process.env.CHROMIUM_PATH
      ? { launchOptions: { executablePath: process.env.CHROMIUM_PATH, args: ['--no-sandbox'] } }
      : {}),
  },
});
