import { useLayoutEffect, useRef, useState } from 'react'
import { useAtomValue } from 'jotai'
import {
  Box,
  Button,
  IconButton,
  LinearProgress,
  MenuItem,
  Select,
  Slide,
  Stack,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
  useMediaQuery,
  useScrollTrigger,
} from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import CloseIcon from '@mui/icons-material/Close'
import HighlightOffIcon from '@mui/icons-material/HighlightOff'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { analysisParamsState } from '../store/atom'
import { PERIODS, useSetAnalysisParams } from '../hooks/AnalysisHooks'
import type { AnalysisJob } from '../hooks/AnalysisHooks'
import { scrollToTop } from '../utils/utils'
import AnalysisMetricHelp from './AnalysisMetricHelp'
import { ANALYSIS_HEADER_H, innerColumnSx } from './AnalysisHeader'

const METRICS: { value: AnalysisMetric; label: string }[] = [
  { value: 'increase', label: '期間の増加' },
  { value: 'steady', label: 'じわじわ成長' },
]
const PERIOD_LABEL: Record<AnalysisPeriod, string> = {
  month: '1ヶ月',
  '3month': '3ヶ月',
  '6month': '半年',
  year: '1年',
  all: '全期間',
  custom: '任意',
}
const SORTS: { value: AnalysisSort; label: string }[] = [
  { value: 'count', label: '増加数' },
  { value: 'rate', label: '増加率' },
]

const DATA_START = '2023-10-30'
const TODAY = new Date().toISOString().slice(0, 10)

// 角丸セグメンテッドコントロール（選択時ダーク塗り。ranking のチップ色に合わせる）
const segSx = {
  bgcolor: 'var(--c-surface)',
  borderRadius: '99px',
  p: '3px',
  '& .MuiToggleButtonGroup-grouped': { m: 0, border: 'none', borderRadius: '99px !important' },
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

// ピル型の Select（sx は Select 本体＝OutlinedInput root に当たる）
const pillSelectSx = {
  bgcolor: 'var(--c-surface)',
  borderRadius: '99px',
  height: 38,
  '& .MuiOutlinedInput-notchedOutline': { border: 'none' },
  // 表示値を縦中央に
  '& .MuiSelect-select': {
    py: 0,
    pl: 1.5,
    fontSize: 14,
    height: 38,
    minHeight: 'unset',
    boxSizing: 'border-box',
    display: 'flex',
    alignItems: 'center',
  },
} as const

// ピル型の TextField（sx は FormControl に当たるので、見た目は内側の input root に当てる）
// font-size 16px で iOS 自動ズーム防止。input を 38px に伸ばしブラウザ標準でテキスト/placeholder を縦中央化。
const pillFieldSx = {
  '& .MuiOutlinedInput-root': {
    bgcolor: 'var(--c-surface)',
    borderRadius: '99px',
    height: 38,
  },
  '& .MuiOutlinedInput-notchedOutline': { border: 'none' },
  '& .MuiOutlinedInput-input': {
    fontSize: 16,
    py: 0,
    height: '100%',
    boxSizing: 'border-box',
  },
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
  const loading = job.phase === 'loading'
  const isIncrease = params.metric === 'increase'
  const isCustom = params.period === 'custom'

  // ランキングの CategoryListAppBar と同じ挙動: PC は固定で常時表示、スマホは下スクロールで
  // 隠れて上スクロールで降りてくる（MUI Slide + useScrollTrigger）。スマホで固定にすると
  // 高さ分の隙間が要るので、内容の実寸を測ってスペーサーに反映する（行数が条件で変わるため）。
  const isPC = useMediaQuery('(min-width:600px)')
  const trigger = useScrollTrigger()
  const barRef = useRef<HTMLDivElement>(null)
  const [barHeight, setBarHeight] = useState(0)
  useLayoutEffect(() => {
    const el = barRef.current
    if (!el) return
    const update = () => setBarHeight(el.offsetHeight)
    update()
    const ro = new ResizeObserver(update)
    ro.observe(el)
    return () => ro.disconnect()
  }, [])

  const periodOptions = PERIODS.map((v) => ({ value: v, label: PERIOD_LABEL[v] }))

  const dateError = isCustom
    ? !params.from
      ? '開始日を入力してください'
      : params.to && params.from >= params.to
        ? '開始日は終了日より前にしてください'
        : ''
    : ''
  const canSearch = !dateError && !loading

  const runSearch = () => {
    if (!canSearch) return
    scrollToTop()
    window.scrollTo(0, 0)
    job.search(params)
  }

  const barEl = (
    <Box
      ref={barRef}
      sx={{
        position: isPC ? 'sticky' : 'fixed',
        top: `${ANALYSIS_HEADER_H}px`,
        left: 0,
        right: 0,
        zIndex: 1200,
        background: 'var(--c-bg)',
        borderBottom: '1px solid var(--c-border)',
        // キーワード入力以外は選択・ドラッグ不可
        userSelect: 'none',
        WebkitUserSelect: 'none',
      }}
    >
      <Stack sx={{ ...innerColumnSx, gap: 1, py: 1 }}>
        {/* 指標 */}
        <Stack direction="row" alignItems="center" useFlexGap flexWrap="wrap" sx={{ gap: 1 }}>
          <Seg value={params.metric} options={METRICS} onChange={(v) => setParams((c) => ({ ...c, metric: v }))} />
          <AnalysisMetricHelp metric={params.metric} />
        </Stack>

        {/* 期間（両指標で共通） */}
        <Stack direction="row" alignItems="center" useFlexGap flexWrap="wrap" sx={{ gap: 1 }}>
          <Seg value={params.period} options={periodOptions} onChange={(v) => setParams((c) => ({ ...c, period: v }))} />
          {isCustom && (
            <>
              <TextField
                size="small"
                type="date"
                value={params.from}
                onChange={(e) => setParams((c) => ({ ...c, from: e.target.value }))}
                error={!params.from}
                slotProps={{ htmlInput: { min: DATA_START, max: TODAY } }}
                sx={{ ...pillFieldSx, width: 150 }}
              />
              <Typography sx={{ fontSize: 13, color: 'var(--c-text-3)' }}>〜</Typography>
              <TextField
                size="small"
                type="date"
                value={params.to}
                onChange={(e) => setParams((c) => ({ ...c, to: e.target.value }))}
                slotProps={{ htmlInput: { min: DATA_START, max: TODAY } }}
                sx={{ ...pillFieldSx, width: 150 }}
              />
            </>
          )}
        </Stack>

        {/* カテゴリ＋（期間の増加のときだけ）並び替え・順序 */}
        <Stack direction="row" alignItems="center" useFlexGap flexWrap="wrap" sx={{ gap: 1 }}>
          <Select
            size="small"
            value={String(params.category)}
            onChange={(e) => setParams((c) => ({ ...c, category: Number(e.target.value) }))}
            MenuProps={{ disableScrollLock: true, slotProps: { paper: { sx: { maxHeight: 360 } } } }}
            sx={{ ...pillSelectSx, minWidth: 124 }}
          >
            {OPEN_CHAT_CATEGORY.map((c) => (
              <MenuItem key={c[1]} value={String(c[1])}>
                {c[0]}
              </MenuItem>
            ))}
          </Select>

          {isIncrease && (
            <>
              <Seg value={params.sort} options={SORTS} onChange={(v) => setParams((c) => ({ ...c, sort: v }))} />
              <Seg
                value={params.order}
                options={[
                  { value: 'desc', label: '多い順' },
                  { value: 'asc', label: '少ない順' },
                ]}
                onChange={(v) => setParams((c) => ({ ...c, order: v }))}
              />
            </>
          )}
        </Stack>

        {/* キーワード（全幅＋×クリア）＋ 実行/キャンセル */}
        <Stack direction="row" alignItems="center" sx={{ gap: 1 }}>
          <TextField
            size="small"
            placeholder="キーワード"
            value={params.keyword}
            onChange={(e) => setParams((c) => ({ ...c, keyword: e.target.value }))}
            onKeyDown={(e) => e.key === 'Enter' && runSearch()}
            sx={{ ...pillFieldSx, flexGrow: 1, userSelect: 'text', WebkitUserSelect: 'text' }}
            slotProps={{
              input: {
                endAdornment: params.keyword ? (
                  <IconButton size="small" onClick={() => setParams((c) => ({ ...c, keyword: '' }))} sx={{ mr: -0.5 }}>
                    <HighlightOffIcon sx={{ fontSize: 20, color: 'var(--c-text-3)' }} />
                  </IconButton>
                ) : undefined,
              },
            }}
          />
          {loading ? (
            <Button variant="outlined" color="inherit" startIcon={<CloseIcon />} onClick={job.cancel} sx={{ borderRadius: '99px', flexShrink: 0 }}>
              中止
            </Button>
          ) : (
            <Button
              variant="contained"
              startIcon={<SearchIcon />}
              disabled={!canSearch}
              onClick={runSearch}
              sx={{ fontWeight: 700, px: 2.5, borderRadius: '99px', flexShrink: 0 }}
            >
              分析
            </Button>
          )}
        </Stack>

        {dateError && (
          <Typography sx={{ fontSize: 12, color: 'var(--c-down, #d32f2f)' }}>{dateError}</Typography>
        )}

        {loading && (
          <Box>
            <Stack direction="row" alignItems="baseline" justifyContent="space-between" sx={{ mb: 0.5 }}>
              {job.stalled ? (
                <Typography sx={{ fontSize: 12.5, fontWeight: 700, color: 'var(--c-down, #d32f2f)' }}>
                  接続が不安定です。続きから自動で再開します…
                </Typography>
              ) : (
                <Typography sx={{ fontSize: 12.5, fontWeight: 700, color: 'var(--c-up, #1976d2)' }}>
                  分析中… {job.percent}％
                  {job.computed > 0 && <>（{job.computed.toLocaleString()} 件 集計済み）</>}
                </Typography>
              )}
              <Typography sx={{ fontSize: 11.5, color: 'var(--c-text-3)' }}>{job.elapsed} 秒</Typography>
            </Stack>
            <LinearProgress
              variant={job.stalled || job.percent === 0 ? 'indeterminate' : 'determinate'}
              value={job.percent}
              sx={{ height: 6, borderRadius: 3 }}
            />
          </Box>
        )}
      </Stack>
    </Box>
  )

  // PC: 固定（sticky）で常時表示。スマホ: 固定＋Slide で下スクロール時に隠れ、上スクロールで降りる。
  // スマホは固定で flow から外れるので、同じ高さのスペーサーで隙間を確保する。
  return isPC ? (
    barEl
  ) : (
    <Box sx={{ height: `${barHeight}px` }}>
      <Slide appear={false} direction="down" in={!trigger}>
        {barEl}
      </Slide>
    </Box>
  )
}
