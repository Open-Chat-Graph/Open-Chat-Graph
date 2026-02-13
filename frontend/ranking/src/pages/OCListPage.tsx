import React from 'react'
import { Navigate, useParams } from 'react-router-dom'
import OcListMainTabs from '../components/OcListMainTabs'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { RecoilRoot } from 'recoil'
import { useGetInitListParamsState } from '../hooks/ListParamsHooks'
import { useMediaQuery } from '@mui/material'
import OcListMainTabsVertical from '../components/OcListMainTabsVertical'

export default function OCListPage() {
  const { category } = useParams()
  const initializeState = useGetInitListParamsState()
  const matches = useMediaQuery('(min-width:600px)') // 599px以下で false

  const cateIndex = typeof category === 'string' ? OPEN_CHAT_CATEGORY.findIndex((el) => el[1] === Number(category)) : 0

  if (cateIndex === -1) {
    return <Navigate to="/404" />
  }

  return (
    <RecoilRoot initializeState={initializeState}>
      {matches ? <OcListMainTabsVertical cateIndex={cateIndex} /> : <OcListMainTabs cateIndex={cateIndex} />}
    </RecoilRoot>
  )
}
