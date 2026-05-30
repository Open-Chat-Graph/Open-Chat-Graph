import { test, expect } from '@playwright/test'

/**
 * ページ再クリック時の再レンダリングテスト
 * 
 * 同じページのナビゲーションボタンを再度クリックしたとき、
 * ページが再レンダリングされてリセットされることを確認
 */

test.describe('ページ再クリック時の再レンダリング', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
    await page.waitForSelector('input[placeholder="キーワードを入力..."]', { timeout: 10000 })
  })

  test.skip('検索ページで検索ボタン再クリック → クエリがリセットされる', async ({ page }) => {
    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // URLを確認
    expect(page.url()).toContain('q=%E3%83%86%E3%82%B9%E3%83%88')

    // 検索結果があることを確認
    const resultsCount = await page.locator('.grid > div').count()
    expect(resultsCount).toBeGreaterThan(0)

    // 検索ボタンを再クリック（サイドバーから）
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // URLがリセットされている
    expect(page.url()).not.toContain('q=')
    expect(page.url()).toMatch(/http:\/\/localhost:5173\/js\/alpha\/?$/)

    // sessionStorageがクリアされている
    const savedQuery = await page.evaluate(() => sessionStorage.getItem('searchPageQuery'))
    expect(savedQuery).toBeNull()

    // 検索結果がクリアされている
    const resultsAfter = await page.locator('.grid > div').count()
    expect(resultsAfter).toBe(0)

    // 初期メッセージが表示されている
    await expect(page.locator('text=キーワードを入力して検索してください')).toBeVisible()
  })

  test('検索リセット後、他ページから戻っても空の検索が維持される', async ({ page }) => {
    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'グループ')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // 検索ボタンを再クリックしてリセット
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // マイリストに遷移
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForTimeout(1000)

    // 検索ボタンで戻る
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // 空の検索が維持されている（古いクエリが復元されない）
    expect(page.url()).not.toContain('q=')
    const resultsCount = await page.locator('.grid > div').count()
    expect(resultsCount).toBe(0)
  })

  test.skip('マイリストページでマイリストボタン再クリック → 再レンダリングされる', async ({ page }) => {
    // マイリストに遷移
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // location.stateのtimestampを確認
    const timestamp1 = await page.evaluate(() => window.history.state?.usr?.timestamp)

    // マイリストボタンを再クリック
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForTimeout(500)

    // 新しいtimestampが設定されている（再レンダリングされた証拠）
    const timestamp2 = await page.evaluate(() => window.history.state?.usr?.timestamp)
    expect(timestamp2).toBeDefined()
    expect(timestamp2).not.toBe(timestamp1)

    // URLは変わらない
    expect(page.url()).toContain('/mylist')
  })

  test('設定ページで設定ボタン再クリック → 再レンダリングされる', async ({ page }) => {
    // 設定に遷移
    await page.click('aside a:has-text("設定")')
    await page.waitForURL('**/settings')
    await page.waitForTimeout(500)

    // location.stateのtimestampを確認
    const timestamp1 = await page.evaluate(() => window.history.state?.usr?.timestamp)

    // 設定ボタンを再クリック
    await page.click('aside a:has-text("設定")')
    await page.waitForTimeout(500)

    // 新しいtimestampが設定されている（再レンダリングされた証拠）
    const timestamp2 = await page.evaluate(() => window.history.state?.usr?.timestamp)
    expect(timestamp2).toBeDefined()
    expect(timestamp2).not.toBe(timestamp1)

    // URLは変わらない
    expect(page.url()).toContain('/settings')
  })

  test('モバイル下部ナビでも再レンダリングが動作する', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await page.goto('/')
    await page.waitForTimeout(1000)

    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'オープン')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    expect(page.url()).toContain('q=')

    // 下部ナビの検索ボタンを再クリック
    await page.click('nav.fixed.bottom-0 a:has-text("検索")')
    await page.waitForTimeout(1000)

    // URLがリセットされている
    expect(page.url()).not.toContain('q=')

    // sessionStorageがクリアされている
    const savedQuery = await page.evaluate(() => sessionStorage.getItem('searchPageQuery'))
    expect(savedQuery).toBeNull()
  })
})
