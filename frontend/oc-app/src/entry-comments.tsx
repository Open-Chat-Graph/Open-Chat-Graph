import { createRoot } from 'react-dom/client'
import App from './comments/App'
import { OcThemeProvider } from './themeMui'

const el = document.getElementById('comment-root')
if (el)
  createRoot(el).render(
    <OcThemeProvider>
      <App />
    </OcThemeProvider>
  )
