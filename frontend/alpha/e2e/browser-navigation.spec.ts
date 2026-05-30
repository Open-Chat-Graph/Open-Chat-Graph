import { test, expect } from '@playwright/test'

test.describe('ブラウザナビゲーションテスト', () => {
  test('ブラウザバック/フォワードでモーダルの開閉ができる', async ({ page }) => {
    // 検索ページにアクセス
    await page.goto('/')

    // 検索実行
    const desktopSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await desktopSearch.fill('就活')
    await desktopSearch.press('Enter')
    await page.waitForSelector('h3')

    // 現在のURL（検索結果ページ）を記録
    const searchUrl = page.url()
    console.log(`検索ページURL: ${searchUrl}`)

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    const detailUrl = page.url()
    console.log(`詳細ページURL: ${detailUrl}`)

    // 詳細ページが表示されていることを確認
    await expect(page.locator('div.fixed.inset-0.z-50')).toBeVisible()

    // ブラウザバック（ネイティブナビゲーション）
    await page.goBack()
    await page.waitForURL(searchUrl)
    console.log(`ブラウザバック後のURL: ${page.url()}`)

    // リストページに戻っていることを確認
    await expect(page).toHaveURL(searchUrl)
    await expect(page.locator('h3').first()).toBeVisible()

    // 詳細ページのオーバーレイが非表示になっていることを確認
    const detailOverlay = page.locator('div.fixed.inset-0.z-50')
    await expect(detailOverlay).toHaveCount(0)

    // ブラウザフォワード
    await page.goForward()
    await page.waitForURL(detailUrl)
    console.log(`ブラウザフォワード後のURL: ${page.url()}`)

    // 詳細ページが再表示されていることを確認
    await expect(page).toHaveURL(detailUrl)
    await expect(page.locator('div.fixed.inset-0.z-50')).toBeVisible()
  })

  test('直接詳細ページにアクセスした場合、ブラウザバックでabout:blankになる（期待される動作）', async ({ page }) => {
    // 直接詳細ページにアクセス
    await page.goto('/openchat/101')
    await page.waitForTimeout(2000)

    // 詳細ページが表示されていることを確認
    await expect(page.locator('div.fixed.inset-0.z-50')).toBeVisible()

    // ブラウザバック（履歴がないのでabout:blankになる）
    await page.goBack()
    await page.waitForTimeout(500)

    // about:blankになることを確認（これがブラウザのデフォルト動作）
    await expect(page).toHaveURL('about:blank')

    console.log('ℹ️  直接アクセス時のブラウザバックは about:blank になります')
    console.log('ℹ️  手動戻るボタンはこの問題を回避してトップページに遷移します')
  })

  test('スマホ幅でナビゲーションバーの戻るボタンが機能する', async ({ page }) => {
    // スマホ幅に設定
    await page.setViewportSize({ width: 375, height: 667 })

    // 検索ページにアクセス
    await page.goto('/')

    // 検索実行
    const mobileSearch = page.locator('input[placeholder="キーワードを入力..."]').first()
    await mobileSearch.fill('就活')
    await mobileSearch.press('Enter')
    await page.waitForSelector('h3')
    const searchUrl = page.url()

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)

    // ナビゲーションバーの戻るボタンをクリック
    const backButton = page.locator('header button').first()
    await expect(backButton).toBeVisible()
    await backButton.click()
    await page.waitForTimeout(500)

    // 検索結果ページに戻ることを確認
    await expect(page).toHaveURL(searchUrl)
    console.log('✅ 手動戻るボタンは navigate(-1) を使用してブラウザバックと同等の動作をする')
  })
})
