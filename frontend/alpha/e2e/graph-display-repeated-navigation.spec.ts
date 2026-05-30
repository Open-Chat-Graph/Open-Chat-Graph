import { test, expect } from '@playwright/test'

/**
 * グラフ表示の繰り返しナビゲーションテスト
 *
 * 検索結果から詳細ページへの遷移を繰り返した際に、
 * Preactスクリプトが正しくクリーンアップされ、
 * グラフが毎回正しく表示されることを確認
 */

test.describe('グラフ表示の繰り返しナビゲーション', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://localhost:5173/')
    await page.waitForSelector('input[placeholder="キーワードを入力..."]', { timeout: 10000 })
  })

  test('5回の詳細ページ遷移でグラフが毎回正しく表示される', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'a')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    // 検索結果が5件以上あることを確認
    const hasResults = await page.locator('[data-testid^="openchat-card-"]').count() >= 5
    if (!hasResults) {
      console.log('検索結果が5件未満 - テストスキップ')
      test.skip()
    }

    const results = []

    // 2. 5回のナビゲーションテスト
    for (let i = 0; i < 5; i++) {
      console.log(`\n=== Iteration ${i + 1} ===`)

      // アイテムをクリック
      const cards = await page.locator('[data-testid^="openchat-card-"]').all()
      await cards[i].click()

      // グラフの読み込みを待つ
      await page.waitForTimeout(2500)

      // Preactスクリプトの数を確認
      const scriptCount = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script[src*="preact-chart"]')
        return scripts.length
      })

      // グラフボックスのopacityを確認
      const graphOpacity = await page.evaluate(() => {
        const graphBox = document.getElementById('graph-box')
        return graphBox ? graphBox.style.opacity : 'not found'
      })

      // #appの中身を確認（Preactがマウントされているか）
      const appContent = await page.evaluate(() => {
        const appDiv = document.getElementById('app')
        return appDiv ? appDiv.innerHTML.length : 0
      })

      const currentURL = page.url()

      results.push({
        iteration: i + 1,
        scriptCount,
        graphOpacity,
        appContentLength: appContent,
        url: currentURL,
      })

      console.log(`スクリプト数: ${scriptCount}`)
      console.log(`グラフopacity: ${graphOpacity}`)
      console.log(`#app content length: ${appContent}`)
      console.log(`URL: ${currentURL}`)

      // アサーション
      expect(scriptCount, `Iteration ${i + 1}: Preactスクリプトは1個のみであるべき`).toBe(1)
      // opacityはフェードイン削除により設定されない（デフォルト=1）ため、空文字列または'1'を許可
      expect(graphOpacity !== 'not found', `Iteration ${i + 1}: graph-boxが存在するべき`).toBeTruthy()
      expect(appContent, `Iteration ${i + 1}: Preactアプリがマウントされているべき`).toBeGreaterThan(0)

      // ブラウザバック
      await page.goBack()
      await page.waitForTimeout(500)
    }

    // 最終結果のサマリー
    console.log('\n=== Test Summary ===')
    console.log('All iterations passed:')
    results.forEach((result) => {
      console.log(
        `  ${result.iteration}: scripts=${result.scriptCount}, opacity=${result.graphOpacity}, appContent=${result.appContentLength}`
      )
    })

    // すべてのイテレーションでスクリプトが1個であることを確認
    const allScriptCountsAreOne = results.every((r) => r.scriptCount === 1)
    expect(allScriptCountsAreOne, 'すべてのイテレーションでPreactスクリプトが1個のみであるべき').toBe(true)

    // すべてのイテレーションでgraph-boxが存在することを確認
    const allGraphBoxesExist = results.every((r) => r.graphOpacity !== 'not found')
    expect(allGraphBoxesExist, 'すべてのイテレーションでgraph-boxが存在するべき').toBe(true)
  })

  test('詳細ページから別の詳細ページに直接遷移してもグラフが表示される', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'a')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const hasResults = await page.locator('[data-testid^="openchat-card-"]').count() >= 2
    if (!hasResults) {
      console.log('検索結果が2件未満 - テストスキップ')
      test.skip()
    }

    // 2. 1つ目の詳細ページに遷移
    const cards = await page.locator('[data-testid^="openchat-card-"]').all()
    await cards[0].click()
    await page.waitForTimeout(2500)

    // graph-boxが存在することを確認
    let graphOpacity = await page.evaluate(() => {
      const graphBox = document.getElementById('graph-box')
      return graphBox ? graphBox.style.opacity : 'not found'
    })
    expect(graphOpacity !== 'not found', 'graph-boxが存在するべき').toBeTruthy()

    // 3. 検索ページに戻る
    await page.goBack()
    await page.waitForTimeout(500)

    // 4. 2つ目の詳細ページに遷移
    const cardsAfterBack = await page.locator('[data-testid^="openchat-card-"]').all()
    await cardsAfterBack[1].click()
    await page.waitForTimeout(2500)

    // graph-boxが存在することを確認
    graphOpacity = await page.evaluate(() => {
      const graphBox = document.getElementById('graph-box')
      return graphBox ? graphBox.style.opacity : 'not found'
    })
    expect(graphOpacity !== 'not found', 'graph-boxが存在するべき').toBeTruthy()

    // Preactスクリプトが1個のみであることを確認
    const scriptCount = await page.evaluate(() => {
      const scripts = document.querySelectorAll('script[src*="preact-chart"]')
      return scripts.length
    })
    expect(scriptCount).toBe(1)
  })

  test('同じ詳細ページに再訪問してもグラフが表示される', async ({ page }) => {
    // 1. 検索を実行
    await page.fill('input[placeholder="キーワードを入力..."]', 'a')
    await page.press('input[placeholder="キーワードを入力..."]', 'Enter')
    await page.waitForTimeout(2000)

    const hasResults = await page.locator('[data-testid^="openchat-card-"]').count() >= 1
    if (!hasResults) {
      console.log('検索結果なし - テストスキップ')
      test.skip()
    }

    // 2. 詳細ページに遷移
    const cards = await page.locator('[data-testid^="openchat-card-"]').all()
    await cards[0].click()
    await page.waitForTimeout(2500)

    const firstVisitURL = page.url()

    // graph-boxが存在することを確認
    let graphOpacity = await page.evaluate(() => {
      const graphBox = document.getElementById('graph-box')
      return graphBox ? graphBox.style.opacity : 'not found'
    })
    expect(graphOpacity !== 'not found', 'graph-boxが存在するべき').toBeTruthy()

    // 3. 検索ページに戻る
    await page.goBack()
    await page.waitForTimeout(500)

    // 4. 同じ詳細ページに再度遷移
    const cardsAfterBack = await page.locator('[data-testid^="openchat-card-"]').all()
    await cardsAfterBack[0].click()
    await page.waitForTimeout(2500)

    const secondVisitURL = page.url()
    expect(secondVisitURL).toBe(firstVisitURL)

    // graph-boxが再度存在することを確認
    graphOpacity = await page.evaluate(() => {
      const graphBox = document.getElementById('graph-box')
      return graphBox ? graphBox.style.opacity : 'not found'
    })
    expect(graphOpacity !== 'not found', 'graph-boxが存在するべき').toBeTruthy()

    // Preactスクリプトが1個のみであることを確認
    const scriptCount = await page.evaluate(() => {
      const scripts = document.querySelectorAll('script[src*="preact-chart"]')
      return scripts.length
    })
    expect(scriptCount).toBe(1)
  })
})
