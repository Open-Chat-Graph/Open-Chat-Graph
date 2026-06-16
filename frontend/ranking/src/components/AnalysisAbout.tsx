import { Box, Divider, Typography } from '@mui/material'

const headingSx = { fontSize: 15, fontWeight: 800, mb: 0.75 } as const
const bodySx = { fontSize: 13.5, lineHeight: 1.85, color: 'var(--c-text-2, #555)' } as const

/**
 * ページ下部に置く詳しい説明。とくに「じわじわ成長」が何を見ているのかを丁寧に解説する。
 * 上のツールバーはコンパクトに保ち、初見でも下まで読めば仕組みが分かるようにする狙い。
 */
export default function AnalysisAbout() {
  return (
    <Box
      component="section"
      sx={{
        mt: 6,
        p: { xs: 2, sm: 3 },
        bgcolor: 'var(--c-surface)',
        border: '1px solid var(--c-border)',
        borderRadius: 2,
        color: 'var(--c-text-1)',
      }}
    >
      <Typography component="h2" sx={{ fontSize: 17, fontWeight: 800, mb: 2 }}>
        この分析ツールについて
      </Typography>

      <Box sx={{ mb: 3 }}>
        <Typography component="h3" sx={headingSx}>
          「期間の増加」とは
        </Typography>
        <Typography sx={bodySx}>
          選んだ期間（1ヶ月・半年・1年・任意）の始点と終点を比べ、メンバーが
          <b>何人増えたか（増加数）</b>と<b>何％増えたか（増加率）</b>で並べ替えます。
          短期間でどれだけ伸びたかを把握したいときに向いています。増加率は、ごく小規模な部屋の
          数人増による「見かけの急騰」を除くため、始点メンバー数が一定以上の部屋だけを対象にしています。
        </Typography>
      </Box>

      <Divider sx={{ my: 2.5, borderColor: 'var(--c-border)' }} />

      <Box>
        <Typography component="h3" sx={headingSx}>
          「じわじわ成長」とは
        </Typography>
        <Typography sx={{ ...bodySx, mb: 1.5 }}>
          通常の急上昇・増加数ランキングでは、一気に伸びた部屋が上位を占め、
          <b>何年もかけて着実に伸び続けている部屋</b>はほとんど埋もれてしまいます。
          「じわじわ成長」は、そうした<b>長期で右肩上がりを続ける部屋</b>を見つけ出すための指標です。
        </Typography>
        <Typography sx={{ ...bodySx, mb: 1.5 }}>
          具体的には、期間内のメンバー数の推移に直線をあてはめ、その<b>当てはまりの良さ（直線性）</b>を
          中心に評価します。短期間の急騰や、上がっては下がるギザギザの動きではなく、
          <b>ブレが少なく一貫して増え続けているか</b>を重視します。さらに、途中で大きく落ち込んでいない（下落の少なさ）、
          十分な観測期間がある、といった条件も加味してスコア化し、その高い順に並べています。
        </Typography>
        <Typography sx={bodySx}>
          「全期間」は部屋の登録タイミングで有利・不利が出やすいため、まずは
          <b>3ヶ月・半年・1年</b>といった同じ長さの窓で比べるのがおすすめです。
          運営の参考に、息の長い伸び方をしている部屋を探してみてください。
        </Typography>
      </Box>

      <Divider sx={{ my: 2.5, borderColor: 'var(--c-border)' }} />

      <Typography sx={{ fontSize: 12, color: 'var(--c-text-3, #888)', lineHeight: 1.8 }}>
        ※ 数万件規模を毎時の最新データで集計するため、結果が出るまで数十秒かかることがあります。
        集計中は進捗（％）を表示します。同じ条件での再表示は高速にキャッシュされます。
        この機能は試験運用版（Labs）です。
      </Typography>
    </Box>
  )
}
