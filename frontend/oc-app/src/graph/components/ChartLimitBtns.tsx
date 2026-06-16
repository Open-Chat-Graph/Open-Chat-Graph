import {
  Box,
  Chip,
  FormControlLabel,
  FormGroup,
  Stack,
  Switch,
  Tab,
  Tabs,
  useMediaQuery,
} from '@mui/material'
import CandlestickChartIcon from '@mui/icons-material/CandlestickChart'
import ShowChartIcon from '@mui/icons-material/ShowChart'
import AddIcon from '@mui/icons-material/Add'
import RemoveIcon from '@mui/icons-material/Remove'
import { useAtomValue } from 'jotai'
import {
  chartModeAtom,
  handleChangeChartMode,
  handleChangeEnableZoom,
  handleChangeLimit,
  handleZoomStep,
  hasOhlcData,
  hasOhlcDataForLimit,
  limitAtom,
  toggleDisplay24hAtom,
  toggleDisplayAllAtom,
  toggleDisplayMonthAtom,
  toggleDisplayWeekAtom,
  zoomEnableAtom,
} from '../state/chartState'
import { t } from '../util/translation'

function CandlestickToggle() {
  const chartMode = useAtomValue(chartModeAtom)
  const limit = useAtomValue(limitAtom)
  if (!hasOhlcData()) return null
  const isCandlestick = chartMode === 'candlestick'
  // 表示中の期間タブにOHLCデータが無い場合はグレーアウト
  const disabled = !isCandlestick && !hasOhlcDataForLimit(limit)
  // 最新24時間(limit=25)はローソク足非対応。ボタンは隠すが、他タブと高さを揃えて
  // 下の「ランキングの順位を表示」がずれないよう、枠は残して visibility:hidden で消す
  const hidden = limit === 25

  const handleToggle = () => {
    if (disabled || hidden) return
    handleChangeChartMode(isCandlestick ? 'line' : 'candlestick')
  }

  return (
    <Chip
      className={`openchat-item-header-chip graph chart-mode-toggle ${isCandlestick ? 'selected' : ''}`}
      icon={isCandlestick ? <ShowChartIcon /> : <CandlestickChartIcon />}
      label={isCandlestick ? t('折れ線グラフ') : t('ローソク足')}
      onClick={handleToggle}
      size="small"
      sx={{
        visibility: hidden ? 'hidden' : 'visible',
        opacity: disabled ? 0.4 : 1,
        cursor: disabled ? 'default' : 'pointer',
        '& .MuiChip-icon': {
          color: 'inherit',
        },
      }}
      aria-label={isCandlestick ? t('折れ線グラフに切り替え') : t('ローソク足に切り替え')}
    />
  )
}

// グラフの移動・拡大スイッチ（全期間タブでのみ表示）。ローソク足トグルと同じ行の左側に置く
function SwitchLabels() {
  const zoomEnable = useAtomValue(zoomEnableAtom)
  const isPc = useMediaQuery('(min-width:512px)')

  return (
    <FormGroup>
      <FormControlLabel
        control={<Switch size={isPc ? 'medium' : 'small'} checked={zoomEnable} />}
        label={t('グラフの移動・拡大')}
        sx={{
          ml: 0,
          mr: 0,
          // PC幅では文字を大きく・太めにしてスイッチを分かりやすく
          '.MuiFormControlLabel-label': {
            fontSize: isPc ? '13.5px' : '11.5px',
            fontWeight: isPc ? 600 : undefined,
            textWrap: 'nowrap',
          },
        }}
        onChange={(_e: React.SyntheticEvent, checked: boolean) => handleChangeEnableZoom(checked)}
      />
    </FormGroup>
  )
}

// ボタン1回で可視件数を何倍にするか（拡大=表示を半分=0.5 / 縮小=倍に戻す=2）
const ZOOM_IN_FACTOR = 0.5
const ZOOM_OUT_FACTOR = 2

// 縮小・拡大ボタン（移動・拡大スイッチの右隣）。ランキング等と同じ MUI Chip（リップル等のフィードバック付き）。
// 390px以上は アイコン＋ラベルの横長チップ、未満ははみ出さないようアイコンのみのコンパクトなチップ。
// スイッチOFF時は無効(薄く)、ON時に活性化。
function ZoomButtons() {
  const zoomEnable = useAtomValue(zoomEnableAtom)
  const showText = useMediaQuery('(min-width:412px)') // 412px以上: アイコン＋文字
  const wider = useMediaQuery('(min-width:390px)') // 390〜411px: 文字なしで少し横長 / 未満: コンパクト

  const stepChip = (factor: number, label: string, Icon: typeof AddIcon) => (
    <Chip
      className="openchat-item-header-chip graph zoom-step-chip"
      icon={showText ? <Icon /> : undefined}
      label={showText ? label : <Icon sx={{ fontSize: '1.15rem' }} />}
      onClick={() => handleZoomStep(factor)}
      disabled={!zoomEnable}
      size="small"
      aria-label={label}
      sx={
        showText
          ? undefined
          : {
              // アイコンのみ: minWidthで少し横長にしつつ、中身(−/＋)を上下左右とも中央に揃える
              minWidth: wider ? 50 : 34,
              justifyContent: 'center',
              alignItems: 'center',
              '& .MuiChip-label': {
                px: 0,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                height: '100%',
              },
              '& .MuiChip-label svg': { display: 'block' },
            }
      }
    />
  )

  return (
    <Stack direction="row" spacing={1}>
      {stepChip(ZOOM_OUT_FACTOR, t('縮小'), RemoveIcon)}
      {stepChip(ZOOM_IN_FACTOR, t('拡大'), AddIcon)}
    </Stack>
  )
}

export default function ChartLimitBtns() {
  const limit = useAtomValue(limitAtom)
  const chartMode = useAtomValue(chartModeAtom)
  const display24h = useAtomValue(toggleDisplay24hAtom)
  const displayWeek = useAtomValue(toggleDisplayWeekAtom)
  const displayMonth = useAtomValue(toggleDisplayMonthAtom)
  const displayAll = useAtomValue(toggleDisplayAllAtom)
  // PC幅(≥512px)で余裕があるときだけ縮小拡大を行の中央に。狭い幅はスイッチ右隣に寄せる
  // （412〜511で中央gridにするとスイッチの文字が見切れるため）
  const centerZoom = useMediaQuery('(min-width:512px)')

  const handleChange = (_e: React.SyntheticEvent, newLimit: ChartLimit | 25) => {
    handleChangeLimit(newLimit)
  }

  return (
    <>
      <Box
        sx={{ borderBottom: 1, borderColor: 'var(--c-border)', width: '100%' }}
        className="limit-btns category-tab"
      >
        <Tabs onChange={handleChange} variant="fullWidth" value={limit}>
          {display24h && chartMode !== 'candlestick' && (
            <Tab value={25} label={t('最新24時間')} />
          )}
          {displayWeek && <Tab value={8} label={t('1週間')} />}
          {displayMonth && <Tab value={31} label={t('1ヶ月')} />}
          {displayAll && <Tab value={0} label={t('全期間')} />}
        </Tabs>
      </Box>
      {/* 操作行: 全期間時に 移動・拡大スイッチ＋縮小拡大、OHLCありでローソク足トグル。
          中身が無くても一定高さでレイアウトシフト防止。幅に余裕があれば縮小拡大を中央、狭ければスイッチの右隣 */}
      {centerZoom ? (
        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: '1fr auto 1fr',
            alignItems: 'center',
            minHeight: '38px',
            pt: '1rem',
            columnGap: '6px',
          }}
        >
          <Box sx={{ justifySelf: 'start', display: 'flex', alignItems: 'center', minWidth: 0 }}>
            {limit === 0 && <SwitchLabels />}
          </Box>
          <Box sx={{ justifySelf: 'center' }}>{limit === 0 && <ZoomButtons />}</Box>
          <Box sx={{ justifySelf: 'end' }}>{hasOhlcData() && <CandlestickToggle />}</Box>
        </Box>
      ) : (
        <Box
          sx={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            minHeight: '38px',
            pt: '1rem',
          }}
        >
          <Box sx={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            {limit === 0 && (
              <>
                <SwitchLabels />
                <ZoomButtons />
              </>
            )}
          </Box>
          <Box>{hasOhlcData() && <CandlestickToggle />}</Box>
        </Box>
      )}
    </>
  )
}
