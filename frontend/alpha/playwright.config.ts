import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'on-first-retry',
    headless: true,
  },
  projects: [
    // 高速版：フロントエンド変更時に常に実行する重要テスト（5分以内）
    {
      name: 'fast',
      testMatch: [
        '**/core-navigation.spec.ts',
        '**/critical-ux.spec.ts',
        '**/detail-page-buttons.spec.ts',
        '**/layout-responsiveness.spec.ts',
        '**/page-rerender-on-reclick.spec.ts',
      ],
      use: { ...devices['Desktop Chrome'], headless: true },
    },
    // 網羅版：包括的なテスト（すべてのテスト）
    {
      name: 'full',
      testMatch: '**/*.spec.ts',
      use: { ...devices['Desktop Chrome'], headless: true },
    },
  ],
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: !process.env.CI,
  },
})
