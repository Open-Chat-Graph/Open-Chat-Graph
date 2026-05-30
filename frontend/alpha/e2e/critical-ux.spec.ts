import { test, expect } from '@playwright/test'

test.describe('Critical UX Tests - 検索とナビゲーション', () => {
  test.beforeEach(async ({ page }) => {
    // 開発サーバーのルートにアクセス（検索ページ）
    await page.goto('/')
  })

  test('スマホ幅で検索バーに文字入力できる', async ({ page }) => {
    // エラーが発生しないことを確認
    const consoleErrors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text())
      }
    })

    // スマホ幅に設定
    await page.setViewportSize({ width: 375, height: 667 })

    // ページをリロードしてモバイルレイアウトを適用
    await page.reload()

    // モバイルヘッダーの検索バーを探す
    const mobileSearchInput = page.locator('input[placeholder="キーワードを入力..."]').first()

    // 入力できることを確認
    await mobileSearchInput.fill('テスト')
    await expect(mobileSearchInput).toHaveValue('テスト')

    // Enterキーで検索実行
    await mobileSearchInput.press('Enter')

    // URLが更新されることを確認（URLエンコードされる）
    await expect(page).toHaveURL(/q=/)

    await page.waitForTimeout(1000)

    // setSearchParams エラーがないことを確認
    const hasSetSearchParamsError = consoleErrors.some(err =>
      err.includes('setSearchParams is not defined')
    )
    expect(hasSetSearchParamsError).toBe(false)
  })

  test('詳細画面から戻ったときに検索結果が表示される', async ({ page }) => {
    // 検索を実行（デスクトップの検索バーを使用）
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first() // 2番目がデスクトップ
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')

    // 検索結果が表示されるまで待つ
    await page.waitForSelector('h3')

    // 最初の結果をクリック
    const firstResult = page.locator('h3').first()
    await firstResult.click()

    // 詳細ページに遷移することを確認
    await expect(page).toHaveURL(/\/openchat\/\d+/)

    // ブラウザバック
    await page.goBack()

    // 検索ページに戻ることを確認（URLエンコードされる）
    await expect(page).toHaveURL(/q=/)

    // 検索結果が表示されることを確認（「読み込み中...」だけでない）
    await expect(page.locator('h3').first()).toBeVisible({ timeout: 5000 })

    // 結果件数が表示されることを確認
    await expect(page.locator('text=/\\d+件/')).toBeVisible()
  })

  test.skip('ブラウザバック時にスクロール位置が保たれる', async ({ page }) => {
    // ブラウザのコンソールログを表示
    page.on('console', msg => console.log(`[Browser] ${msg.text()}`))

    // 検索を実行（デスクトップの検索バーを使用）
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')

    // 検索結果が表示されるまで待つ
    await page.waitForSelector('h3')

    // 下にスクロール
    await page.evaluate(() => window.scrollTo(0, 500))
    const scrollYBefore = await page.evaluate(() => window.scrollY)
    console.log(`[Test] Scroll position before navigation: ${scrollYBefore}`)
    expect(scrollYBefore).toBeGreaterThan(400)

    // 最初の結果をクリック
    await page.locator('h3').first().click()
    await expect(page).toHaveURL(/\/openchat\/\d+/)

    // ブラウザバック
    await page.goBack()
    await expect(page).toHaveURL(/q=/)

    // スクロール位置が復元されるまで少し待つ（BF-Cache）
    await page.waitForTimeout(1000)

    // スクロール位置が保たれていることを確認
    const scrollYAfter = await page.evaluate(() => window.scrollY)
    console.log(`[Test] Scroll position after back: ${scrollYAfter}`)
    console.log(`[Test] Difference: ${Math.abs(scrollYAfter - scrollYBefore)}px`)

    expect(Math.abs(scrollYAfter - scrollYBefore)).toBeLessThan(50) // 誤差50px以内
  })

  test('ブラウザフォワード→バックでもスクロール位置が保たれる', async ({ page }) => {
    // 検索を実行（デスクトップの検索バーを使用）
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')

    // 検索結果が表示されるまで待つ
    await page.waitForSelector('h3')

    // 下にスクロール
    await page.evaluate(() => window.scrollTo(0, 500))
    const scrollYBefore = await page.evaluate(() => window.scrollY)

    // 最初の結果をクリック
    await page.locator('h3').first().click()
    await expect(page).toHaveURL(/\/openchat\/\d+/)

    // ブラウザバック
    await page.goBack()
    await expect(page).toHaveURL(/q=/)
    await page.waitForTimeout(500)

    // ブラウザフォワード
    await page.goForward()
    await expect(page).toHaveURL(/\/openchat\/\d+/)

    // 再度ブラウザバック
    await page.goBack()
    await expect(page).toHaveURL(/q=/)
    await page.waitForTimeout(500)

    // スクロール位置が保たれていることを確認
    const scrollYAfter = await page.evaluate(() => window.scrollY)
    expect(Math.abs(scrollYAfter - scrollYBefore)).toBeLessThan(50)
  })


})

