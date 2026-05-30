import { test, expect } from '@playwright/test'

/**
 * コアナビゲーションパターンのE2Eテスト
 *
 * このテストは、アプリの基本設計に基づく重要なナビゲーションパターンを検証します。
 */
test.describe('コアナビゲーションパターン', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
    // 検索フィールドが表示されるまで待機
    await page.waitForSelector('input[placeholder="キーワードを入力..."]', { timeout: 10000 })
  })

  test('検索 → マイリスト → 検索ボタン（検索状態が維持される）', async ({ page }) => {
    // 1. 検索ページでキーワードを入力して検索
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')

    // URLが変わるまで待機
    await page.waitForURL('**/?q=%E3%83%86%E3%82%B9%E3%83%88', { timeout: 5000 })

    // 2. マイリストに遷移
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/mylist"]')
    } else {
      await page.click('aside a[href="/mylist"]')
    }

    await page.waitForURL('**/mylist')

    // 3. 検索ボタンをクリック
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/"]')
    } else {
      await page.click('aside a[href="/"]')
    }

    // 4. 検索ページに戻り、検索状態が維持されていることを確認
    await page.waitForURL('**/?q=%E3%83%86%E3%82%B9%E3%83%88')

    const searchInput = page.locator('input[placeholder="キーワードを入力..."]')
    await expect(searchInput).toHaveValue('テスト')
  })

  test('検索 → 詳細 → 検索ボタン（検索状態が維持される）', async ({ page }) => {
    // 1. 検索ページでキーワードを入力して検索
    await page.fill('input[placeholder="キーワードを入力..."]', 'グループ')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')

    await page.waitForURL('**/?q=%E3%82%B0%E3%83%AB%E3%83%BC%E3%83%97', { timeout: 5000 })

    // 検索結果が表示されるまで待機
    await page.waitForSelector('.grid > div', { timeout: 10000 })

    // 2. 最初の検索結果をクリック
    const firstCard = page.locator('.grid > div').first()
    await firstCard.click()

    // 詳細ページに遷移
    await page.waitForURL('**/openchat/**')

    // 3. 検索ボタンをクリック
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/"]')
    } else {
      await page.click('aside a[href="/"]')
    }

    // 4. 検索ページに戻り、検索状態が維持されていることを確認
    await page.waitForURL('**/?q=%E3%82%B0%E3%83%AB%E3%83%BC%E3%83%97')

    const searchInput = page.locator('input[placeholder="キーワードを入力..."]')
    await expect(searchInput).toHaveValue('グループ')
  })

  test.skip('マイリスト → 詳細 → ブラウザバック（マイリストのスクロール位置が復元される）', async ({ page }) => {
    // マイリストにアイテムを追加する準備
    // まず検索して何かを見つける
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForURL('**/?q=%E3%83%86%E3%82%B9%E3%83%88', { timeout: 5000 })

    // 検索結果が表示されるまで待機
    await page.waitForSelector('.grid > div', { timeout: 10000 })

    // 1つ目のアイテムをマイリストに追加
    const firstCard = page.locator('.grid > div').first()
    const addButton = firstCard.locator('button').first()
    await addButton.click()

    // フォルダ選択ダイアログが表示されるまで待機
    await page.waitForSelector('[role="dialog"]', { timeout: 5000 })

    // 「ルート」を選択
    await page.click('button:has-text("ルート")')

    // 1. マイリストページに遷移
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/mylist"]')
    } else {
      await page.click('aside a[href="/mylist"]')
    }

    await page.waitForURL('**/mylist')

    // マイリストページで下にスクロール（スクロール位置を作る）
    await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      if (overlay) {
        overlay.scrollTo(0, 200)
      }
    })

    // スクロール位置が設定されたことを確認
    await page.waitForTimeout(500)
    const scrollY = await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      return overlay ? overlay.scrollTop : 0
    })
    expect(scrollY).toBeGreaterThan(100)

    // 2. マイリストのアイテムをクリックして詳細ページに遷移
    const mylistItem = page.locator('.grid > div').first()
    await mylistItem.click()

    await page.waitForURL('**/openchat/**')

    // 3. ブラウザバックでマイリストに戻る
    await page.goBack()

    await page.waitForURL('**/mylist')

    // 4. マイリストのスクロール位置が復元されていることを確認
    await page.waitForTimeout(500)
    const restoredScrollY = await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      return overlay ? overlay.scrollTop : 0
    })

    // スクロール位置が概ね復元されていることを確認（完全一致でなくても良い）
    expect(restoredScrollY).toBeGreaterThan(100)
    expect(restoredScrollY).toBeCloseTo(scrollY, -1) // 10の位まで一致
  })

  test.skip('マイリスト → 詳細（詳細はスクロール位置0から始まる）', async ({ page }) => {
    // マイリストにアイテムを追加する準備
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForURL('**/?q=%E3%83%86%E3%82%B9%E3%83%88', { timeout: 5000 })
    await page.waitForSelector('.grid > div', { timeout: 10000 })

    const firstCard = page.locator('.grid > div').first()
    const addButton = firstCard.locator('button').first()
    await addButton.click()
    await page.waitForSelector('[role="dialog"]', { timeout: 5000 })
    await page.click('button:has-text("ルート")')

    // 1. マイリストページに遷移
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/mylist"]')
    } else {
      await page.click('aside a[href="/mylist"]')
    }

    await page.waitForURL('**/mylist')

    // マイリストページで下にスクロール
    await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      if (overlay) {
        overlay.scrollTo(0, 300)
      }
    })

    await page.waitForTimeout(500)

    // 2. マイリストのアイテムをクリックして詳細ページに遷移
    const mylistItem = page.locator('.grid > div').first()
    await mylistItem.click()

    await page.waitForURL('**/openchat/**')

    // 3. 詳細ページのスクロール位置が0であることを確認
    await page.waitForTimeout(500)
    const detailScrollY = await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      return overlay ? overlay.scrollTop : 0
    })

    expect(detailScrollY).toBe(0)
  })

  test('詳細A → 詳細B（詳細Bはスクロール位置0から始まる）', async ({ page }) => {
    // 1. 検索して2つの結果を取得
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForURL('**/?q=%E3%83%86%E3%82%B9%E3%83%88', { timeout: 5000 })
    await page.waitForSelector('.grid > div', { timeout: 10000 })

    // 2. 最初の詳細ページを開く
    const firstCard = page.locator('.grid > div').first()
    await firstCard.click()
    await page.waitForURL('**/openchat/**')

    const firstDetailUrl = page.url()

    // 詳細ページAで下にスクロール
    await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      if (overlay) {
        overlay.scrollTo(0, 400)
      }
    })

    await page.waitForTimeout(500)

    // 3. 検索ボタンで検索ページに戻る
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/"]')
    } else {
      await page.click('aside a[href="/"]')
    }

    await page.waitForURL('**/?q=%E3%83%86%E3%82%B9%E3%83%88')

    // 4. 2つ目の詳細ページを開く
    const secondCard = page.locator('.grid > div').nth(1)
    await secondCard.click()
    await page.waitForURL('**/openchat/**')

    const secondDetailUrl = page.url()

    // 異なる詳細ページであることを確認
    expect(secondDetailUrl).not.toBe(firstDetailUrl)

    // 5. 詳細ページBのスクロール位置が0であることを確認
    await page.waitForTimeout(500)
    const detailBScrollY = await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      return overlay ? overlay.scrollTop : 0
    })

    expect(detailBScrollY).toBe(0)
  })

  test('検索ページで検索ボタン（空の検索にリセット）', async ({ page }) => {
    // 1. 検索ページでキーワードを入力して検索
    await page.fill('input[placeholder="キーワードを入力..."]', 'リセット')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')

    await page.waitForURL('**/?q=%E3%83%AA%E3%82%BB%E3%83%83%E3%83%88', { timeout: 5000 })

    // 2. 検索ページで再び検索ボタンを押す
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a[href="/"]')
    } else {
      await page.click('aside a[href="/"]')
    }

    // 3. 空の検索ページに戻る
    await page.waitForURL('/')

    // URLパラメータがクリアされている
    expect(page.url()).not.toContain('q=')

    // 検索フィールドが空になっている
    const searchInput = page.locator('input[placeholder="キーワードを入力..."]')
    await expect(searchInput).toHaveValue('')

    // 初期メッセージが表示されている
    await expect(page.locator('text=キーワードを入力して検索してください')).toBeVisible()
  })
})
