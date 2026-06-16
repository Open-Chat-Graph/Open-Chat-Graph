import { BrowserRouter, Routes, Route } from 'react-router-dom'
import OCListPage from './pages/OCListPage'
import AnalysisPage from './pages/AnalysisPage'
import { useEffect } from 'react'
import { basePath, analysisPath } from './config/config'

function RedirectTo404() {
  useEffect(() => {
    window.location.replace('/404')
  }, [])

  return null
}

export const Router = () => {
  return (
    <BrowserRouter>
      <Routes>
        <Route path={`${basePath}/:category?`} element={<OCListPage />} />
        <Route path={`${analysisPath}`} element={<AnalysisPage />} />
        <Route path="*" element={<RedirectTo404 />} />
      </Routes>
    </BrowserRouter>
  )
}
