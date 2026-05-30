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
        start_url: mode === 'production' ? '/alpha' : '/',
        scope: mode === 'production' ? '/alpha/' : '/',
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
        globPatterns: ['**/*.{js,css,html,ico,png,svg}'],
        runtimeCaching: [
          {
            urlPattern: /^\/alpha-api\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 60 * 60 // 1時間
              },
              cacheableResponse: {
                statuses: [0, 200]
              }
            }
          },
          {
            urlPattern: /^\/oc\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'oc-api-cache',
              expiration: {
                maxEntries: 50,
                maxAgeSeconds: 60 * 60 // 1時間
              },
              cacheableResponse: {
                statuses: [0, 200]
              }
            }
          }
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
        entryFileNames: 'index.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'index.css') return 'index.css'
          return 'assets/[name]-[hash][extname]'
        },
      },
    },
  },
  // 開発環境ではルートパス、本番ビルドでは /js/alpha/
  base: mode === 'production' ? '/js/alpha/' : '/',
  }
})
