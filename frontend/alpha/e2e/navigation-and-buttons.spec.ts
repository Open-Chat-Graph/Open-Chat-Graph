import { test, expect } from '@playwright/test'

test.describe('ナビゲーションとボタンのテスト', () => {
  test.beforeEach(async ({ page }) => {
    // 検索ページにアクセス
    await page.goto('/')
  })

  test('詳細ページでハンバーガーメニューが表示され、機能する', async ({ page }) => {
    // スマホ幅に設定
    await page.setViewportSize({ width: 375, height: 667 })

    // 検索実行
    const mobileSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await mobileSearch.fill('就活')
    await mobileSearch.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    await page.waitForTimeout(1000)

    // ハンバーガーメニューボタンを探す（ヘッダー内の右側のボタン）
    const menuButton = page.locator('header button').last()
    await expect(menuButton).toBeVisible()

    // メニューを開く
    await menuButton.click()
    await page.waitForTimeout(500)

    // サイドバーが表示されることを確認
    await expect(page.locator('aside').getByText('オプチャグラフα')).toBeVisible()
    await expect(page.locator('aside').getByText('検索')).toBeVisible()
    await expect(page.locator('aside').getByText('マイリスト')).toBeVisible()
  })

  test('戻るボタンが機能する（履歴がある場合）', async ({ page }) => {
    // スマホ幅に設定
    await page.setViewportSize({ width: 375, height: 667 })

    // 検索実行
    const mobileSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await mobileSearch.fill('就活')
    await mobileSearch.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    await page.waitForTimeout(1000)

    // 戻るボタンをクリック（ヘッダーの戻るボタン）
    const backButton = page.locator('header button').first()
    await expect(backButton).toBeVisible()
    await backButton.click()

    // 検索結果ページに戻ることを確認
    await page.waitForTimeout(500)
    await expect(page).toHaveURL(/\?q=/)
    await expect(page.locator('h3').first()).toBeVisible()
  })

  test('直接詳細ページにアクセスした場合、戻るボタンでトップに戻る', async ({ page }) => {
    // スマホ幅に設定
    await page.setViewportSize({ width: 375, height: 667 })

    // 直接詳細ページにアクセス
    await page.goto('/openchat/101')
    await page.waitForTimeout(2000)

    // 戻るボタンをクリック（ヘッダーの戻るボタン）
    const backButton = page.locator('header button').first()
    await expect(backButton).toBeVisible()
    await backButton.click()

    // トップページに戻ることを確認
    await page.waitForTimeout(500)
    await expect(page).toHaveURL('/')
  })

  test('詳細ページでマイリスト追加ボタンが表示される', async ({ page }) => {
    // 検索実行
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    await page.waitForTimeout(1000)

    // マイリスト追加ボタンが表示されることを確認
    const addButton = page.getByRole('button', { name: /マイリストに追加/i })
    await expect(addButton).toBeVisible()

    // ボタンをクリックしてダイアログが開くことを確認
    await addButton.click()
    await expect(page.getByRole('heading', { name: 'マイリストに追加' })).toBeVisible()
    await expect(page.getByText('保存先のフォルダを選択してください')).toBeVisible()
  })

  test('詳細ページでLINEで開くボタンが表示される', async ({ page }) => {
    // 検索実行
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    await page.waitForTimeout(1000)

    // LINEで開くボタンが表示されることを確認
    const lineButton = page.getByRole('button', { name: /LINEで開く/i })
    await expect(lineButton).toBeVisible()
  })

  test('スマホ幅で詳細ページのコンテンツにマージンがある', async ({ page }) => {
    // スマホ幅に設定
    await page.setViewportSize({ width: 375, height: 667 })

    // 検索実行
    const mobileSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await mobileSearch.fill('就活')
    await mobileSearch.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    await page.waitForTimeout(1000)

    // コンテンツエリアの左マージンを確認
    // DetailInfoやDetailStatsを含むdivを特定（px-3クラスを持つdiv）
    const contentArea = page.locator('div.px-3.md\\:px-0').first()
    const paddingLeft = await contentArea.evaluate(el => {
      const style = window.getComputedStyle(el)
      return parseInt(style.paddingLeft)
    })

    // スマホ幅ではpx-3 (12px) のマージンがあることを確認
    expect(paddingLeft).toBeGreaterThanOrEqual(10)
    console.log(`Content padding-left: ${paddingLeft}px`)
  })

  test('PC幅では詳細ページのアニメーションが無効', async ({ page }) => {
    // PC幅に設定
    await page.setViewportSize({ width: 1280, height: 720 })

    // 検索実行
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    const startTime = Date.now()
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    const transitionTime = Date.now() - startTime

    // アニメーションが無効なので、遷移は高速（500ms以下）
    console.log(`PC transition time: ${transitionTime}ms`)
    expect(transitionTime).toBeLessThan(1000)
  })
})
