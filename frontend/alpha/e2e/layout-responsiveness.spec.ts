import { test, expect } from '@playwright/test'

/**
 * レイアウト・レスポンシブ対応のE2Eテスト
 *
 * 各画面サイズで要素が飛び出していないことを確認
 */

const viewports = [
  { width: 1920, height: 1080, name: 'Desktop' },
  { width: 1400, height: 900, name: 'Laptop' },
  { width: 1024, height: 768, name: 'Tablet Large' },
  { width: 900, height: 1024, name: 'Tablet' },
  { width: 768, height: 1024, name: 'Tablet Small' },
  { width: 375, height: 812, name: 'Mobile' },
]

test.describe('レイアウト・レスポンシブ対応', () => {
  for (const viewport of viewports) {
    test(`${viewport.name} (${viewport.width}x${viewport.height}) で要素が飛び出していない`, async ({ page }) => {
      await page.setViewportSize(viewport)
      await page.goto('/')
      await page.waitForSelector('input[placeholder="キーワードを入力..."]', { timeout: 10000 })

      // 検索を実行
      await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
      await page.waitForTimeout(2000)

      // メインコンテナのオーバーフローチェック
      const mainOverflow = await page.evaluate(() => {
        const main = document.querySelector('main')
        if (!main) return { hasIssue: false }

        const rect = main.getBoundingClientRect()
        const viewportWidth = window.innerWidth

        return {
          hasIssue: rect.right > viewportWidth || rect.left < 0,
          right: rect.right,
          left: rect.left,
          viewportWidth
        }
      })

      expect(mainOverflow.hasIssue).toBe(false)

      // 検索結果カードのオーバーフローチェック
      const cardsOverflow = await page.evaluate(() => {
        const cards = document.querySelectorAll('.grid > div')
        const viewportWidth = window.innerWidth
        const issues = []

        cards.forEach((card, i) => {
          const rect = card.getBoundingClientRect()
          if (rect.right > viewportWidth) {
            issues.push({ index: i, side: 'right', value: rect.right })
          }
          if (rect.left < 0) {
            issues.push({ index: i, side: 'left', value: rect.left })
          }
        })

        return issues
      })

      expect(cardsOverflow).toHaveLength(0)

      // マイリストページも確認
      if (viewport.width >= 768) {
        await page.click('aside a:has-text("マイリスト")')
      } else {
        await page.click('nav.fixed.bottom-0 a:has-text("マイリスト")')
      }
      await page.waitForURL('**/mylist')
      await page.waitForTimeout(500)

      const mylistOverflow = await page.evaluate(() => {
        const main = document.querySelector('main')
        if (!main) return { hasIssue: false }

        const rect = main.getBoundingClientRect()
        const viewportWidth = window.innerWidth

        return {
          hasIssue: rect.right > viewportWidth || rect.left < 0
        }
      })

      expect(mylistOverflow.hasIssue).toBe(false)

      // 設定ページも確認
      if (viewport.width >= 768) {
        await page.click('aside a:has-text("設定")')
      } else {
        await page.click('nav.fixed.bottom-0 a:has-text("設定")')
      }
      await page.waitForURL('**/settings')
      await page.waitForTimeout(500)

      const settingsOverflow = await page.evaluate(() => {
        const main = document.querySelector('main')
        if (!main) return { hasIssue: false }

        const rect = main.getBoundingClientRect()
        const viewportWidth = window.innerWidth

        return {
          hasIssue: rect.right > viewportWidth || rect.left < 0
        }
      })

      expect(settingsOverflow.hasIssue).toBe(false)
    })
  }
})
