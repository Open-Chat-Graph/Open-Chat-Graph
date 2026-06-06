import {
  Box,
  Chip,
  FormControlLabel,
  FormGroup,
  Stack,
  Switch,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
  useMediaQuery,
} from '@mui/material'
import { useAtomValue } from 'jotai'
import {
  categoryAtom,
  rankingRisingAtom,
  handleChangeCategory,
  handleChangeRankingRising,
  toggleShowCategoryAtom,
  limitAtom,
  handleChangeEnableZoom,
  zoomEnableAtom,
} from '../state/chartState'
import SettingButton from './SettingButton'
import { t } from '../util/translation'
import { useIsDark } from '../../themeMui'

const chips1: [string, ToggleChart][] = [
  [t('急上昇'), 'rising'],
  [t('ランキング'), 'ranking'],
]

function CategoryToggle() {
  const rankingRising = useAtomValue(rankingRisingAtom)
  const category = useAtomValue(categoryAtom)
  const toggleShowCategory = useAtomValue(toggleShowCategoryAtom)

  const handleChangeToggle = (_e: React.SyntheticEvent, alignment: urlParamsValue<'category'> | null) => {
    rankingRising !== 'none' && handleChangeCategory(alignment)
  }

  const isDark = useIsDark()

  return (
    <Stack
      direction="row"
      spacing={1}
      alignItems="center"
      sx={{ opacity: rankingRising === 'none' ? 0.2 : undefined }}
    >
      <ToggleButtonGroup
        value={category}
        exclusive
        onChange={handleChangeToggle}
        size="small"
        sx={
          /* 旧試験実装の isDark 調整値（slate-100文字 / slate-400枠 / 選択時 白 on slate-500） */
          isDark
            ? {
                '& .MuiToggleButton-root': {
                  color: '#eff1f2',
                  borderColor: '#71767b',
                  '&.Mui-selected': {
                    color: '#ffffff',
                    backgroundColor: '#565b60',
                    borderColor: '#d3d6d8',
                    '&:hover': {
                      backgroundColor: '#71767b',
                    },
                  },
                  '&:hover': {
                    backgroundColor: 'rgba(231, 233, 234, 0.15)',
                  },
                },
              }
            : undefined
        }
      >
        <ToggleButton value="all">
          <Typography variant="caption">{t('すべて')}</Typography>
        </ToggleButton>
        {toggleShowCategory && (
          <ToggleButton value="in">
            <Typography variant="caption">{t('カテゴリー内')}</Typography>
          </ToggleButton>
        )}
      </ToggleButtonGroup>
    </Stack>
  )
}

function SwitchLabels() {
  const zoomEnable = useAtomValue(zoomEnableAtom)

  return (
    <FormGroup>
      <FormControlLabel
        control={<Switch size="small" checked={zoomEnable} />}
        label={t('グラフの移動・拡大')}
        sx={{ '.MuiFormControlLabel-label': { fontSize: '11.5px', textWrap: 'nowrap' } }}
        onChange={(_e: React.SyntheticEvent, checked: boolean) =>
          handleChangeEnableZoom(checked)
        }
      />
    </FormGroup>
  )
}

export default function ToggleButtons() {
  const isMiniMobile = useMediaQuery('(max-width:359px)')
  const isPc = useMediaQuery('(min-width:512px)')

  const rankingRising = useAtomValue(rankingRisingAtom)
  const limit = useAtomValue(limitAtom)
  const toggleShowCategory = useAtomValue(toggleShowCategoryAtom)

  return (
    <Box>
      <Stack
        minHeight="48px"
        direction="row"
        alignItems="center"
        justifyContent={isPc ? 'space-around' : 'space-between'}
      >
        <Typography variant="h3" fontSize="13px" fontWeight="bold" color="var(--c-text-1)">
          {t('ランキングの順位を表示')}
        </Typography>
        {limit === 0 && !isPc && <SwitchLabels />}
        {limit === 0 && isPc && (
          <Box sx={{ position: 'absolute', ml: '6rem' }}>
            <SwitchLabels />
          </Box>
        )}
        <SettingButton />
      </Stack>
      <Stack
        direction="row"
        spacing={1}
        alignItems="center"
        justifyContent="center"
        m={isMiniMobile ? '0 -1rem' : '0'}
        gap={isMiniMobile ? '2px' : '1rem'}
      >
        <CategoryToggle />
        <Stack direction="row" spacing={1} alignItems="center">
          {chips1.map(
            (chip) =>
              !(chip[1] === 'ranking' && !toggleShowCategory) && (
                <Chip
                  key={chip[1]}
                  className={`openchat-item-header-chip graph ${rankingRising === chip[1] ? 'selected' : ''}`}
                  label={chip[0]}
                  onClick={() => handleChangeRankingRising(rankingRising === chip[1] ? 'none' : chip[1])}
                  size={isMiniMobile ? 'small' : 'medium'}
                />
              )
          )}
        </Stack>
      </Stack>
    </Box>
  )
}
