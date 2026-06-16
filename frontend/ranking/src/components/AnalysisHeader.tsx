import { Box } from '@mui/material'
import { SiteTitleBtn } from './SiteHeader'

/** 固定ヘッダーの高さ（ツールバーの sticky top・本文スペーサーと共有） */
export const ANALYSIS_HEADER_H = 52

/** 本文カラムの最大幅（サイト共通の --width-card-wide=812 と揃える）。
 *  ヘッダー・ツールバー・リストの内側コンテンツをこの幅で中央寄せして左端を一致させる。 */
export const ANALYSIS_MAX_W = 812

/** ヘッダー/ツールバー/本文で共有する「中央寄せの内側カラム」。左端を全段で揃える。 */
export const innerColumnSx = {
  width: '100%',
  maxWidth: ANALYSIS_MAX_W,
  mx: 'auto',
  px: { xs: 1.5, sm: 2.5 },
} as const

/**
 * オプチャグラフのメインヘッダー（ブランド部のみ）。画面上部に固定し、スクロールでも隠れない。
 * ランキングのカテゴリ/検索導線は持たず、サイトの一員と分かるロゴ＋タイトルだけ。
 * 帯（背景・境界）は全幅、中のロゴは本文と同じ中央カラムに収めて左端を揃える。
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
        borderBottom: '1px solid var(--c-border)',
        background: 'var(--c-bg)',
      }}
    >
      <Box sx={{ ...innerColumnSx, display: 'flex', alignItems: 'center' }}>
        <SiteTitleBtn />
      </Box>
    </Box>
  )
}
