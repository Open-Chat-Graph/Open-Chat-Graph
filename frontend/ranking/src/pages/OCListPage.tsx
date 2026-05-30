import { useRef } from 'react'
import { Navigate, useLocation, useParams } from 'react-router-dom'
import OcListMainTabs from '../components/OcListMainTabs'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { Provider, createStore } from 'jotai'
import { getValidListParams } from '../hooks/ListParamsHooks'
import { listParamsState, keywordState } from '../store/atom'
import { useMediaQuery } from '@mui/material'
import OcListMainTabsVertical from '../components/OcListMainTabsVertical'

export default function OCListPage() {
  const { category } = useParams()
  const location = useLocation()
  const matches = useMediaQuery('(min-width:600px)') // 599px以下で false

  const storeRef = useRef<ReturnType<typeof createStore> | null>(null)
  if (!storeRef.current) {
    const s = createStore()
    const params = getValidListParams(new URLSearchParams(window.location.search), location)
    s.set(listParamsState, params)
    s.set(keywordState, params.keyword)
    storeRef.current = s
  }
  const store = storeRef.current

  const cateIndex =
    typeof category === 'string'
      ? OPEN_CHAT_CATEGORY.findIndex((el) => el[1] === Number(category))
      : 0

  if (cateIndex === -1) {
    return <Navigate to="/404" />
  }

  return (
    <Provider store={store}>
      {matches ? (
        <OcListMainTabsVertical cateIndex={cateIndex} />
      ) : (
        <OcListMainTabs cateIndex={cateIndex} />
      )}
    </Provider>
  )
}
