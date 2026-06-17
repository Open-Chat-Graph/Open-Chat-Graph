/* ============================================================
   グラフ（Chart.js / canvas）用カラーテーマの単一ソース
   ------------------------------------------------------------
   - canvas には CSS 変数が届かないため、色はこの TS パレットが一次ソース。
     サイト側 DOM の色は public/style/tokens.css（相互参照: 値の整合を保つこと）。
   - ダーク値の正は旧試験実装 Open-Chat-Graph-Frontend-Stats-Graph の
     src/util/theme.ts（グラフ内・MUI まで調整済み）。値を変えずに移植。
     shadcn/ui Slate 準拠: grid #334155 / border #475569 /
     text #e2e8f0 / #94a3b8 / saturday sky-300 / sunday rose-400。
   - テーマ判定はライブ参照（キャッシュしない）。切替は public/js/theme.js が
     document へ 'octhemechange' を発火する。
   ============================================================ */

export function isDarkMode(): boolean {
  const attr = document.documentElement.getAttribute('data-theme')
  if (attr === 'dark') return true
  if (attr === 'light') return false
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
}

/** テーマ変更の購読（octhemechange + OS 設定変化）。戻り値で解除 */
export function onThemeChange(cb: (isDark: boolean) => void): () => void {
  const handler = () => cb(isDarkMode())
  document.addEventListener('octhemechange', handler)
  const mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null
  mq?.addEventListener?.('change', handler)
  return () => {
    document.removeEventListener('octhemechange', handler)
    mq?.removeEventListener?.('change', handler)
  }
}

type GradientStop = { offset: number; color: string }

export interface ChartColors {
  lineGradient: { stops: GradientStop[] }
  barGradient: { stops: GradientStop[] }
  /** グリッド線（縦＝日付・横＝順位/メンバー共通）。控えめな中立色 */
  grid: string
  border: string
  borderWeekly: string
  text: {
    primary: string
    secondary: string
    tertiary: string
    saturday: string
    sunday: string
    yesterday: string
  }
  pointBackground: string
  tooltipBackground: string
  tooltipBorder: string
  verticalLine: string
  /** ローソク足（メンバー数） */
  candle: {
    up: string
    down: string
    unchanged: string
    bg: { up: string; down: string; unchanged: string }
    borders: { up: string; down: string; unchanged: string }
  }
  /** ローソク足（ランキング・第2軸） */
  candleRank: {
    color: { up: string; down: string; unchanged: string }
    bg: { up: string; down: string; unchanged: string }
    borders: { up: string; down: string; unchanged: string }
  }
  /** candlestick モードの凡例スウォッチ [メンバー, ランキング] */
  legendCandle: [string, string]
  /** データなしメッセージ等の控えめな文字 */
  watermark: string
}

export const colors: { light: ChartColors; dark: ChartColors } = {
  light: {
    lineGradient: {
      stops: [
        { offset: 1, color: 'rgba(0, 183, 96, 1.0)' },
        { offset: 0.8, color: 'rgba(17, 216, 113, 1.0)' },
        { offset: 0.5, color: 'rgba(17, 213, 147, 1.0)' },
        { offset: 0.3, color: 'rgba(18, 207, 205, 1.0)' },
        { offset: 0, color: 'rgba(22, 194, 193, 1.0)' },
      ],
    },
    barGradient: {
      stops: [
        { offset: 1, color: 'rgba(0, 183, 96, 0.45)' },
        { offset: 0.7, color: 'rgba(17, 216, 113, 0.45)' },
        { offset: 0.5, color: 'rgba(17, 213, 147, 0.45)' },
        { offset: 0.3, color: 'rgba(18, 207, 205, 0.45)' },
        { offset: 0, color: 'rgba(22, 194, 193, 0.45)' },
      ],
    },
    grid: '#f7f7f7',
    border: '#efefef',
    borderWeekly: 'rgba(0,0,0,0)',
    text: {
      primary: '#111',
      secondary: '#777',
      tertiary: '#aaa',
      saturday: '#44617B',
      sunday: '#9C3848',
      yesterday: '#b7b7b7',
    },
    pointBackground: '#fff',
    tooltipBackground: 'rgba(255, 255, 255, 0.95)',
    tooltipBorder: '#ccc',
    verticalLine: 'rgba(0, 0, 0, 0.3)',
    candle: {
      up: '#00c853',
      down: '#ff1744',
      unchanged: '#757575',
      bg: {
        up: 'rgba(0, 200, 83, 0.5)',
        down: 'rgba(255, 23, 68, 0.5)',
        unchanged: 'rgba(117, 117, 117, 0.5)',
      },
      borders: {
        up: 'rgba(0, 200, 83, 0.7)',
        down: 'rgba(255, 23, 68, 0.7)',
        unchanged: 'rgba(117, 117, 117, 0.7)',
      },
    },
    candleRank: {
      color: {
        up: 'rgba(41, 121, 255, 0.3)',
        down: 'rgba(255, 109, 0, 0.3)',
        unchanged: 'rgba(158, 158, 158, 0.3)',
      },
      bg: {
        up: 'rgba(41, 121, 255, 0.08)',
        down: 'rgba(255, 109, 0, 0.08)',
        unchanged: 'rgba(158, 158, 158, 0.08)',
      },
      borders: {
        up: 'rgba(41, 121, 255, 0.3)',
        down: 'rgba(255, 109, 0, 0.3)',
        unchanged: 'rgba(158, 158, 158, 0.3)',
      },
    },
    legendCandle: ['#00c853', 'rgba(41, 121, 255, 0.35)'],
    watermark: '#888',
  },
  dark: {
    /* 線・棒グラデは旧実装どおり同色相で α を調整（ダークでは発光しすぎないように） */
    lineGradient: {
      stops: [
        { offset: 1, color: 'rgba(0, 183, 96, 0.85)' },
        { offset: 0.8, color: 'rgba(17, 216, 113, 0.85)' },
        { offset: 0.5, color: 'rgba(17, 213, 147, 0.85)' },
        { offset: 0.3, color: 'rgba(18, 207, 205, 0.85)' },
        { offset: 0, color: 'rgba(22, 194, 193, 0.85)' },
      ],
    },
    barGradient: {
      stops: [
        { offset: 1, color: 'rgba(0, 183, 96, 0.5)' },
        { offset: 0.7, color: 'rgba(17, 216, 113, 0.5)' },
        { offset: 0.5, color: 'rgba(17, 213, 147, 0.5)' },
        { offset: 0.3, color: 'rgba(18, 207, 205, 0.5)' },
        { offset: 0, color: 'rgba(22, 194, 193, 0.5)' },
      ],
    },
    grid: '#1e2127' /* 縦横共通の控えめなグリッド: 青みを抑えた中立色で薄く */,
    border: '#475569' /* slate-600 */,
    borderWeekly: '#334155',
    text: {
      primary: '#e2e8f0' /* slate-200 */,
      secondary: '#94a3b8' /* slate-400 */,
      tertiary: '#94a3b8',
      saturday: '#7dd3fc' /* sky-300 */,
      sunday: '#fb7185' /* rose-400 */,
      yesterday: '#94a3b8',
    },
    pointBackground: 'transparent',
    tooltipBackground: 'rgba(30, 41, 59, 0.95)' /* slate-800 */,
    tooltipBorder: '#475569',
    verticalLine: '#cbd5e1' /* slate-300 */,
    /* 旧実装に無い現行機能の補完（要調整: 仕上げPRの実画面確認で確定） */
    candle: {
      up: '#00c853',
      down: '#ff1744',
      unchanged: '#94a3b8',
      bg: {
        up: 'rgba(0, 200, 83, 0.45)',
        down: 'rgba(255, 23, 68, 0.45)',
        unchanged: 'rgba(148, 163, 184, 0.4)',
      },
      borders: {
        up: 'rgba(0, 200, 83, 0.8)',
        down: 'rgba(255, 23, 68, 0.8)',
        unchanged: 'rgba(148, 163, 184, 0.7)',
      },
    },
    candleRank: {
      color: {
        up: 'rgba(122, 154, 255, 0.45)',
        down: 'rgba(255, 145, 60, 0.45)',
        unchanged: 'rgba(148, 163, 184, 0.35)',
      },
      bg: {
        up: 'rgba(122, 154, 255, 0.12)',
        down: 'rgba(255, 145, 60, 0.12)',
        unchanged: 'rgba(148, 163, 184, 0.1)',
      },
      borders: {
        up: 'rgba(122, 154, 255, 0.45)',
        down: 'rgba(255, 145, 60, 0.45)',
        unchanged: 'rgba(148, 163, 184, 0.35)',
      },
    },
    legendCandle: ['#00c853', 'rgba(122, 154, 255, 0.5)'],
    watermark: '#94a3b8',
  },
}

export function getColors(): ChartColors {
  return isDarkMode() ? colors.dark : colors.light
}
