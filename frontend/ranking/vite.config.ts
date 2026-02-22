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
        '/th': { target, changeOrigin: true, secure: false },
        '/tw': { target, changeOrigin: true, secure: false },
        '/oc': { target, changeOrigin: true, secure: false },
        '/oclist': { target, changeOrigin: true, secure: false },
        '/search': { target, changeOrigin: true, secure: false },
        '/style': { target, changeOrigin: true, secure: false },
        '/assets': { target, changeOrigin: true, secure: false },
      },
    },
    build: {
      outDir: '../../public/js/react',
      emptyOutDir: false,
      cssCodeSplit: true,
      rollupOptions: {
        input: 'src/main.tsx',
        output: {
          entryFileNames: 'main-[hash].js',
          assetFileNames: 'main-[hash][extname]',
        },
      },
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['src/test/setup.ts'],
      globals: true,
    },
  }
})
