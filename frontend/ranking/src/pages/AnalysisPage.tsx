import { useEffect, useMemo, useRef, type ReactNode } from 'react'
import { Provider, createStore } from 'jotai'
import { Box, ThemeProvider, createTheme, useTheme } from '@mui/material'
import { analysisParamsState } from '../store/atom'
import { getValidAnalysisParams, useAnalysisJob } from '../hooks/AnalysisHooks'
import AnalysisHeader from '../components/AnalysisHeader'
import AnalysisToolbar from '../components/AnalysisToolbar'
import FetchAnalysisList from '../components/FetchAnalysisList'

// サイト本文と同じフォント。MUI の Select メニューや Popover は document.body に portal され
// .div-fetchOpenChatRankingList の font 指定が効かないため、theme の typography で揃える。
const SITE_FONT =
  "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"

function AnalysisThemeProvider({ children }: { children: ReactNode }) {
  const base = useTheme()
  const theme = useMemo(
    () =>
      createTheme(base, {
        typography: { fontFamily: SITE_FONT },
        components: {
          // iOS Safari は font-size<16px の入力で自動ズームするため 16px に上げる
          MuiInputBase: { styleOverrides: { input: { fontSize: 16 } } },
          MuiOutlinedInput: { styleOverrides: { input: { fontSize: 16 } } },
          MuiSelect: { styleOverrides: { select: { fontSize: 16 } } },
        },
      }),
    [base]
  )
  return <ThemeProvider theme={theme}>{children}</ThemeProvider>
}

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

  // div-fetchOpenChatRankingList: サイトのフォント(system-ui系)を配下全体に適用する既存クラス
  return (
    <div className="div-fetchOpenChatRankingList">
      <AnalysisHeader />
      <AnalysisToolbar job={job} />
      <Box
        component="main"
        sx={{ maxWidth: 1040, mx: 'auto', px: { xs: 1.75, sm: 2.5 }, pt: 1.5, pb: 8 }}
      >
        <FetchAnalysisList job={job} />
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
      <AnalysisThemeProvider>
        <AnalysisPageInner />
      </AnalysisThemeProvider>
    </Provider>
  )
}
