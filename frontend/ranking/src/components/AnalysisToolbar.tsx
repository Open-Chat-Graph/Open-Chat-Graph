import { useEffect, useState } from 'react'
import { useAtomValue } from 'jotai'
import {
  Box,
  Button,
  Chip,
  LinearProgress,
  Menu,
  MenuItem,
  Stack,
  TextField,
  Typography,
  useScrollTrigger,
} from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import CloseIcon from '@mui/icons-material/Close'
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import { analysisParamsState } from '../store/atom'
import { useSetAnalysisParams } from '../hooks/AnalysisHooks'
import type { AnalysisJob } from '../hooks/AnalysisHooks'
import { scrollToTop } from '../utils/utils'
import AnalysisMetricHelp from './AnalysisMetricHelp'

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
    { value: 'cagr', label: '年の伸び率' },
    { value: 'slope', label: '1日の増加数' },
  ],
}

/** ランキングと同じピル型トグル（.openchat-item-header-chip / .selected） */
function Pill({
  label,
  selected,
  onClick,
}: {
  label: string
  selected: boolean
  onClick: () => void
}) {
  return (
    <Chip
      label={label}
      onClick={selected ? undefined : onClick}
      className={`openchat-item-header-chip${selected ? ' selected' : ''}`}
      sx={{ cursor: selected ? 'default' : 'pointer' }}
    />
  )
}

/** ラベル＋コントロールの1行 */
function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <Stack direction="row" alignItems="center" useFlexGap flexWrap="wrap" sx={{ gap: 1, rowGap: 1 }}>
      <Typography
        sx={{ fontSize: 12, color: 'var(--c-text-3)', width: 34, flexShrink: 0, fontWeight: 600 }}
      >
        {label}
      </Typography>
      {children}
    </Stack>
  )
}

// ピル型入力（キーワード・日付）。font-size 16px で iOS の自動ズームを防ぐ
const pillInputSx = {
  '& .MuiOutlinedInput-root': {
    borderRadius: '99px',
    height: 36,
    background: 'var(--c-surface)',
  },
  '& .MuiOutlinedInput-notchedOutline': { border: 'none' },
  '& .MuiOutlinedInput-input': { fontSize: 16, py: 0 },
} as const

export default function AnalysisToolbar({ job }: { job: AnalysisJob }) {
  const params = useAtomValue(analysisParamsState)
  const setParams = useSetAnalysisParams()
  const running = job.phase === 'running'

  const trigger = useScrollTrigger()
  const hidden = trigger && !running

  const [elapsed, setElapsed] = useState(0)
  useEffect(() => {
    if (!running) {
      setElapsed(0)
      return
    }
    const tmr = setInterval(() => setElapsed((s) => s + 1), 1000)
    return () => clearInterval(tmr)
  }, [running])

  const [catAnchor, setCatAnchor] = useState<null | HTMLElement>(null)
  const catName = OPEN_CHAT_CATEGORY.find((c) => c[1] === params.category)?.[0] ?? 'すべて'

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
        px: { xs: 1.75, sm: 2.5 },
        pt: 1.5,
        pb: running ? 0.75 : 1.5,
      }}
    >
      <Stack sx={{ gap: 1.25 }}>
        {/* 指標 */}
        <Row label="指標">
          {METRICS.map((m) => (
            <Pill
              key={m.value}
              label={m.label}
              selected={params.metric === m.value}
              onClick={() => setParams((c) => ({ ...c, metric: m.value }))}
            />
          ))}
          <AnalysisMetricHelp metric={params.metric} />
        </Row>

        {/* 期間（期間の増加のみ） */}
        {params.metric === 'increase' && (
          <Row label="期間">
            {PERIODS.map((p) => (
              <Pill
                key={p.value}
                label={p.label}
                selected={params.period === p.value}
                onClick={() => setParams((c) => ({ ...c, period: p.value }))}
              />
            ))}
            {params.period === 'custom' && (
              <>
                <TextField
                  size="small"
                  type="date"
                  value={params.from}
                  onChange={(e) => setParams((c) => ({ ...c, from: e.target.value }))}
                  sx={{ ...pillInputSx, width: 150 }}
                />
                <Typography sx={{ fontSize: 13, color: 'var(--c-text-3)' }}>〜</Typography>
                <TextField
                  size="small"
                  type="date"
                  value={params.to}
                  onChange={(e) => setParams((c) => ({ ...c, to: e.target.value }))}
                  sx={{ ...pillInputSx, width: 150 }}
                />
              </>
            )}
          </Row>
        )}

        {/* 並び替え＋順序 */}
        <Row label="並び">
          {sortOptions.map((s) => (
            <Pill
              key={s.value}
              label={s.label}
              selected={params.sort === s.value}
              onClick={() => setParams((c) => ({ ...c, sort: s.value }))}
            />
          ))}
          <Box sx={{ width: 4 }} />
          <Pill
            label="多い順"
            selected={params.order === 'desc'}
            onClick={() => setParams((c) => ({ ...c, order: 'desc' }))}
          />
          <Pill
            label="少ない順"
            selected={params.order === 'asc'}
            onClick={() => setParams((c) => ({ ...c, order: 'asc' }))}
          />
        </Row>

        {/* 絞り込み（カテゴリ・キーワード） */}
        <Row label="絞込">
          <Chip
            label={catName}
            icon={<ArrowDropDownIcon />}
            onClick={(e) => setCatAnchor(e.currentTarget)}
            className="openchat-item-header-chip"
            sx={{ cursor: 'pointer', flexDirection: 'row-reverse', pl: 0.5 }}
          />
          <Menu
            anchorEl={catAnchor}
            open={Boolean(catAnchor)}
            onClose={() => setCatAnchor(null)}
            disableScrollLock
            slotProps={{ paper: { sx: { maxHeight: 360 } } }}
          >
            {OPEN_CHAT_CATEGORY.map((c) => (
              <MenuItem
                key={c[1]}
                selected={c[1] === params.category}
                onClick={() => {
                  setParams((p) => ({ ...p, category: c[1] }))
                  setCatAnchor(null)
                }}
              >
                {c[0]}
              </MenuItem>
            ))}
          </Menu>
          <TextField
            size="small"
            placeholder="キーワード"
            value={params.keyword}
            onChange={(e) => setParams((c) => ({ ...c, keyword: e.target.value }))}
            sx={{ ...pillInputSx, width: 170 }}
          />
        </Row>

        {/* アクション or 進捗 */}
        {running ? (
          <Box>
            <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 0.5 }}>
              <Typography sx={{ fontSize: 12.5, fontWeight: 700, color: 'var(--c-up, #1976d2)' }}>
                分析中 {job.percent}%
              </Typography>
              <Typography sx={{ fontSize: 12, opacity: 0.75 }}>
                {job.computed.toLocaleString()} 件を計算 ・ {elapsed} 秒
              </Typography>
              <Box sx={{ flexGrow: 1 }} />
              <Button size="small" variant="outlined" color="inherit" startIcon={<CloseIcon />} onClick={job.cancel}>
                キャンセル
              </Button>
            </Stack>
            <LinearProgress variant="determinate" value={job.percent} sx={{ height: 6, borderRadius: 3 }} />
          </Box>
        ) : (
          <Stack direction="row">
            <Box sx={{ flexGrow: 1 }} />
            <Button
              variant="contained"
              startIcon={<SearchIcon />}
              onClick={() => {
                scrollToTop()
                window.scrollTo(0, 0)
                job.search(params)
              }}
              sx={{ fontWeight: 700, px: 3, borderRadius: '99px' }}
            >
              分析する
            </Button>
          </Stack>
        )}
      </Stack>
    </Box>
  )
}
