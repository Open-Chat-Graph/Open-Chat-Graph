import { createRoot } from 'react-dom/client'
import App from './comments/App'

const el = document.getElementById('comment-root')
if (el) createRoot(el).render(<App />)
