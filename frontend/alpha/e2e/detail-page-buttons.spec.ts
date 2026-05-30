import { test, expect } from '@playwright/test'

test.describe('詳細ページのボタン表示テスト', () => {
  const testDetailPageButtons = async (page: any, viewportName: string) => {
    // 検索実行
    const searchInput = viewportName === 'mobile'
      ? page.locator('input[placeholder="キーワードを入力..."]').first()
      : page.locator('input[placeholder="キーワードを入力..."]').first()

    await searchInput.fill('就活')
    await searchInput.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)

    // ページが完全に読み込まれるまで待機
    await page.waitForTimeout(1000)

    // 「LINEで開く」ボタンまでスクロール
    const lineButton = page.locator('[data-testid="line-open-button"]')
    await lineButton.scrollIntoViewIfNeeded()

    // 「LINEで開く」ボタンが表示されることを確認
    await expect(lineButton).toBeVisible({ timeout: 10000 })
    await expect(lineButton).toContainText('LINEで開く')
    console.log(`✅ [${viewportName}] LINEで開くボタンが表示されました`)

    // 「マイリストに追加」ボタンが表示されることを確認
    const mylistAddButton = page.locator('[data-testid="mylist-add-button"]')
    await expect(mylistAddButton).toBeVisible({ timeout: 10000 })
    await expect(mylistAddButton).toContainText('マイリストに追加')
    console.log(`✅ [${viewportName}] マイリストに追加ボタンが表示されました`)

    // ボタンがDOMに存在し、クリック可能であることを確認
    const lineButtonBox = await lineButton.boundingBox()
    const mylistButtonBox = await mylistAddButton.boundingBox()

    expect(lineButtonBox).not.toBeNull()
    expect(mylistButtonBox).not.toBeNull()

    if (lineButtonBox && mylistButtonBox) {
      console.log(`✅ [${viewportName}] LINEボタン位置: y=${lineButtonBox.y}, height=${lineButtonBox.height}`)
      console.log(`✅ [${viewportName}] マイリストボタン位置: y=${mylistButtonBox.y}, height=${mylistButtonBox.height}`)
    }
  }

  test('PC幅で詳細ページのボタンが表示される', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto('/')
    await testDetailPageButtons(page, 'PC')
  })

  test('スマホ幅で詳細ページのボタンが表示される', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 })
    await page.goto('/')
    await testDetailPageButtons(page, 'mobile')
  })

  test('タブレット幅で詳細ページのボタンが表示される', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 })
    await page.goto('/')
    await testDetailPageButtons(page, 'tablet')
  })

  test.skip('スマホ幅でマイリストに追加→登録済みボタンに変わる', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 })
    await page.goto('/')

    // 検索実行
    const searchInput = page.locator('input[placeholder="キーワードを入力..."]').first()
    await searchInput.fill('就活')
    await searchInput.press('Enter')
    await page.waitForSelector('h3')

    // 詳細ページに遷移
    await page.locator('h3').first().click()
    await page.waitForURL(/\/openchat\/\d+/)
    await page.waitForTimeout(1000)

    // 「マイリストに追加」ボタンをクリック
    const mylistAddButton = page.locator('[data-testid="mylist-add-button"]')
    await expect(mylistAddButton).toBeVisible()
    await mylistAddButton.click()

    // フォルダ選択ダイアログが表示される
    await page.waitForSelector('text=マイリストに追加')

    // 「未分類」を選択
    const uncategorizedButton = page.locator('button:has-text("未分類")').first()
    await uncategorizedButton.click()

    // ボタンが「マイリスト登録済み」に変わることを確認
    const mylistAddedButton = page.locator('[data-testid="mylist-added-button"]')
    await expect(mylistAddedButton).toBeVisible({ timeout: 5000 })
    await expect(mylistAddedButton).toContainText('マイリスト登録済み')
    console.log('✅ [mobile] マイリストに追加後、登録済みボタンに変わりました')
  })
})
