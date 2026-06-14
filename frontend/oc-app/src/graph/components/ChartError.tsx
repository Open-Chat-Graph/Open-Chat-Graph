import { Box, Button, Typography } from '@mui/material'
import RefreshIcon from '@mui/icons-material/Refresh'
import { t } from '../util/translation'

/**
 * チャートデータの取得が最終的に失敗（5xxをリトライしても取れない・403等）したときに、
 * 壊れた/空のグラフの代わりにチャート描画領域へ中央表示するエラー表示。
 * 一時的なエラーであることを伝え、ページ再読み込みを促す。
 */
export default function ChartError() {
  return (
    <Box
      className="fade-in"
      sx={{
        position: 'absolute',
        inset: 0,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        textAlign: 'center',
        gap: 1.5,
        px: 2,
      }}
    >
      <Typography variant="subtitle1" sx={{ fontWeight: 600, color: 'var(--c-text-1)' }}>
        {t('データを取得できませんでした')}
      </Typography>
      <Typography
        variant="body2"
        sx={{ color: 'var(--c-text-5)', maxWidth: '22rem', lineHeight: 1.7 }}
      >
        {t('アクセスが集中しているか、一時的な通信エラーの可能性があります。ページを再読み込みしてください。')}
      </Typography>
      <Button
        variant="outlined"
        size="small"
        startIcon={<RefreshIcon />}
        onClick={() => location.reload()}
        sx={{ mt: 0.5 }}
      >
        {t('再読み込み')}
      </Button>
    </Box>
  )
}
