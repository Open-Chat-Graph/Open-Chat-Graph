import path from "path"
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // モノレポ直下の .env から開発サーバーのプロキシ先を解決（frontend/oc-app と同じ流儀）
  const env = loadEnv(mode, path.resolve(__dirname, '../..'), '')
  const target = `https://localhost:${env.HTTPS_PORT || '8443'}`

  return {
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      devOptions: {
        enabled: true, // 開発環境でもPWAを有効化
        type: 'module',
      },
      manifest: {
        name: 'オプチャグラフ',
        short_name: 'オプチャ',
        description: 'LINE OpenChatの統計・ランキング',
        lang: 'ja',
        start_url: mode === 'production' ? '/alpha' : '/',
        scope: mode === 'production' ? '/alpha' : '/',
        display: 'standalone',
        background_color: '#ffffff',
        theme_color: '#10b981',
        icons: [
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-72x72.png' : '/icons/icon-72x72.png',
            sizes: '72x72',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-96x96.png' : '/icons/icon-96x96.png',
            sizes: '96x96',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-128x128.png' : '/icons/icon-128x128.png',
            sizes: '128x128',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-144x144.png' : '/icons/icon-144x144.png',
            sizes: '144x144',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-152x152.png' : '/icons/icon-152x152.png',
            sizes: '152x152',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-192x192.png' : '/icons/icon-192x192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any maskable'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-384x384.png' : '/icons/icon-384x384.png',
            sizes: '384x384',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: mode === 'production' ? '/js/alpha/icons/icon-512x512.png' : '/icons/icon-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any maskable'
          }
        ]
      },
      workbox: {
        // push-sw.js: push イベントと notificationclick を処理する追加スクリプト。
        // public/ に置いてあるためビルドで成果物ディレクトリにコピーされる。
        importScripts: ['push-sw.js'],
        // registerSW.js と index.html はαでは使われない（登録はalpha_content.php インライン、
        // HTMLはPHPが返す）。プリキャッシュから除外して不要なキャッシュを作らない。
        globPatterns: ['**/*.{js,css,ico,png,svg,webmanifest}'],
        globIgnores: ['registerSW.js', 'index.html'],
        navigateFallback: null,
        runtimeCaching: [
          {
            // アプリのページ遷移（/alpha 配下のHTML）: オンライン優先・オフライン時はキャッシュ
            urlPattern: ({ request }) => request.mode === 'navigate',
            handler: 'NetworkFirst',
            options: {
              cacheName: 'alpha-pages',
              expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 },
              cacheableResponse: { statuses: [0, 200] }
            }
          },
          {
            // アプリ本体のJS/CSS/画像（/js/alpha/配下）。
            // index-[hash].js / index-[hash].css とハッシュ付きで URL=内容が不変なので
            // CacheFirst で問題なく長期キャッシュできる（StaleWhileRevalidate のように
            // 古い版を一旦掴んでから裏で更新…という挙動が不要になる）。
            // 内容が変わればハッシュ＝URLが変わり別エントリとして取得される。
            urlPattern: /^.*\/js\/alpha\/.*/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'alpha-assets',
              expiration: { maxEntries: 60, maxAgeSeconds: 60 * 60 * 24 * 7 },
              cacheableResponse: { statuses: [0, 200] }
            }
          }
          // /alpha-api/ と /oc/ の NetworkFirst ルートは削除:
          // SW がリクエストを respondWith で握ると、SW インスタンス終了時に
          // ERR_FAILED で落ちてブラウザ本来の再試行が効かなくなる。
          // ルートが存在しなければリクエストはブラウザ管理になり SW 終了の影響を受けない。
          // /alpha-api/ は SWR が独自にキャッシュ・リトライする。
          // /oc/ は PHP・Preact が直接 fetch するため SW オフロードの恩恵がなく
          // 中断リスクだけが残る。
        ]
      }
    })
  ],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/alpha-api': { target, changeOrigin: true, secure: false },
      '/oc': { target, changeOrigin: true, secure: false },
      '/js': { target, changeOrigin: true, secure: false },
    },
  },
  build: {
    outDir: '../../public/js/alpha',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: 'index-[hash].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'index.css') return 'index-[hash].css'
          return 'assets/[name]-[hash][extname]'
        },
      },
    },
  },
  // 開発環境ではルートパス、本番ビルドでは /js/alpha/
  base: mode === 'production' ? '/js/alpha/' : '/',
  }
})
