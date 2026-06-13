// dataLayer 経由で GTM にカスタムイベントを送る。測定先(本番/STG)の出し分けは GTM 側(hostName ルックアップ)で行うので、ここではホスト判定しない。
export function trackEvent(name: string, params?: Record<string, unknown>): void {
  try {
    const w = window as unknown as { dataLayer?: Record<string, unknown>[] }
    w.dataLayer = w.dataLayer || []
    w.dataLayer.push({ event: name, ...(params || {}) })
  } catch {
    /* noop */
  }
}
