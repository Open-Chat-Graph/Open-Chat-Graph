import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, resolve(__dirname, '../..'), '')
  const target = `https://localhost:${env.HTTPS_PORT || '8443'}`

  return {
    plugins: [react()],
    server: {
      proxy: {
        '/oc': { target, changeOrigin: true, secure: false },
        '/style': { target, changeOrigin: true, secure: false },
        '/comment': { target, changeOrigin: true, secure: false },
        '/comment-img': { target, changeOrigin: true, secure: false },
        '/comment_image_report': { target, changeOrigin: true, secure: false },
      },
    },
    build: {
      outDir: '../../public/js/oc-app',
      emptyOutDir: true,
      chunkSizeWarningLimit: 600,
      rollupOptions: {
        input: {
          graph: resolve(__dirname, 'src/entry-graph.tsx'),
          comments: resolve(__dirname, 'src/entry-comments.tsx'),
        },
        output: {
          entryFileNames: '[name]-[hash].js',
          chunkFileNames: 'chunks/[name]-[hash].js',
          assetFileNames: '[name]-[hash][extname]',
          manualChunks(id) {
            if (id.includes('node_modules/react-dom/') || id.includes('node_modules/react/')) {
              return 'vendor-react'
            }
            if (
              id.includes('node_modules/@mui/') ||
              id.includes('node_modules/@emotion/')
            ) {
              return 'vendor-mui'
            }
            if (
              id.includes('node_modules/chart.js/') ||
              id.includes('node_modules/chartjs-') ||
              id.includes('node_modules/luxon/')
            ) {
              return 'vendor-chartjs'
            }
          },
        },
      },
    },
  }
})
