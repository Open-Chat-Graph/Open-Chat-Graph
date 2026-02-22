import { useMemo } from 'react'
import { Navigate, useParams } from 'react-router-dom'
import OcListMainTabs from '../components/OcListMainTabs'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { Provider, createStore } from 'jotai'
import { useInitStoreFromURL } from '../hooks/ListParamsHooks'
import { useMediaQuery } from '@mui/material'
import OcListMainTabsVertical from '../components/OcListMainTabsVertical'

export default function OCListPage() {
  const { category } = useParams()
  const initStore = useInitStoreFromURL()
  const matches = useMediaQuery('(min-width:600px)') // 599px以下で false

  const store = useMemo(() => {
    const s = createStore()
    initStore(s)
    return s
  }, [initStore])

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
