import { useEffect, useState } from 'react'
import { useAtomValue } from 'jotai'
import {
  Autocomplete,
  Box,
  Button,
  LinearProgress,
  Stack,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
} from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import CloseIcon from '@mui/icons-material/Close'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { analysisParamsState } from '../store/atom'
import { useSetAnalysisParams } from '../hooks/AnalysisHooks'
import type { AnalysisJob } from '../hooks/AnalysisHooks'
import { scrollToTop } from '../utils/utils'
import AnalysisMetricHelp from './AnalysisMetricHelp'
import { ANALYSIS_HEADER_H } from './AnalysisHeader'

const METRICS: { value: AnalysisMetric; label: string }[] = [
  { value: 'increase', label: '期間の増加' },
  { value: 'steady', label: 'じわじわ成長' },
]
const PERIODS: Record<AnalysisMetric, { value: AnalysisPeriod; label: string }[]> = {
  increase: [
    { value: 'month', label: '1ヶ月' },
    { value: 'year', label: '1年' },
    { value: 'custom', label: '任意' },
  ],
  steady: [
    { value: '3month', label: '3ヶ月' },
    { value: '6month', label: '半年' },
    { value: 'year', label: '1年' },
    { value: 'all', label: '全期間' },
    { value: 'custom', label: '任意' },
  ],
}
const SORTS: Record<AnalysisMetric, { value: AnalysisSort; label: string }[]> = {
  increase: [
    { value: 'count', label: '増加数' },
    { value: 'rate', label: '増加率' },
  ],
  steady: [
    { value: 'score', label: 'じわじわ度' },
    { value: 'cagr', label: '年の伸び' },
    { value: 'slope', label: '日の伸び' },
  ],
}

const CATEGORY_OPTIONS = OPEN_CHAT_CATEGORY.map(([label, value]) => ({ label, value }))

// 任意期間で選べる日付の範囲（統計データの開始日〜今日）
const DATA_START = '2023-10-16'
const TODAY = new Date().toISOString().slice(0, 10)

// 角丸セグメンテッドコントロール（iOS風・選択時ダーク塗り、ranking のチップ色に揃える）
const segSx = {
  bgcolor: 'var(--c-surface)',
  borderRadius: '99px',
  p: '3px',
  gap: '2px',
  '& .MuiToggleButtonGroup-grouped': {
    m: 0,
    border: 'none',
    borderRadius: '99px !important',
  },
  '& .MuiToggleButton-root': {
    textTransform: 'none',
    color: 'var(--c-text-1)',
    fontSize: 13,
    fontWeight: 600,
    lineHeight: 1.3,
    px: 1.5,
    py: 0.4,
    '&:hover': { bgcolor: 'transparent' },
    '&.Mui-selected': {
      bgcolor: 'var(--c-chip-dark)',
      color: 'var(--c-text-inverse)',
      '&:hover': { bgcolor: 'var(--c-chip-dark)' },
    },
  },
} as const

// ピル型入力（font-size 16px で iOS 自動ズーム防止）
const pillInputSx = {
  '& .MuiOutlinedInput-root': { borderRadius: '99px', height: 38, background: 'var(--c-surface)', pl: 1 },
  '& .MuiOutlinedInput-notchedOutline': { border: 'none' },
  '& .MuiOutlinedInput-input': { fontSize: 16, py: 0 },
} as const

function Seg<T extends string>({
  value,
  options,
  onChange,
}: {
  value: T
  options: { value: T; label: string }[]
  onChange: (v: T) => void
}) {
  return (
    <ToggleButtonGroup
      size="small"
      exclusive
      value={value}
      onChange={(_, v) => v && onChange(v as T)}
      sx={segSx}
    >
      {options.map((o) => (
        <ToggleButton key={o.value} value={o.value}>
          {o.label}
        </ToggleButton>
      ))}
    </ToggleButtonGroup>
  )
}

export default function AnalysisToolbar({ job }: { job: AnalysisJob }) {
  const params = useAtomValue(analysisParamsState)
  const setParams = useSetAnalysisParams()
  const running = job.phase === 'running'

  const [elapsed, setElapsed] = useState(0)
  useEffect(() => {
    if (!running) {
      setElapsed(0)
      return
    }
    const tmr = setInterval(() => setElapsed((s) => s + 1), 1000)
    return () => clearInterval(tmr)
  }, [running])

  const sortOptions = SORTS[params.metric]
  const categoryValue = CATEGORY_OPTIONS.find((o) => o.value === params.category) ?? CATEGORY_OPTIONS[0]

  // 任意期間の入力チェック: 開始日が空 / 開始≧終了 は分析不可
  const isCustom = params.period === 'custom'
  const dateError = isCustom
    ? !params.from
      ? '開始日を入力してください'
      : params.to && params.from >= params.to
        ? '開始日は終了日より前にしてください'
        : ''
    : ''
  const canSearch = !dateError

  return (
    <Box
      sx={{
        position: 'sticky',
        top: `${ANALYSIS_HEADER_H}px`,
        zIndex: 1200,
        background: 'var(--c-bg)',
        borderBottom: '1px solid var(--c-border)',
        boxShadow: '0 2px 8px rgba(0,0,0,0.06)',
        px: { xs: 1.5, sm: 2.5 },
        py: 1,
      }}
    >
      <Stack
        direction="row"
        useFlexGap
        flexWrap="wrap"
        alignItems="center"
        sx={{ gap: 1, rowGap: 1 }}
      >
        <Seg value={params.metric} options={METRICS} onChange={(v) => setParams((c) => ({ ...c, metric: v }))} />
        <AnalysisMetricHelp metric={params.metric} />

        <Seg
          value={params.period}
          options={PERIODS[params.metric]}
          onChange={(v) => setParams((c) => ({ ...c, period: v }))}
        />

        {isCustom && (
          <>
            <TextField
              size="small"
              type="date"
              value={params.from}
              onChange={(e) => setParams((c) => ({ ...c, from: e.target.value }))}
              error={!params.from}
              slotProps={{ htmlInput: { min: DATA_START, max: TODAY } }}
              sx={{ ...pillInputSx, width: 150 }}
            />
            <Typography sx={{ fontSize: 13, color: 'var(--c-text-3)' }}>〜</Typography>
            <TextField
              size="small"
              type="date"
              value={params.to}
              onChange={(e) => setParams((c) => ({ ...c, to: e.target.value }))}
              error={!!params.to && !!params.from && params.from >= params.to}
              slotProps={{ htmlInput: { min: DATA_START, max: TODAY } }}
              sx={{ ...pillInputSx, width: 150 }}
            />
          </>
        )}

        {/* 並び替えは「期間の増加」のみ（増加数/増加率＝意味が明確）。じわじわ成長は単一スコア順なので出さない */}
        {params.metric === 'increase' && (
          <Seg value={params.sort} options={sortOptions} onChange={(v) => setParams((c) => ({ ...c, sort: v }))} />
        )}
        {/* じわじわ成長はスコア降順固定なので順序トグルは出さない */}
        {params.metric === 'increase' && (
          <Seg
            value={params.order}
            options={[
              { value: 'desc', label: '多い順' },
              { value: 'asc', label: '少ない順' },
            ]}
            onChange={(v) => setParams((c) => ({ ...c, order: v }))}
          />
        )}

        <Autocomplete
          size="small"
          disableClearable
          options={CATEGORY_OPTIONS}
          value={categoryValue}
          onChange={(_, v) => setParams((c) => ({ ...c, category: v?.value ?? 0 }))}
          isOptionEqualToValue={(o, v) => o.value === v.value}
          renderInput={(p) => <TextField {...p} placeholder="カテゴリ" />}
          slotProps={{ paper: { sx: { fontSize: 14 } } }}
          sx={{ width: 150, ...pillInputSx }}
        />

        <TextField
          size="small"
          placeholder="キーワード"
          value={params.keyword}
          onChange={(e) => setParams((c) => ({ ...c, keyword: e.target.value }))}
          sx={{ ...pillInputSx, width: 150 }}
        />

        <Box sx={{ flexGrow: 1 }} />

        {running ? (
          <Button variant="outlined" color="inherit" startIcon={<CloseIcon />} onClick={job.cancel} sx={{ borderRadius: '99px' }}>
            キャンセル
          </Button>
        ) : (
          <>
            {dateError && (
              <Typography sx={{ fontSize: 12, color: 'var(--c-down, #d32f2f)', alignSelf: 'center' }}>
                {dateError}
              </Typography>
            )}
            <Button
              variant="contained"
              startIcon={<SearchIcon />}
              disabled={!canSearch}
              onClick={() => {
                if (!canSearch) return
                scrollToTop()
                window.scrollTo(0, 0)
                job.search(params)
              }}
              sx={{ fontWeight: 700, px: 2.5, borderRadius: '99px' }}
            >
              分析
            </Button>
          </>
        )}
      </Stack>

      {running && (
        <Box sx={{ mt: 0.75 }}>
          <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 0.5 }}>
            <Typography sx={{ fontSize: 12.5, fontWeight: 700, color: 'var(--c-up, #1976d2)' }}>
              分析中 {job.percent}%
            </Typography>
            <Typography sx={{ fontSize: 12, opacity: 0.75 }}>
              {job.computed.toLocaleString()} 件を計算 ・ {elapsed} 秒
            </Typography>
          </Stack>
          <LinearProgress variant="determinate" value={job.percent} sx={{ height: 6, borderRadius: 3 }} />
        </Box>
      )}
    </Box>
  )
}
