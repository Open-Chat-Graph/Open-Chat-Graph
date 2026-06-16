import { useEffect, useRef } from 'react'
import { Provider, createStore } from 'jotai'
import { Box } from '@mui/material'
import { analysisParamsState } from '../store/atom'
import { getValidAnalysisParams, useAnalysisJob } from '../hooks/AnalysisHooks'
import AnalysisHeader, { ANALYSIS_HEADER_H, ANALYSIS_MAX_W } from '../components/AnalysisHeader'
import AnalysisToolbar from '../components/AnalysisToolbar'
import FetchAnalysisList from '../components/FetchAnalysisList'
import AnalysisAbout from '../components/AnalysisAbout'

function AnalysisPageInner() {
  const job = useAnalysisJob()

  useEffect(() => {
    document.title = '詳細成長分析｜オプチャグラフ'
    // 共有/ブックマーク用ディープリンク: URL に run=1 があれば自動で分析を実行する
    const sp = new URLSearchParams(window.location.search)
    if (sp.get('run') === '1') {
      job.search(getValidAnalysisParams(sp))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <div className="div-fetchOpenChatRankingList">
      <AnalysisHeader />
      {/* 固定ヘッダー分の余白 */}
      <Box sx={{ height: ANALYSIS_HEADER_H }} />
      <AnalysisToolbar job={job} />
      <Box
        component="main"
        sx={{
          maxWidth: ANALYSIS_MAX_W,
          mx: 'auto',
          px: { xs: 1.5, sm: 2.5 },
          pt: 1.5,
          pb: 8,
          // リスト各行の .overlay-link が右に -2.5rem はみ出す（不可視のクリック領域）。
          // これが横スクロールの原因になるので、見えない overhang だけを切り取る。
          // clip は scroll コンテナを作らないので、上の sticky ツールバーには影響しない。
          overflowX: 'clip',
        }}
      >
        <FetchAnalysisList job={job} />
        <AnalysisAbout />
      </Box>
    </div>
  )
}

export default function AnalysisPage() {
  // ページごと独立した jotai store（URL のクエリで初期化）
  const storeRef = useRef<ReturnType<typeof createStore> | null>(null)
  if (!storeRef.current) {
    const s = createStore()
    s.set(analysisParamsState, getValidAnalysisParams(new URLSearchParams(window.location.search)))
    storeRef.current = s
  }

  return (
    <Provider store={storeRef.current}>
      <AnalysisPageInner />
    </Provider>
  )
}
