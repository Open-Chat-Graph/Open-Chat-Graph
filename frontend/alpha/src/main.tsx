import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { ThemeProvider } from './providers/theme-provider'

// oc-app グラフに alpha パレット（indigo-violet 系）を適用させるため
// React マウント前に同期的に設定する（useEffect より先に graph-embed が走ることがあるため）
document.documentElement.setAttribute('data-oc-palette', 'alpha')

createRoot(document.getElementById('alpha-root')!).render(
  <StrictMode>
    <ThemeProvider defaultTheme="system" storageKey="openchat-alpha-theme">
      <App />
    </ThemeProvider>
  </StrictMode>,
)
