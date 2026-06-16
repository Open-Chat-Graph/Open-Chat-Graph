import { Box } from '@mui/material'
import { SiteTitleBtn } from './SiteHeader'

/** 固定ヘッダーの高さ（ツールバーの sticky top・本文スペーサーと共有） */
export const ANALYSIS_HEADER_H = 52

/**
 * オプチャグラフのメインヘッダー（ブランド部のみ）。画面上部に固定し、スクロールでも隠れない。
 * ランキングのカテゴリ/検索導線は持たず、サイトの一員と分かるロゴ＋タイトルだけ。
 */
export default function AnalysisHeader() {
  return (
    <Box
      component="header"
      sx={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        height: ANALYSIS_HEADER_H,
        zIndex: 1300,
        display: 'flex',
        alignItems: 'center',
        px: { xs: 1.5, sm: 2.5 },
        borderBottom: '1px solid var(--c-border)',
        background: 'var(--c-bg)',
      }}
    >
      <SiteTitleBtn />
    </Box>
  )
}
