import { test, expect } from '@playwright/test'

/**
 * マイリスト - フォルダURLナビゲーションのテスト
 *
 * フォルダ遷移時のURL変更とブラウザバック/フォワードをテスト
 */

test.describe('マイリスト - フォルダURLナビゲーション', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')

    // LocalStorageをクリア
    await page.evaluate(() => {
      localStorage.clear()
      sessionStorage.clear()
    })

    // テストデータをセットアップ
    await page.evaluate(() => {
      const testData = {
        version: 1,
        folders: [
          { id: 'folder-1', name: 'フォルダA', parentId: null, order: 0, expanded: true },
          { id: 'folder-2', name: 'フォルダB', parentId: 'folder-1', order: 0, expanded: false },
        ],
        items: [
          { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
          { id: 29, folderId: 'folder-1', order: 0, addedAt: new Date().toISOString() },
          { id: 188, folderId: 'folder-2', order: 0, addedAt: new Date().toISOString() },
        ],
        lastModified: new Date().toISOString(),
      }
      localStorage.setItem('alpha_mylist', JSON.stringify(testData))
    })

    // マイリストページに移動
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)
  })

  test('フォルダをクリックするとURLが変わる', async ({ page }) => {
    // 初期状態: /mylist
    await expect(page).toHaveURL(/\/js\/alpha\/mylist$/)

    // フォルダAをクリック
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForTimeout(500)

    // URL変更確認: /mylist/folder-1
    await expect(page).toHaveURL(/\/js\/alpha\/mylist\/folder-1$/)

    // タイトル更新確認
    await expect(page.locator('header')).toContainText('フォルダA')
  })

  test('ブラウザバックでルートフォルダに戻る', async ({ page }) => {
    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // ブラウザバック
    await page.goBack()
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // ルートに戻ったことを確認
    await expect(page.locator('header')).toContainText('マイリスト')
    await expect(page.locator('[data-testid="folder-item-folder-1"]')).toBeVisible()
  })

  test('ブラウザフォワードでフォルダに進む', async ({ page }) => {
    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // ブラウザバック
    await page.goBack()
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // ブラウザフォワード
    await page.goForward()
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダAに戻ったことを確認
    await expect(page.locator('header')).toContainText('フォルダA')
  })

  test('検索→マイリスト押下で最後のフォルダに戻る', async ({ page }) => {
    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // 検索ページに移動
    await page.click('aside a:has-text("検索")')
    await page.waitForURL(/\/js\/alpha\/?$/)
    await page.waitForTimeout(500)

    // マイリストボタンを押す
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダAに戻ったことを確認
    await expect(page.locator('header')).toContainText('フォルダA')
  })

  test('フォルダ内→マイリスト押下でルートに戻る', async ({ page }) => {
    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // マイリストボタンを押す
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // ルートに戻ったことを確認
    await expect(page.locator('header')).toContainText('マイリスト')
    await expect(page.locator('[data-testid="folder-item-folder-1"]')).toBeVisible()
  })

  test('階層移動: ルート → フォルダA → フォルダB → バック → バック', async ({ page }) => {
    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    await expect(page.locator('header')).toContainText('フォルダA')

    // フォルダBに移動
    await page.click('[data-testid="folder-item-folder-2"]')
    await page.waitForURL('**/mylist/folder-2')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    await expect(page.locator('header')).toContainText('フォルダB')
    await expect(page.locator('[data-testid="openchat-card-188"]')).toBeVisible()

    // ブラウザバック → フォルダAに戻る
    await page.goBack()
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    await expect(page.locator('header')).toContainText('フォルダA')
    await expect(page.locator('[data-testid="openchat-card-29"]')).toBeVisible()

    // ブラウザバック → ルートに戻る
    await page.goBack()
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    await expect(page.locator('header')).toContainText('マイリスト')
    await expect(page.locator('[data-testid="openchat-card-184"]')).toBeVisible()
  })

  test('戻るボタンでフォルダから上位階層に移動', async ({ page }) => {
    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // 戻るボタンが表示されることを確認（デスクトップ版）
    await expect(page.locator('[data-testid="go-up-button"]').nth(1)).toBeVisible()

    // 戻るボタンをクリック（デスクトップ版）
    await page.locator('[data-testid="go-up-button"]').nth(1).click()
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // ルートに戻ったことを確認
    await expect(page.locator('header')).toContainText('マイリスト')
    await expect(page.locator('[data-testid="folder-item-folder-1"]')).toBeVisible()
  })

  test('URL直接アクセスでフォルダページが表示される', async ({ page }) => {
    // LocalStorageをクリア
    await page.evaluate(() => {
      localStorage.clear()
      sessionStorage.clear()
    })

    // テストデータをセットアップ
    await page.evaluate(() => {
      const testData = {
        version: 1,
        folders: [
          { id: 'folder-1', name: 'フォルダA', parentId: null, order: 0, expanded: true },
          { id: 'folder-2', name: 'フォルダB', parentId: 'folder-1', order: 0, expanded: false },
        ],
        items: [
          { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
          { id: 29, folderId: 'folder-1', order: 0, addedAt: new Date().toISOString() },
          { id: 188, folderId: 'folder-2', order: 0, addedAt: new Date().toISOString() },
        ],
        lastModified: new Date().toISOString(),
      }
      localStorage.setItem('alpha_mylist', JSON.stringify(testData))
    })

    // フォルダAのURLに直接アクセス
    await page.goto('/mylist/folder-1')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダAの内容が表示されることを確認
    await expect(page.locator('header')).toContainText('フォルダA')
    await expect(page.locator('[data-testid="openchat-card-29"]')).toBeVisible()
    await expect(page.locator('[data-testid="folder-item-folder-2"]')).toBeVisible()
  })

  test('モバイル: 検索→マイリスト押下で最後のフォルダに戻る', async ({ page }) => {
    // モバイルビューポートに変更
    await page.setViewportSize({ width: 375, height: 667 })
    await page.waitForTimeout(500)

    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // 検索ページに移動（モバイル下部ナビから）
    // 下部ナビゲーションの「検索」ボタンをクリック（fixed bottom-0）
    await page.locator('nav.fixed.bottom-0 a').filter({ hasText: '検索' }).click()
    await page.waitForURL(/\/js\/alpha\/?$/)
    await page.waitForTimeout(500)

    // マイリストボタンを押す（モバイル下部ナビから）
    await page.locator('nav.fixed.bottom-0 a').filter({ hasText: 'マイリスト' }).click()
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダAに戻ったことを確認
    await expect(page.locator('header')).toContainText('フォルダA')
  })

  test('sessionStorageクリーンアップ: フォルダ→ルート→検索→マイリストでルートに戻る', async ({ page }) => {
    // フォルダAに移動（sessionStorageに保存される）
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // マイリストボタンでルートに戻る（sessionStorageをクリア）
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // ルートに戻ったことを確認
    await expect(page.locator('header')).toContainText('マイリスト')

    // 検索ページに移動
    await page.click('aside a:has-text("検索")')
    await page.waitForURL(/\/js\/alpha\/?$/)
    await page.waitForTimeout(500)

    // マイリストボタンを押す
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダAではなくルートに戻ったことを確認（sessionStorageがクリアされている）
    await expect(page.locator('header')).toContainText('マイリスト')
    await expect(page.locator('[data-testid="folder-item-folder-1"]')).toBeVisible()
  })

  test('メニューリンクが常に/mylistであることを確認', async ({ page }) => {
    // 初期状態: ルートフォルダ
    const rootHref = await page.locator('aside a:has-text("マイリスト")').getAttribute('href')
    expect(rootHref).toBe('/js/alpha/mylist')

    // フォルダAに移動
    await page.click('[data-testid="folder-item-folder-1"]')
    await page.waitForURL('**/mylist/folder-1')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダ内でもメニューリンクは/mylistのまま
    const folderHref = await page.locator('aside a:has-text("マイリスト")').getAttribute('href')
    expect(folderHref).toBe('/js/alpha/mylist')

    // フォルダBに移動
    await page.click('[data-testid="folder-item-folder-2"]')
    await page.waitForURL('**/mylist/folder-2')
    await page.waitForTimeout(500)

    // 統計データの読み込み完了を待つ
    await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
    await page.waitForTimeout(500)

    // フォルダBでもメニューリンクは/mylistのまま
    const folder2Href = await page.locator('aside a:has-text("マイリスト")').getAttribute('href')
    expect(folder2Href).toBe('/js/alpha/mylist')
  })
})
