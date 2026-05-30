import { test, expect } from '@playwright/test'

/**
 * スクロール位置永続化のE2Eテスト
 *
 * 各ページ（検索、マイリスト、設定）は常にDOMに存在し、
 * display制御のみで表示切替を行う。スクロール位置は自動的に保持される。
 */

test.describe('スクロール位置の永続化', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
    await page.waitForSelector('input[placeholder="キーワードを入力..."]', { timeout: 10000 })
  })

  test('検索 → マイリスト → 検索（デスクトップ）', async ({ page }) => {
    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'グループ')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // スクロールダウン
    await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      if (container) container.scrollTo(0, 500)
    })
    await page.waitForTimeout(500)

    const scrollY1 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY1).toBe(500)

    // マイリストに遷移
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')

    // 検索ボタンで戻る
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // スクロール位置が維持されている
    const scrollY2 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY2).toBe(500)

    // 検索結果も維持されている
    const hasResults = await page.evaluate(() => {
      const results = document.querySelector('.grid')
      return results && results.children.length > 0
    })
    expect(hasResults).toBe(true)
  })

  test('検索 → マイリスト → 検索（モバイル）', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })

    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // スクロールダウン
    await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      if (container) container.scrollTo(0, 500)
    })
    await page.waitForTimeout(500)

    const scrollY1 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY1).toBe(500)

    // マイリストに遷移（モバイルナビ）
    await page.click('nav.fixed.bottom-0 a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')

    // 検索ボタンで戻る
    await page.click('nav.fixed.bottom-0 a:has-text("検索")')
    await page.waitForTimeout(1000)

    // スクロール位置が維持されている
    const scrollY2 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY2).toBe(500)

    // 検索結果も維持されている
    const hasResults = await page.evaluate(() => {
      const results = document.querySelector('.grid')
      return results && results.children.length > 0
    })
    expect(hasResults).toBe(true)
  })

  test('検索 → 設定 → 検索', async ({ page }) => {
    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'オープン')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // スクロールダウン
    await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      if (container) container.scrollTo(0, 400)
    })
    await page.waitForTimeout(500)

    const scrollY1 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY1).toBe(400)

    // 設定に遷移
    await page.click('aside a:has-text("設定")')
    await page.waitForURL('**/settings')

    // 検索ボタンで戻る
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // スクロール位置が維持されている
    const scrollY2 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY2).toBe(400)

    // 検索結果も維持されている
    const hasResults = await page.evaluate(() => {
      const results = document.querySelector('.grid')
      return results && results.children.length > 0
    })
    expect(hasResults).toBe(true)
  })

  test('検索ページで検索ボタン → 空の検索にリセット', async ({ page }) => {
    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'リセット')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // スクロールダウン
    await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      if (container) container.scrollTo(0, 300)
    })
    await page.waitForTimeout(500)

    // 検索ページで再び検索ボタンを押す
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // URLがリセットされている
    expect(page.url()).toMatch(/http:\/\/localhost:5173\/js\/alpha\/?$/)

    // 検索フィールドが空
    const searchInput = page.locator('input[placeholder="キーワードを入力..."]')
    await expect(searchInput).toHaveValue('')

    // スクロール位置がリセットされている
    const scrollY = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(scrollY).toBeLessThan(100) // ほぼ0

    // 初期メッセージが表示されている
    await expect(page.locator('text=キーワードを入力して検索してください')).toBeVisible()
  })

  test('マイリスト → 検索 → マイリスト（マイリストのスクロール位置も維持）', async ({ page }) => {
    // まず検索して複数アイテムをマイリストに追加
    await page.fill('input[placeholder="キーワードを入力..."]', 'グループ')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // 5個のアイテムを追加
    for (let i = 0; i < 5; i++) {
      const card = page.locator('.grid > div').nth(i)
      const addButton = card.locator('button').first()
      await addButton.click()
      await page.waitForSelector('[role="dialog"]', { timeout: 5000 })
      await page.click('button:has-text("ルート")')
      await page.waitForTimeout(300)
    }

    // マイリストに遷移
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // マイリストでスクロールダウン
    await page.evaluate(() => {
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const mylistContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      )
      if (mylistContainer) (mylistContainer as HTMLElement).scrollTo(0, 300)
    })
    await page.waitForTimeout(500)

    const mylistScrollY1 = await page.evaluate(() => {
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const mylistContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      )
      return mylistContainer ? (mylistContainer as HTMLElement).scrollTop : 0
    })
    expect(mylistScrollY1).toBe(300)

    // 検索に遷移
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // マイリストに戻る
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // マイリストのスクロール位置が維持されている
    const mylistScrollY2 = await page.evaluate(() => {
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const mylistContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      )
      return mylistContainer ? (mylistContainer as HTMLElement).scrollTop : 0
    })
    expect(mylistScrollY2).toBe(300)
  })

  test.skip('マイリスト → 詳細 → マイリストに戻る（マイリストのスクロール位置を維持）', async ({ page }) => {
    // 検索して複数アイテムをマイリストに追加
    await page.fill('input[placeholder="キーワードを入力..."]', 'テスト')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // 3個のアイテムを追加
    for (let i = 0; i < 3; i++) {
      const card = page.locator('.grid > div').nth(i)
      const addButton = card.locator('button').first()
      await addButton.click()
      await page.waitForSelector('[role="dialog"]', { timeout: 5000 })
      await page.click('button:has-text("ルート")')
      await page.waitForTimeout(300)
    }

    // マイリストに遷移
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // マイリストでスクロールダウン
    await page.evaluate(() => {
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const mylistContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      )
      if (mylistContainer) (mylistContainer as HTMLElement).scrollTo(0, 250)
    })
    await page.waitForTimeout(500)

    const mylistScrollY1 = await page.evaluate(() => {
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const mylistContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      )
      return mylistContainer ? (mylistContainer as HTMLElement).scrollTop : 0
    })
    expect(mylistScrollY1).toBeGreaterThan(150) // スクロールできる範囲で可能な限りスクロールしている

    // マイリストのアイテムをクリックして詳細ページへ
    await page.waitForSelector('.grid > div', { timeout: 10000 })
    const mylistItem = page.locator('.grid > div').first()
    await mylistItem.click()
    await page.waitForURL('**/openchat/**')
    await page.waitForTimeout(500)

    // 詳細ページでスクロールダウン
    await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      if (overlay) (overlay as HTMLElement).scrollTo(0, 400)
    })
    await page.waitForTimeout(500)

    // 戻るボタンでマイリストに戻る
    await page.click('button:has(svg)')  // ArrowLeft button
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(500)

    // マイリストのスクロール位置が維持されている
    const mylistScrollY2 = await page.evaluate(() => {
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const mylistContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      )
      return mylistContainer ? (mylistContainer as HTMLElement).scrollTop : 0
    })
    expect(mylistScrollY2).toBeCloseTo(mylistScrollY1, -1) // 元の位置に近いことを確認（10の位まで）
  })

  test.skip('マイリスト → 詳細 → 検索ボタンで検索に戻る（検索状態を維持）', async ({ page }) => {
    // 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'オープン')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // 検索ページでスクロール
    await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      if (container) (container as HTMLElement).scrollTo(0, 350)
    })
    await page.waitForTimeout(500)

    const searchScrollY1 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(searchScrollY1).toBe(350)

    // アイテムをマイリストに追加
    const card = page.locator('.grid > div').first()
    const addButton = card.locator('button').first()
    await addButton.click()
    await page.waitForSelector('[role="dialog"]', { timeout: 5000 })
    await page.click('button:has-text("ルート")')
    await page.waitForTimeout(300)

    // マイリストに遷移
    await page.click('aside a:has-text("マイリスト")')
    await page.waitForURL('**/mylist')
    await page.waitForTimeout(1000)

    // マイリストのアイテムをクリックして詳細ページへ
    await page.waitForSelector('.grid > div', { timeout: 10000 })
    const mylistItem = page.locator('.grid > div').first()
    await mylistItem.click()
    await page.waitForURL('**/openchat/**')
    await page.waitForTimeout(500)

    // 詳細ページでスクロール
    await page.evaluate(() => {
      const overlay = document.querySelector('.fixed.inset-0.z-50')
      if (overlay) (overlay as HTMLElement).scrollTo(0, 500)
    })
    await page.waitForTimeout(500)

    // 検索ボタンで検索ページに戻る
    await page.click('aside a:has-text("検索")')
    await page.waitForTimeout(1000)

    // 検索ページのスクロール位置が維持されている
    const searchScrollY2 = await page.evaluate(() => {
      const container = document.querySelector('main > div[style*="position: absolute"]')
      return container ? (container as HTMLElement).scrollTop : 0
    })
    expect(searchScrollY2).toBe(350)

    // 検索結果も維持されている
    const hasResults = await page.evaluate(() => {
      const results = document.querySelector('.grid')
      return results && results.children.length > 0
    })
    expect(hasResults).toBe(true)

    // URLにクエリパラメータが残っている
    expect(page.url()).toContain('q=')
  })
})
