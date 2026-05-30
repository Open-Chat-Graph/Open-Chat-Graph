import { test, expect } from '@playwright/test'

test.describe('MyListPage Toolbar Spacing', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('http://localhost:5173/')

    // Setup test data in localStorage
    await page.evaluate(() => {
      const testData = {
        version: 1,
        folders: [],
        items: [
          { id: 184, folderId: null, order: 0, addedAt: new Date().toISOString() },
          { id: 29, folderId: null, order: 1, addedAt: new Date().toISOString() },
          { id: 188, folderId: null, order: 2, addedAt: new Date().toISOString() },
        ],
      }
      localStorage.setItem('alpha_mylist', JSON.stringify(testData))
    })

    // Navigate to MyList page
    await page.click('[href="/mylist"]')
    await page.waitForURL('**/mylist')

    // Wait for content to load
    await page.waitForSelector('main')
  })

  test('toolbar and content should have no gap or overlap at scroll position 0', async ({ page }) => {
    // Ensure scroll position is 0
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) {
        container.scrollTo(0, 0)
      }
    })

    // Get measurements
    const measurements = await page.evaluate(() => {
      const toolbar = document.querySelector('[class*="fixed"][class*="top-12"]')
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')
      const firstContentItem = document.querySelector('main div[class*="top-[65px]"] > div > div')

      if (!toolbar || !contentContainer) {
        throw new Error('Required elements not found')
      }

      const toolbarRect = toolbar.getBoundingClientRect()
      const contentRect = contentContainer.getBoundingClientRect()
      const firstItemRect = firstContentItem?.getBoundingClientRect()

      return {
        toolbar: {
          top: toolbarRect.top,
          bottom: toolbarRect.bottom,
          height: toolbarRect.height,
        },
        content: {
          top: contentRect.top,
          scrollTop: (contentContainer as HTMLElement).scrollTop,
        },
        firstItem: firstItemRect ? {
          top: firstItemRect.top,
        } : null,
        gap: contentRect.top - toolbarRect.bottom,
      }
    })

    // Verify scroll position is 0
    expect(measurements.content.scrollTop).toBe(0)

    // Verify no gap between toolbar and content container
    expect(measurements.gap).toBe(0)

    // Verify toolbar doesn't overlap content
    expect(measurements.content.top).toBeGreaterThanOrEqual(measurements.toolbar.bottom)

    // Verify there's adequate spacing between toolbar and first content item
    if (measurements.firstItem) {
      const spacingBetweenToolbarAndFirstItem = measurements.firstItem.top - measurements.toolbar.bottom

      // Should have at least 16px (1rem / pt-4) spacing
      expect(spacingBetweenToolbarAndFirstItem).toBeGreaterThanOrEqual(16)

      // Should not have excessive spacing (less than 32px)
      expect(spacingBetweenToolbarAndFirstItem).toBeLessThan(32)
    }
  })

  test('spacing should remain consistent after scrolling and returning to top', async ({ page }) => {
    // Get initial measurements at scroll 0
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 0)
    })
    await page.waitForTimeout(100)

    const initialMeasurements = await page.evaluate(() => {
      const toolbar = document.querySelector('[class*="fixed"][class*="top-12"]')
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')

      if (!toolbar || !contentContainer) {
        throw new Error('Required elements not found')
      }

      const toolbarRect = toolbar.getBoundingClientRect()
      const contentRect = contentContainer.getBoundingClientRect()

      return {
        gap: contentRect.top - toolbarRect.bottom,
      }
    })

    // Scroll down
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 300)
    })
    await page.waitForTimeout(100)

    // Scroll back to top
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 0)
    })
    await page.waitForTimeout(100)

    // Get measurements after scrolling
    const afterScrollMeasurements = await page.evaluate(() => {
      const toolbar = document.querySelector('[class*="fixed"][class*="top-12"]')
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')

      if (!toolbar || !contentContainer) {
        throw new Error('Required elements not found')
      }

      const toolbarRect = toolbar.getBoundingClientRect()
      const contentRect = contentContainer.getBoundingClientRect()

      return {
        gap: contentRect.top - toolbarRect.bottom,
      }
    })

    // Gap should remain the same
    expect(afterScrollMeasurements.gap).toBe(initialMeasurements.gap)
    expect(afterScrollMeasurements.gap).toBe(0)
  })

  test('content should not overlap toolbar when toolbar is hidden (future-proofing)', async ({ page }) => {
    // Scroll down to potentially trigger toolbar hiding
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 300)
    })
    await page.waitForTimeout(300)

    const measurements = await page.evaluate(() => {
      const toolbar = document.querySelector('[class*="fixed"][class*="top-12"]')
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')

      if (!toolbar || !contentContainer) {
        throw new Error('Required elements not found')
      }

      const toolbarStyle = window.getComputedStyle(toolbar)
      const contentRect = contentContainer.getBoundingClientRect()

      // Check if toolbar has translate transform (hidden state)
      const transform = toolbarStyle.transform
      const isHidden = transform.includes('matrix') && transform.includes('-')

      return {
        isToolbarHidden: isHidden,
        contentTop: contentRect.top,
        // Content should always start at same position regardless of toolbar visibility
        expectedContentTop: 113, // 48px header + 65px toolbar
      }
    })

    // Content position should be consistent whether toolbar is visible or hidden
    expect(measurements.contentTop).toBe(measurements.expectedContentTop)

    // If toolbar hiding is implemented, this documents the behavior
    // (Currently toolbar may hide with -translate-y-full on scroll down)
  })

  test('content container position should be independent of toolbar visibility state', async ({ page }) => {
    // Measure at scroll 0 (toolbar visible)
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 0)
    })
    await page.waitForTimeout(100)

    const visibleState = await page.evaluate(() => {
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')
      if (!contentContainer) throw new Error('Content container not found')
      return contentContainer.getBoundingClientRect().top
    })

    // Scroll down (toolbar may hide)
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 500)
    })
    await page.waitForTimeout(300)

    const hiddenState = await page.evaluate(() => {
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')
      if (!contentContainer) throw new Error('Content container not found')
      return contentContainer.getBoundingClientRect().top
    })

    // Content container should maintain same position
    // (it's absolutely positioned, not affected by toolbar transform)
    expect(hiddenState).toBe(visibleState)
  })

  test('spacing should be correct on mobile viewport', async ({ page }) => {
    // Resize to mobile
    await page.setViewportSize({ width: 375, height: 667 })
    await page.waitForTimeout(500)

    // Ensure scroll position is 0
    await page.evaluate(() => {
      const container = document.querySelector('main div[class*="overflow-y-auto"]')
      if (container) container.scrollTo(0, 0)
    })

    const measurements = await page.evaluate(() => {
      const toolbar = document.querySelector('[class*="fixed"][class*="top-12"]')
      const contentContainer = document.querySelector('main div[class*="top-[65px]"]')
      const firstContentItem = document.querySelector('main div[class*="top-[65px]"] > div > div')

      if (!toolbar || !contentContainer) {
        throw new Error('Required elements not found')
      }

      const toolbarRect = toolbar.getBoundingClientRect()
      const contentRect = contentContainer.getBoundingClientRect()
      const firstItemRect = firstContentItem?.getBoundingClientRect()

      return {
        toolbar: {
          bottom: toolbarRect.bottom,
        },
        content: {
          top: contentRect.top,
        },
        firstItem: firstItemRect ? {
          top: firstItemRect.top,
        } : null,
        gap: contentRect.top - toolbarRect.bottom,
      }
    })

    // Verify no gap
    expect(measurements.gap).toBe(0)

    // Verify adequate spacing to first item
    if (measurements.firstItem) {
      const spacing = measurements.firstItem.top - measurements.toolbar.bottom
      expect(spacing).toBeGreaterThanOrEqual(16)
      expect(spacing).toBeLessThan(32)
    }
  })
})
