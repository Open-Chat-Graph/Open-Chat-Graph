import { defineConfig, loadEnv } from 'vite'
import preact from '@preact/preset-vite'
import { resolve } from 'path'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, resolve(__dirname, '../..'), '')
  const target = `https://localhost:${env.HTTPS_PORT || '8443'}`

  return {
    plugins: [preact()],
    server: {
      proxy: {
        '/oc': {
          target,
          changeOrigin: true,
          secure: false,
        },
        '/style': {
          target,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      outDir: '../../public/js/chart',
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
