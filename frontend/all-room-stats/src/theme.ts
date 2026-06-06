/* グラフ用テーマ（canvas は CSS 変数が効かないため TS 側で判定）。
   切替イベントは public/js/theme.js の 'octhemechange'。 */

export function isDarkMode(): boolean {
  const attr = document.documentElement.getAttribute('data-theme')
  if (attr === 'dark') return true
  if (attr === 'light') return false
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function onThemeChange(cb: () => void): void {
  document.addEventListener('octhemechange', cb)
  window.matchMedia?.('(prefers-color-scheme: dark)')?.addEventListener?.('change', cb)
}

/** 軸・タイトル・データラベルの文字色 */
export function labelColor(): string {
  return isDarkMode() ? '#8b9196' /* X風ニュートラル */ : '#374151'
}
