import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Dev: `npm run api` (PHP en :8000 sirviendo agusdevpro) + `npm run dev`
// Build: sale directo a agusdevpro/autos-admin → https://agusdevpro.com/autos-admin/
// (no usa /leads para no pisar LeadFinder, que se deploya desde su propio repo)
export default defineConfig(({ mode }) => ({
  base: mode === 'development' ? '/' : '/autos-admin/',
  plugins: [react(), tailwindcss()],
  server: {
    port: 5175,
    proxy: {
      '/api': { target: 'http://localhost:8000', changeOrigin: true },
      '/autos-media': { target: 'http://localhost:8000', changeOrigin: true },
      '/autos': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
  build: {
    outDir: '../agusdevpro/autos-admin',
    emptyOutDir: true,
  },
}))
