/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ["class"],
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      /*
       * ===== 重ね順（z-index）の唯一の定義元 =====
       * このアプリは固定ヘッダ・絶対配置パネル・オーバーレイ・portalされる
       * dropdown/dialog を重ねて構成する。重ね順の「意図」をここに集約し、
       * 各コンポーネントは生の z-[NN] ではなく必ずこのトークン名で参照する。
       * 値が小さいほど下。新しい層を足すときはここに意図ごと追記すること。
       *
       *  subheader(10) … in-flowの固定サブヘッダ（MyListツールバー / FolderChartヘッダ / Dialog内stickyの帯）
       *  overlay(50)   … ベースページに被せる全面オーバーレイ（Detail / FolderChart）。
       *                  ※アプリ固定ヘッダ(60)は“あえて”この上＝戻る/タイトルを出すため。
       *                    その分 overlay 側は pt-12（=ヘッダ高 --header-h）でヘッダを避ける。
       *  nav(50)       … モバイル下部ナビ / 一括操作バー（コンテンツの上・ヘッダの下）
       *  header(60)    … アプリ固定ヘッダ＋モバイルサイドバーの暗幕
       *  sidebar(70)   … サイドバー本体（暗幕より上）
       *  popover(75)   … dropdown / select / popover ＝ 必ずヘッダ(60)より上に浮かせる
       *                  （ここを 50 にするとヘッダ下に潜るので不可）
       *  modal(80)     … dialog / RankingHistory 等の最前面オーバーレイ（全層より上）
       */
      zIndex: {
        subheader: '10',
        overlay: '50',
        nav: '50',
        header: '60',
        sidebar: '70',
        popover: '75',
        modal: '80',
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)'
      },
      colors: {
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        primary: {
          DEFAULT: 'hsl(var(--primary))',
          foreground: 'hsl(var(--primary-foreground))'
        },
        secondary: {
          DEFAULT: 'hsl(var(--secondary))',
          foreground: 'hsl(var(--secondary-foreground))'
        },
        destructive: {
          DEFAULT: 'hsl(var(--destructive))',
          foreground: 'hsl(var(--destructive-foreground))'
        },
        muted: {
          DEFAULT: 'hsl(var(--muted))',
          foreground: 'hsl(var(--muted-foreground))'
        },
        accent: {
          DEFAULT: 'hsl(var(--accent))',
          foreground: 'hsl(var(--accent-foreground))'
        },
        popover: {
          DEFAULT: 'hsl(var(--popover))',
          foreground: 'hsl(var(--popover-foreground))'
        },
        card: {
          DEFAULT: 'hsl(var(--card))',
          foreground: 'hsl(var(--card-foreground))'
        }
      }
    }
  },
  plugins: [require("tailwindcss-animate")],
}
