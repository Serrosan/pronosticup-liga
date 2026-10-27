import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Identificador de este build: va dentro del código (VITE_VERSION_APP) y en
// /version.json. Si no coinciden, la app abierta es vieja → AvisoVersionNueva.
const VERSION_APP = new Date().toISOString()

function versionApp() {
  return {
    name: 'version-app',
    apply: 'build',
    generateBundle() {
      this.emitFile({ type: 'asset', fileName: 'version.json', source: JSON.stringify({ version: VERSION_APP }) })
    },
  }
}

export default defineConfig({
  plugins: [react(), tailwindcss(), versionApp()],
  define: { 'import.meta.env.VITE_VERSION_APP': JSON.stringify(VERSION_APP) },
  server: {
    watch: { usePolling: true, interval: 300 },
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
  },
})