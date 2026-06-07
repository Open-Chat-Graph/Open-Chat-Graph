import ReactDOM from 'react-dom/client'
import App from './App'
import { OcThemeProvider } from './theme'

const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement)
root.render(
  <OcThemeProvider>
    <App />
  </OcThemeProvider>
)
