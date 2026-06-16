import { Box } from '@mui/material'
import { SiteTitleBtn } from './SiteHeader'

/**
 * オプチャグラフのメインヘッダー（ブランド部のみ）。
 * ランキングのヘッダーからカテゴリ/検索などの導線は外し、サイトの一員だと分かるロゴ＋タイトルだけを置く。
 * 通常フロー（スクロールで流れる）。下のツールバーが sticky で上に残る。
 */
export default function AnalysisHeader() {
  return (
    <Box
      component="header"
      sx={{
        display: 'flex',
        alignItems: 'center',
        px: { xs: 1.5, sm: 2 },
        py: 0.75,
        borderBottom: '1px solid var(--c-border)',
        background: 'var(--c-bg)',
      }}
    >
      <SiteTitleBtn />
    </Box>
  )
}
