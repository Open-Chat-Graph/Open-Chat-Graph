import { Box, Button, Typography } from '@mui/material'
import RefreshIcon from '@mui/icons-material/Refresh'

// 5xx・ネットワークエラー時に出すインライン再読み込みUI。
// ボタン押下でページ全体ではなく、コメント一覧のデータだけ取り直す(onReload)
export default function ReloadErrorBlock({ onReload }: { onReload: () => void }) {
  return (
    <Box
      role="alert"
      sx={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: '0.75rem',
        textAlign: 'center',
        py: 3,
        px: 2,
      }}
    >
      <Typography sx={{ fontSize: '0.9rem', color: 'text.secondary', lineHeight: 1.5 }}>
        一時的に読み込めませんでした。時間をおいて再読み込みしてください。
      </Typography>
      <Button
        variant="outlined"
        onClick={onReload}
        startIcon={<RefreshIcon />}
        sx={{ minHeight: '44px', borderRadius: '99rem' }}
      >
        再読み込み
      </Button>
    </Box>
  )
}
