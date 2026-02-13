import { BrowserRouter, Routes, Route } from 'react-router-dom'
import OCListPage from './pages/OCListPage'
import { useEffect } from 'react'
import { basePath } from './config/config'

function RedirectTo404() {
  useEffect(() => {
    window.location.replace('https://openchat-review.me/404')
  }, [])

  return null
}

export const Router = () => {
  return (
    <BrowserRouter>
      <Routes>
        <Route path={basePath} element={<OCListPage />} />
        <Route path={`${basePath}/:category`} element={<OCListPage />} />
        {/* <Route path={`official-ranking/`} element={<OCListPage />} /> */}
        {/* <Route path='official-ranking/:category' element={<OCListPage />} /> */}
        <Route path='*' element={<RedirectTo404 />} />
      </Routes>
    </BrowserRouter>
  )
}
