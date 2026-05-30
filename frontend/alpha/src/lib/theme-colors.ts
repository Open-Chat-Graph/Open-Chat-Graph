/**
 * アプリケーション全体のカラーテーマ定義
 *
 * このファイルで定義された色は以下で使用されます:
 * - Tailwind CSS (index.css)
 * - Preactグラフ (oc-review-graph)
 * - その他のカスタムコンポーネント
 *
 * 色を変更する場合は、このファイルとindex.cssの両方を更新してください。
 */


/**
 * ライトモードのカラーパレット
 */
export const lightColors = {
  background: '#ffffff',
  foreground: '#0f172a',
  card: '#ffffff',
  cardForeground: '#0f172a',
  border: '#e2e8f0',
  input: '#e2e8f0',
  primary: '#3b82f6',
  primaryForeground: '#f8fafc',
  muted: '#f1f5f9',
  mutedForeground: '#64748b',
  accent: '#f1f5f9',
  accentForeground: '#0f172a',
  ring: '#3b82f6',
} as const

/**
 * ダークモードのカラーパレット (shadcn/ui Slate theme)
 */
export const darkColors = {
  background: '#020817',      // 222.2 84% 4.9%
  foreground: '#f8fafc',      // 210 40% 98%
  card: '#020817',            // 222.2 84% 4.9%
  cardForeground: '#f8fafc',  // 210 40% 98%
  border: '#1e293b',          // 217.2 32.6% 17.5% - カードの枠線
  input: '#1e293b',           // 217.2 32.6% 17.5% - 入力欄の枠線
  primary: '#5b9cf6',         // 217.2 91.2% 59.8% - プライマリカラー
  primaryForeground: '#0c1729', // 222.2 47.4% 11.2%
  muted: '#1e293b',           // 217.2 32.6% 17.5%
  mutedForeground: '#94a3b8', // 215 20.2% 65.1%
  accent: '#1e293b',          // 217.2 32.6% 17.5%
  accentForeground: '#f8fafc', // 210 40% 98%
  ring: '#5b9cf6',            // 217.2 91.2% 59.8%
} as const

/**
 * グラフ用のダークモードカラー
 * Preactグラフ (oc-review-graph) で使用
 */
export const darkGraphColors = {
  // ライングラデーション (shadcn primary blue基準)
  lineGradient: [
    { offset: 1, color: 'rgba(91, 156, 246, 1.0)' },   // primary
    { offset: 0.8, color: 'rgba(76, 139, 245, 1.0)' },
    { offset: 0.5, color: 'rgba(59, 130, 246, 1.0)' },
    { offset: 0.3, color: 'rgba(37, 99, 235, 1.0)' },
    { offset: 0, color: 'rgba(29, 78, 216, 1.0)' },
  ],

  // バーグラデーション
  barGradient: [
    { offset: 1, color: 'rgba(91, 156, 246, 0.3)' },
    { offset: 0.7, color: 'rgba(76, 139, 245, 0.3)' },
    { offset: 0.5, color: 'rgba(59, 130, 246, 0.3)' },
    { offset: 0.3, color: 'rgba(37, 99, 235, 0.3)' },
    { offset: 0, color: 'rgba(29, 78, 216, 0.3)' },
  ],

  grid: '#1e293b',              // border色と同じ
  border: '#1e293b',            // 枠線
  borderWeekly: 'rgba(255,255,255,0)',

  text: {
    primary: '#ffffff',         // datalabel用 - 純白で最大視認性
    secondary: '#94a3b8',       // mutedForeground
    tertiary: '#64748b',        // より暗めのグレー
    saturday: '#7dd3fc',        // sky-300
    sunday: '#fb7185',          // rose-400
    yesterday: '#94a3b8',       // mutedForeground
  },

  pointBackground: '#0f172a',   // ポイントの背景色
} as const

/**
 * グラフ用のライトモードカラー
 */
export const lightGraphColors = {
  lineGradient: [
    { offset: 1, color: 'rgba(0, 183, 96, 1.0)' },
    { offset: 0.8, color: 'rgba(17, 216, 113, 1.0)' },
    { offset: 0.5, color: 'rgba(17, 213, 147, 1.0)' },
    { offset: 0.3, color: 'rgba(18, 207, 205, 1.0)' },
    { offset: 0, color: 'rgba(22, 194, 193, 1.0)' },
  ],

  barGradient: [
    { offset: 1, color: 'rgba(0, 183, 96, 0.2)' },
    { offset: 0.7, color: 'rgba(17, 216, 113, 0.2)' },
    { offset: 0.5, color: 'rgba(17, 213, 147, 0.2)' },
    { offset: 0.3, color: 'rgba(18, 207, 205, 0.2)' },
    { offset: 0, color: 'rgba(22, 194, 193, 0.2)' },
  ],

  grid: '#efefef',
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
} as const

/**
 * MUIボタン用のダークモードスタイル
 */
export const darkMuiButtonStyles = {
  contained: {
    backgroundColor: darkColors.primary,
    color: darkColors.primaryForeground,
    hover: {
      backgroundColor: '#7dd3fc', // sky-300
    },
    disabled: {
      backgroundColor: '#334155', // slate-700
      color: darkColors.mutedForeground,
    },
  },
  outlined: {
    borderColor: '#475569',     // slate-600
    borderWidth: '1.5px',
    color: darkColors.foreground,
    hover: {
      borderColor: darkColors.primary,
      backgroundColor: 'rgba(91, 156, 246, 0.15)',
    },
    disabled: {
      borderColor: '#334155',
      color: '#64748b',
    },
  },
  text: {
    color: darkColors.foreground,
    hover: {
      backgroundColor: 'rgba(148, 163, 184, 0.1)',
    },
  },
  selected: {
    backgroundColor: 'rgba(91, 156, 246, 0.25)',
    color: '#7dd3fc',
    hover: {
      backgroundColor: 'rgba(91, 156, 246, 0.35)',
    },
  },
} as const
