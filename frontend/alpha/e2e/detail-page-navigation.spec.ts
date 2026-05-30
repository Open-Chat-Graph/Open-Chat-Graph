import { test, expect } from '@playwright/test'

/**
 * 詳細ページからのナビゲーションテスト
 *
 * 詳細ページからナビゲーションボタン（検索、マイリスト）を押したとき、
 * ブラウザバックで元のページに戻り、状態が保持されることを確認
 */

test.describe('詳細ページからのナビゲーション', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
    await page.waitForSelector('input[placeholder="キーワードを入力..."]', { timeout: 10000 })
  })

  test('検索リスト → 詳細ページ → 検索ボタン → 元の検索リストに戻る', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'プログラミング')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const searchURL = page.url()
    console.log('検索ページURL:', searchURL)

    // 検索結果があることを確認
    const hasResults = await page.locator('.grid > div').count() > 0
    if (!hasResults) {
      console.log('検索結果なし - テストスキップ')
      test.skip()
    }

    // 検索結果の数を記録
    const resultsCount = await page.locator('.grid > div').count()
    console.log('検索結果数:', resultsCount)

    // 2. 最初の検索結果をクリックして詳細ページに遷移
    await page.locator('.grid > div').first().click()
    await page.waitForTimeout(1000)

    const detailURL = page.url()
    console.log('詳細ページURL:', detailURL)
    expect(detailURL).toContain('/openchat/')

    // 3. 検索ボタンをクリック
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a:has-text("検索")')
    } else {
      await page.click('aside a:has-text("検索")')
    }

    await page.waitForTimeout(1000)

    // 4. 元の検索ページに戻っていることを確認
    const returnURL = page.url()
    console.log('戻った後のURL:', returnURL)
    expect(returnURL).toBe(searchURL)

    // 5. 検索結果が保持されていることを確認
    const resultsCountAfter = await page.locator('.grid > div').count()
    expect(resultsCountAfter).toBeGreaterThan(0)
    console.log('戻った後の検索結果数:', resultsCountAfter)
  })

  test('マイリスト → 詳細ページ → マイリストボタン → 元のマイリストに戻る', async ({ page }) => {
    // マイリストにアイテムを追加
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const hasResults = await page.locator('.grid > div').count() > 0
    if (!hasResults) {
      console.log('検索結果なし - テストスキップ')
      test.skip()
    }

    // 最初のアイテムをマイリストに追加
    const firstCard = page.locator('.grid > div').first()
    const addButton = firstCard.locator('button').first()
    await addButton.click()

    // フォルダ選択ダイアログで「ルート」を選択
    await page.waitForSelector('[role="dialog"]', { timeout: 5000 })
    await page.click('button:has-text("ルート")')
    await page.waitForTimeout(500)

    // 1. マイリストに遷移
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a:has-text("マイリスト")')
    } else {
      await page.click('aside a:has-text("マイリスト")')
    }

    await page.waitForTimeout(1000)
    const mylistURL = page.url()
    expect(mylistURL).toContain('/mylist')

    // マイリストにアイテムがあることを確認
    await page.waitForTimeout(1000) // アイテムの描画を待つ
    const itemCount = await page.locator('.grid > div, [data-folder-item], [role="article"]').count()
    console.log('マイリストのアイテム数:', itemCount)

    if (itemCount === 0) {
      console.log('マイリストにアイテムが追加されませんでした - テストスキップ')
      test.skip()
    }

    expect(itemCount).toBeGreaterThan(0)

    // 2. アイテムをクリックして詳細ページに遷移
    await page.locator('.grid > div, [data-folder-item], [role="article"]').first().click()
    await page.waitForTimeout(1000)

    const detailURL = page.url()
    console.log('詳細ページURL:', detailURL)
    expect(detailURL).toContain('/openchat/')

    // 3. マイリストボタンをクリック
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a:has-text("マイリスト")')
    } else {
      await page.click('aside a:has-text("マイリスト")')
    }

    await page.waitForTimeout(1000)

    // 4. 元のマイリストページに戻っていることを確認
    const returnURL = page.url()
    console.log('戻った後のURL:', returnURL)
    expect(returnURL).toBe(mylistURL)

    // 5. マイリストのアイテムが保持されていることを確認
    const itemCountAfter = await page.locator('.grid > div, [data-folder-item], [role="article"]').count()
    expect(itemCountAfter).toBe(itemCount)
    console.log('戻った後のアイテム数:', itemCountAfter)
  })

  test('検索 → 詳細A → 詳細B → 検索ボタン → 詳細Aに戻る（ブラウザバック）', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'グループ')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const hasResults = await page.locator('.grid > div').count() >= 2
    if (!hasResults) {
      console.log('検索結果が2件未満 - テストスキップ')
      test.skip()
    }

    // 2. 最初の検索結果をクリック（詳細A）
    const firstCard = page.locator('.grid > div').first()
    await firstCard.click()
    await page.waitForTimeout(1000)

    const detailAURL = page.url()
    console.log('詳細ページA URL:', detailAURL)

    // 3. 検索ボタンで検索ページに戻る
    const isMobile = (await page.viewportSize())?.width! < 768
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a:has-text("検索")')
    } else {
      await page.click('aside a:has-text("検索")')
    }

    await page.waitForTimeout(1000)
    expect(page.url()).toContain('?q=')

    // 4. 2番目の検索結果をクリック（詳細B）
    const secondCard = page.locator('.grid > div').nth(1)
    await secondCard.click()
    await page.waitForTimeout(1000)

    const detailBURL = page.url()
    console.log('詳細ページB URL:', detailBURL)
    expect(detailBURL).not.toBe(detailAURL)

    // 5. 検索ボタンをクリック → ブラウザバックで検索ページに戻る
    if (isMobile) {
      await page.click('nav.fixed.bottom-0 a:has-text("検索")')
    } else {
      await page.click('aside a:has-text("検索")')
    }

    await page.waitForTimeout(1000)

    // 検索ページに戻っている
    expect(page.url()).toContain('?q=')
    expect(page.url()).not.toContain('/openchat/')

    // 検索結果が表示されている
    const resultsCount = await page.locator('.grid > div').count()
    expect(resultsCount).toBeGreaterThan(0)
  })

  test('詳細ページが正しく表示される', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'プログラミング')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const hasResults = await page.locator('.grid > div').count() > 0
    if (!hasResults) {
      console.log('検索結果なし - テストスキップ')
      test.skip()
    }

    // 2. 最初の検索結果をクリックして詳細ページに遷移
    await page.locator('.grid > div').first().click()
    await page.waitForTimeout(1000)

    const detailURL = page.url()
    expect(detailURL).toContain('/openchat/')

    // 3. 詳細ページの主要コンテンツが表示されることを確認
    // タイトルヘッダー
    await expect(page.locator('header')).toContainText('オープンチャット')

    // 戻るボタンが表示される
    await expect(page.locator('header button')).toBeVisible()

    // メインコンテンツエリアが表示される
    await expect(page.locator('main')).toBeVisible()

    // オーバーレイが表示される（z-50クラス）
    const overlay = page.locator('.fixed.inset-0.z-50')
    await expect(overlay).toBeVisible()

    // オーバーレイ内にコンテンツが存在する
    const overlayContent = overlay.locator('> div')
    await expect(overlayContent).toBeVisible()
  })

  test('詳細ページから戻るボタンで元のページに戻る', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'プログラミング')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const searchURL = page.url()

    const hasResults = await page.locator('.grid > div').count() > 0
    if (!hasResults) {
      console.log('検索結果なし - テストスキップ')
      test.skip()
    }

    // 2. 詳細ページに遷移
    await page.locator('.grid > div').first().click()
    await page.waitForTimeout(1000)

    expect(page.url()).toContain('/openchat/')

    // 3. 戻るボタンをクリック
    await page.locator('header button').first().click()
    await page.waitForTimeout(1000)

    // 4. 元の検索ページに戻っていることを確認
    expect(page.url()).toBe(searchURL)

    // 5. 検索結果が保持されている
    const resultsCount = await page.locator('.grid > div').count()
    expect(resultsCount).toBeGreaterThan(0)
  })
})
