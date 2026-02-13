import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'path'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, resolve(__dirname, '../..'), '')
  const target = `https://localhost:${env.HTTPS_PORT || '8443'}`

  return {
    plugins: [react()],
    server: {
      proxy: {
        '/comment': {
          target,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      outDir: '../../public/js/comment',
      emptyOutDir: true,
      rollupOptions: {
        input: 'src/main.tsx',
        output: {
          entryFileNames: 'index-[hash].js',
          assetFileNames: '[name]-[hash][extname]',
        },
      },
    },
  }
})
