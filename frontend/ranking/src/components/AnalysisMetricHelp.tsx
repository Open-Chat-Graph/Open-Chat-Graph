import { Box, Typography } from '@mui/material'
import OCListDescPopover from './OCListDescPopover'

/**
 * 指標の意味を「i」ボタンで分かりやすく説明する（ランキングの i ボタンと同じ作法）。
 * 表示中の指標に合わせて文面を出し分ける。
 */
export default function AnalysisMetricHelp({ metric }: { metric: AnalysisMetric }) {
  return (
    <OCListDescPopover>
      <Box sx={{ maxWidth: 290, fontSize: 13.5, lineHeight: 1.75 }}>
        {metric === 'steady' ? (
          <>
            <Typography sx={{ fontWeight: 700, fontSize: 14, mb: 0.5 }}>じわじわ成長とは</Typography>
            数年かけて <b>コツコツ増え続けた部屋</b> を見つけます。一時的にバズって急増した部屋ではなく、
            <b>ブレずに長く伸び続けた</b> 部屋ほど上位になります（普段のランキングでは埋もれがち）。
            <Box sx={{ mt: 1 }}>
              リストの数字: <b>合計の増加人数</b> ／ <b>年</b>＝1年あたりの伸び率 ／ <b>◯年</b>＝伸びてきた期間
            </Box>
            <Box sx={{ mt: 1, opacity: 0.8 }}>
              並び順は「安定して伸びたか × 期間の長さ × 増えた量」で決めています。
            </Box>
          </>
        ) : (
          <>
            <Typography sx={{ fontWeight: 700, fontSize: 14, mb: 0.5 }}>期間の増加とは</Typography>
            選んだ期間（1ヶ月／1年／任意）で <b>何人・何％増えたか</b> のランキングです。
            「1年」は <b>いまも1年前も存在する部屋</b> だけが対象になります。
            <Box sx={{ mt: 1 }}>
              リストの数字: <b>増加人数（増加率）</b> ／ <b>期間前 → 現在</b> の人数
            </Box>
          </>
        )}
      </Box>
    </OCListDescPopover>
  )
}
