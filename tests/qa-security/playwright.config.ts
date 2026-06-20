import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'path';

dotenv.config({ path: path.resolve(__dirname, '.env.test') });

export default defineConfig({
  testDir: './specs',
  timeout: 30_000,
  retries: 1,
  workers: 1, // secuencial — los tests modifican estado de BD

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],

  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost/automatiza-tech',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    locale: 'es-CL',
    timezoneId: 'America/Santiago',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
