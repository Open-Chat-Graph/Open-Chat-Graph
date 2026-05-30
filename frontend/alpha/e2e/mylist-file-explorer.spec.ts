import { test, expect } from '@playwright/test'

/**
 * マイリスト - ファイルエクスプローラー方式のテスト
 *
 * ツリー表示からファイルエクスプローラー方式への移行をテスト
 */

test.describe('マイリスト - ファイルエクスプローラー方式', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')

    // LocalStorageをクリア
    await page.evaluate(() => {
      localStorage.clear()
    })

    // マイリストページに移動
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)
  })

  test.describe('1. フォルダナビゲーション', () => {
    test('タイトルバーに現在位置が表示される', async ({ page }) => {
      // テストデータをセットアップ（フォルダとアイテムを作成）
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-1', name: 'テストフォルダ1', parentId: null, order: 0, expanded: true },
            { id: 'folder-2', name: 'サブフォルダ', parentId: 'folder-1', order: 0, expanded: false }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
            { id: 29, folderId: 'folder-1', order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)

      // 統計データの読み込み完了を待つ（「読み込み中...」が消えるまで）
      await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)

      // ルートレベル: タイトルに「マイリスト」と件数が表示される
      await expect(page.locator('header')).toContainText('マイリスト')
      await expect(page.locator('header')).toContainText('件')

      // フォルダに移動
      await page.click('[data-testid="folder-item-folder-1"]')
      await page.waitForTimeout(500)

      // タイトル更新: フォルダ名と件数が表示される
      await expect(page.locator('header')).toContainText('テストフォルダ1')
      await expect(page.locator('header')).toContainText('件')
    })

    test('フォルダをクリックするとその中に移動し、その内容のみ表示される', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-1', name: 'フォルダA', parentId: null, order: 0, expanded: true }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
            { id: 29, folderId: 'folder-1', order: 0, addedAt: new Date().toISOString() },
            { id: 188, folderId: 'folder-1', order: 1, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...', { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)

      // ルートレベル: フォルダAとアイテム184が表示
      await expect(page.locator('[data-testid="folder-item-folder-1"]')).toBeVisible()
      await expect(page.locator('[data-testid="openchat-card-184"]')).toBeVisible()

      // アイテム29, 188はまだ非表示
      await expect(page.locator('[data-testid="openchat-card-29"]')).not.toBeVisible()
      await expect(page.locator('[data-testid="openchat-card-188"]')).not.toBeVisible()

      // フォルダAをクリック
      await page.click('[data-testid="folder-item-folder-1"]')
      await page.waitForTimeout(500)

      // フォルダA内: アイテム29, 188のみ表示
      await expect(page.locator('[data-testid="openchat-card-29"]')).toBeVisible()
      await expect(page.locator('[data-testid="openchat-card-188"]')).toBeVisible()

      // ルートのアイテム184は非表示
      await expect(page.locator('[data-testid="openchat-card-184"]')).not.toBeVisible()

      // フォルダAも非表示（現在のディレクトリなので）
      await expect(page.locator('[data-testid="folder-item-folder-1"]')).not.toBeVisible()
    })

    test('戻るボタンで親フォルダに戻れる', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-1', name: 'フォルダA', parentId: null, order: 0, expanded: true }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
            { id: 29, folderId: 'folder-1', order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // フォルダに移動
      await page.click('[data-testid="folder-item-folder-1"]')
      await page.waitForTimeout(500)

      // フォルダ内にいることを確認
      await expect(page.locator('[data-testid="openchat-card-29"]')).toBeVisible()

      // 戻るボタンをクリック（PC用の戻るボタン = 2番目の要素）
      await page.locator('[data-testid="go-up-button"]').last().click()
      await page.waitForTimeout(500)

      // ルートレベルに戻った
      await expect(page.locator('[data-testid="folder-item-folder-1"]')).toBeVisible()
      await expect(page.locator('[data-testid="openchat-card-184"]')).toBeVisible()
      await expect(page.locator('[data-testid="openchat-card-29"]')).not.toBeVisible()
    })
  })

  test.describe('2. ドラッグハンドルの表示制御', () => {
    test.skip('カスタムソート時: ドラッグハンドルが表示される', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
        localStorage.setItem('alpha_mylist_sort', JSON.stringify({ sortType: 'custom', order: 'asc' }))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // ドラッグハンドルが表示されている
      await expect(page.locator('[data-testid="drag-handle-184"]')).toBeVisible()

      // カードの左側にpaddingがある（ドラッグハンドル用のスペース）
      const card = page.locator('[data-testid="openchat-card-184"]')
      const paddingLeft = await card.evaluate((el) => {
        return window.getComputedStyle(el).paddingLeft
      })
      expect(parseInt(paddingLeft)).toBeGreaterThan(0)
    })

    test('他のソート時: ドラッグハンドルもpaddingも削除される', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
        localStorage.setItem('alpha_mylist_sort', JSON.stringify({ sortType: 'member', order: 'desc' }))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // ドラッグハンドルが非表示
      await expect(page.locator('[data-testid="drag-handle-184"]')).not.toBeVisible()

      // カードの左側paddingが削除されている（またはゼロに近い）
      const card = page.locator('[data-testid="openchat-card-184"]')
      const hasNoPaddingClass = await card.evaluate((el) => {
        // ドラッグハンドル用のpaddingクラスがないことを確認
        return !el.className.includes('pl-[20%]') && !el.className.includes('md:pl-[15%]')
      })
      expect(hasNoPaddingClass).toBe(true)
    })
  })

  test.describe('3. フォルダのソート', () => {
    test('フォルダは常に名前順でソートされる', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-c', name: 'Cフォルダ', parentId: null, order: 0, expanded: false },
            { id: 'folder-a', name: 'Aフォルダ', parentId: null, order: 1, expanded: false },
            { id: 'folder-b', name: 'Bフォルダ', parentId: null, order: 2, expanded: false }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
        localStorage.setItem('alpha_mylist_sort', JSON.stringify({ sortType: 'member', order: 'desc' }))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // フォルダがA, B, Cの順で表示されている
      const folderNames = await page.locator('[data-testid^="folder-item-"]').allTextContents()
      expect(folderNames[0]).toContain('Aフォルダ')
      expect(folderNames[1]).toContain('Bフォルダ')
      expect(folderNames[2]).toContain('Cフォルダ')
    })

    test('フォルダは常に先頭に表示される', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-1', name: 'Zフォルダ', parentId: null, order: 0, expanded: false }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // 最初の要素がフォルダ
      const firstItem = page.locator('[data-testid^="folder-item-"], [data-testid^="openchat-card-"]').first()
      const testId = await firstItem.getAttribute('data-testid')
      expect(testId).toContain('folder-item-')
    })
  })

  test.describe('4. 選択モードUI', () => {
    test('「複数選択」ボタンでモードが開始される', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // 「複数選択」ボタンをクリック
      await page.click('[data-testid="selection-mode-button"]')
      await page.waitForTimeout(500)

      // 一括操作ツールバーが表示される（PC版またはスマホ版）
      const bulkActionBar = page.locator('[data-testid="bulk-action-bar-desktop"], [data-testid="bulk-action-bar"]').first()
      await expect(bulkActionBar).toBeVisible()

      // チェックボックスが表示される
      await expect(page.locator('[data-testid="checkbox-184"]')).toBeVisible()
    })

    test('Gmail風ツールバーに「すべて選択」が表示される', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
            { id: 29, folderId: null, order: 1, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // 選択モード開始
      await page.click('[data-testid="selection-mode-button"]')
      await page.waitForTimeout(500)

      // 「すべて選択」ボタンが表示される（PC版またはスマホ版）
      const selectAllButton = page.locator('[data-testid="select-all-button"], [data-testid="select-all-button-mobile"]').first()
      await expect(selectAllButton).toBeVisible()

      // クリックするとすべてが選択される
      await selectAllButton.click()
      await page.waitForTimeout(300)

      // 選択件数が削除ボタンに表示される
      const deleteButton = page.locator('button:has-text("2件")').first()
      await expect(deleteButton).toBeVisible()
    })
  })

  test.describe('5. 移動機能（ドラッグ廃止）', () => {
    test('ドラッグ&ドロップでフォルダに入れる機能は無効化されている', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-1', name: 'フォルダA', parentId: null, order: 0, expanded: false }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
        localStorage.setItem('alpha_mylist_sort', JSON.stringify({ sortType: 'custom', order: 'asc' }))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // ドラッグ可能な要素にdraggable属性がないことを確認
      const isDraggable = await page.locator('[data-testid="openchat-card-184"]').getAttribute('draggable')
      expect(isDraggable).toBeNull()
    })

    test('選択 → ツールバーから移動ができる', async ({ page }) => {
      // テストデータをセットアップ
      await page.evaluate(() => {
        const testData = {
          version: 1,
          folders: [
            { id: 'folder-1', name: 'フォルダA', parentId: null, order: 0, expanded: false }
          ],
          items: [
            { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() }
          ],
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // 選択モード開始
      await page.click('[data-testid="selection-mode-button"]')
      await page.waitForTimeout(500)

      // アイテムを選択
      await page.click('[data-testid="checkbox-184"]')
      await page.waitForTimeout(300)

      // 一括操作バーが表示され、移動ボタンがある（PC版またはスマホ版）
      const bulkActionBar = page.locator('[data-testid="bulk-action-bar-desktop"], [data-testid="bulk-action-bar"]').first()
      await expect(bulkActionBar).toBeVisible()

      const moveButton = page.locator('[data-testid="bulk-move-button"], [data-testid="bulk-move-button-mobile"]').first()
      await expect(moveButton).toBeVisible()
    })
  })

  test.describe('6. スクロール改善', () => {
    test.skip('スワイプスクロールが動作する', async ({ page }) => {
      // モバイルビューポート
      await page.setViewportSize({ width: 375, height: 812 })

      // テストデータをセットアップ（多数のアイテム）
      await page.evaluate(() => {
        // 実在するチャットIDを組み合わせたテストデータ
        const baseIds = [184, 29, 188]
        const items = Array.from({ length: 30 }, (_, i) => ({
          id: baseIds[i % baseIds.length] + i, // 実在IDをベースに連番を追加
          folderId: null,
          order: i,
          addedAt: new Date().toISOString()
        }))
        const testData = {
          version: 1,
          folders: [],
          items,
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // スクロールコンテナを取得
      const scrollContainer = page.locator('main > div[style*="position: absolute"]').first()

      // 初期スクロール位置を確認
      const initialScrollY = await scrollContainer.evaluate((el) => el.scrollTop)
      expect(initialScrollY).toBe(0)

      // スワイプ（タッチスクロール）をシミュレート
      await scrollContainer.evaluate((el) => {
        el.scrollTo({ top: 500, behavior: 'smooth' })
      })
      await page.waitForTimeout(500)

      // スクロール位置が変わっている
      const finalScrollY = await scrollContainer.evaluate((el) => el.scrollTop)
      expect(finalScrollY).toBeGreaterThan(100)
    })

    test.skip('カスタムソート時でもスクロール可能', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 812 })

      // テストデータをセットアップ
      await page.evaluate(() => {
        // 実在するチャットIDを組み合わせたテストデータ
        const baseIds = [184, 29, 188]
        const items = Array.from({ length: 30 }, (_, i) => ({
          id: baseIds[i % baseIds.length] + i, // 実在IDをベースに連番を追加
          folderId: null,
          order: i,
          addedAt: new Date().toISOString()
        }))
        const testData = {
          version: 1,
          folders: [],
          items,
          lastModified: new Date().toISOString()
        }
        localStorage.setItem('alpha_mylist', JSON.stringify(testData))
        localStorage.setItem('alpha_mylist_sort', JSON.stringify({ sortType: 'custom', order: 'asc' }))
      })
      await page.reload()

      // 統計データの読み込み完了を待つ
      await page.waitForSelector('text=読み込み中...'  , { state: 'hidden', timeout: 10000 })
      await page.waitForTimeout(500)
      await page.waitForTimeout(1000)

      // スクロールコンテナ
      const scrollContainer = page.locator('main > div[style*="position: absolute"]').first()

      // スクロール実行
      await scrollContainer.evaluate((el) => {
        el.scrollTo({ top: 500, behavior: 'smooth' })
      })
      await page.waitForTimeout(500)

      // スクロールが動作している
      const scrollY = await scrollContainer.evaluate((el) => el.scrollTop)
      expect(scrollY).toBeGreaterThan(100)
    })
  })

  test.describe('7. shadcn UIの使用', () => {
    test.skip('ソート選択にshadcn Dropdown Menuが使われている', async ({ page }) => {
      // ソートドロップダウンを開く
      await page.click('[data-testid="sort-dropdown-trigger"]')
      await page.waitForTimeout(300)

      // shadcnのDropdownMenuContentが表示される
      await expect(page.locator('[data-testid="sort-dropdown-content"]')).toBeVisible()

      // ソートオプションが表示される
      await expect(page.locator('[data-testid="sort-option-member"]')).toBeVisible()
    })
  })
})
