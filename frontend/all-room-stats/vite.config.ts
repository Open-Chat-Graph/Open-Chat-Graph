import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    outDir: '../../public/js/all-room-stats',
    emptyOutDir: true,
    rollupOptions: {
      input: 'src/main.ts',
      output: {
        entryFileNames: 'index-[hash].js',
        assetFileNames: '[name]-[hash][extname]',
      },
    },
  },
})
