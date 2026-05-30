import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { ThemeProvider } from './providers/theme-provider'

createRoot(document.getElementById('alpha-root')!).render(
  <StrictMode>
    <ThemeProvider defaultTheme="system" storageKey="openchat-alpha-theme">
      <App />
    </ThemeProvider>
  </StrictMode>,
)
