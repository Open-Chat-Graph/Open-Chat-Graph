import { useEffect, useState } from 'react'
import { useAtomValue } from 'jotai'
import {
  Box,
  Button,
  FormControl,
  InputLabel,
  LinearProgress,
  MenuItem,
  Select,
  Stack,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
  useScrollTrigger,
} from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import CloseIcon from '@mui/icons-material/Close'
import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward'
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { analysisParamsState } from '../store/atom'
import { useSetAnalysisParams } from '../hooks/AnalysisHooks'
import type { AnalysisJob } from '../hooks/AnalysisHooks'

const METRICS: { value: AnalysisMetric; label: string }[] = [
  { value: 'increase', label: '期間の増加' },
  { value: 'steady', label: 'じわじわ成長' },
]
const PERIODS: { value: AnalysisPeriod; label: string }[] = [
  { value: 'month', label: '1ヶ月' },
  { value: 'year', label: '1年' },
  { value: 'custom', label: '任意期間' },
]
const SORTS: Record<AnalysisMetric, { value: AnalysisSort; label: string }[]> = {
  increase: [
    { value: 'count', label: '増加数' },
    { value: 'rate', label: '増加率' },
  ],
  steady: [
    { value: 'score', label: 'じわじわ度' },
    { value: 'cagr', label: '年率(CAGR)' },
    { value: 'slope', label: '勢い(人/日)' },
  ],
}

const fieldSx = { minWidth: 116 } as const

export default function AnalysisToolbar({ job }: { job: AnalysisJob }) {
  const params = useAtomValue(analysisParamsState)
  const setParams = useSetAnalysisParams()
  const running = job.phase === 'running'

  // スクロールで隠す/出す（計算中は進捗・キャンセルを見せ続けるため隠さない）
  const trigger = useScrollTrigger()
  const hidden = trigger && !running

  // 計算中の経過秒（数秒に1度しか%が動かなくても「生きている」感を出す）
  const [elapsed, setElapsed] = useState(0)
  useEffect(() => {
    if (!running) {
      setElapsed(0)
      return
    }
    const t = setInterval(() => setElapsed((s) => s + 1), 1000)
    return () => clearInterval(t)
  }, [running])

  const sortOptions = SORTS[params.metric]

  return (
    <Box
      sx={{
        position: 'sticky',
        top: 0,
        zIndex: 1100,
        background: 'var(--c-bg)',
        borderBottom: '1px solid var(--c-border)',
        boxShadow: hidden ? 'none' : '0 1px 6px rgba(0,0,0,0.06)',
        transform: hidden ? 'translateY(-100%)' : 'translateY(0)',
        transition: 'transform .25s ease',
        px: { xs: 1, sm: 2 },
        pt: 1,
        pb: running ? 0 : 1,
      }}
    >
        <Stack
          direction="row"
          spacing={1}
          useFlexGap
          flexWrap="wrap"
          alignItems="center"
          sx={{ rowGap: 1 }}
        >
          <FormControl size="small" sx={fieldSx}>
            <InputLabel>指標</InputLabel>
            <Select
              label="指標"
              value={params.metric}
              onChange={(e) =>
                setParams((c) => ({ ...c, metric: e.target.value as AnalysisMetric }))
              }
            >
              {METRICS.map((m) => (
                <MenuItem key={m.value} value={m.value}>
                  {m.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          {params.metric === 'increase' && (
            <FormControl size="small" sx={fieldSx}>
              <InputLabel>期間</InputLabel>
              <Select
                label="期間"
                value={params.period}
                onChange={(e) =>
                  setParams((c) => ({ ...c, period: e.target.value as AnalysisPeriod }))
                }
              >
                {PERIODS.map((p) => (
                  <MenuItem key={p.value} value={p.value}>
                    {p.label}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {params.metric === 'increase' && params.period === 'custom' && (
            <>
              <TextField
                size="small"
                type="date"
                label="開始"
                value={params.from}
                onChange={(e) => setParams((c) => ({ ...c, from: e.target.value }))}
                InputLabelProps={{ shrink: true }}
                sx={{ width: 150 }}
              />
              <TextField
                size="small"
                type="date"
                label="終了"
                value={params.to}
                onChange={(e) => setParams((c) => ({ ...c, to: e.target.value }))}
                InputLabelProps={{ shrink: true }}
                sx={{ width: 150 }}
              />
            </>
          )}

          <FormControl size="small" sx={fieldSx}>
            <InputLabel>カテゴリ</InputLabel>
            <Select
              label="カテゴリ"
              value={String(params.category)}
              onChange={(e) =>
                setParams((c) => ({ ...c, category: Number(e.target.value) }))
              }
            >
              {OPEN_CHAT_CATEGORY.map((c) => (
                <MenuItem key={c[1]} value={String(c[1])}>
                  {c[0]}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <TextField
            size="small"
            label="キーワード"
            value={params.keyword}
            onChange={(e) => setParams((c) => ({ ...c, keyword: e.target.value }))}
            sx={{ width: 150 }}
          />

          <FormControl size="small" sx={fieldSx}>
            <InputLabel>並び替え</InputLabel>
            <Select
              label="並び替え"
              value={params.sort}
              onChange={(e) => setParams((c) => ({ ...c, sort: e.target.value as AnalysisSort }))}
            >
              {sortOptions.map((s) => (
                <MenuItem key={s.value} value={s.value}>
                  {s.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <ToggleButtonGroup
            size="small"
            exclusive
            value={params.order}
            onChange={(_, v) => v && setParams((c) => ({ ...c, order: v as AnalysisOrder }))}
          >
            <ToggleButton value="desc" aria-label="降順">
              <ArrowDownwardIcon fontSize="small" />
              多い順
            </ToggleButton>
            <ToggleButton value="asc" aria-label="昇順">
              <ArrowUpwardIcon fontSize="small" />
              少ない順
            </ToggleButton>
          </ToggleButtonGroup>

          <Box sx={{ flexGrow: 1 }} />

          {running ? (
            <Button
              variant="outlined"
              color="inherit"
              startIcon={<CloseIcon />}
              onClick={job.cancel}
            >
              キャンセル
            </Button>
          ) : (
            <Button
              variant="contained"
              startIcon={<SearchIcon />}
              onClick={() => job.search(params)}
              sx={{ fontWeight: 700, px: 2.5 }}
            >
              分析する
            </Button>
          )}
        </Stack>

        {running && (
          <Box sx={{ mt: 0.75 }}>
            <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 0.5 }}>
              <Typography sx={{ fontSize: 12, fontWeight: 700, color: 'var(--c-up, #1976d2)' }}>
                分析中 {job.percent}%
              </Typography>
              <Typography sx={{ fontSize: 12, opacity: 0.75 }}>
                {job.computed.toLocaleString()} 件を計算 ・ {elapsed} 秒経過
              </Typography>
            </Stack>
            <LinearProgress
              variant="determinate"
              value={job.percent}
              sx={{ height: 6, borderRadius: 3 }}
            />
          </Box>
        )}
    </Box>
  )
}
