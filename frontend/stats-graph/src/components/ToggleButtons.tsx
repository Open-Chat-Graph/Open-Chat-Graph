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
import {
  categorySignal,
  rankingRisingSignal,
  handleChangeCategory,
  handleChangeRankingRising,
  toggleShowCategorySignal,
  limitSignal,
  handleChangeEnableZoom,
  zoomEnableSignal,
} from '../signal/chartState'
import SettingButton from './SettingButton'
import { t } from '../util/translation'

const chips1: [string, ToggleChart][] = [
  [t('急上昇'), 'rising'],
  [t('ランキング'), 'ranking'],
]

function CategoryToggle() {
  const handleChangeToggle = (e: MouseEvent, alignment: urlParamsValue<'category'> | null) => {
    e.preventDefault()
    rankingRisingSignal.value !== 'none' && handleChangeCategory(alignment)
  }

  return (
    <Stack
      direction='row'
      spacing={1}
      alignItems='center'
      sx={{ opacity: rankingRisingSignal.value === 'none' ? 0.2 : undefined }}
    >
      <ToggleButtonGroup
        value={categorySignal.value}
        exclusive
        onChange={handleChangeToggle}
        size='small'
      >
        <ToggleButton value='all'>
          <Typography variant='caption'>{t('すべて')}</Typography>
        </ToggleButton>
        {toggleShowCategorySignal.value && (
          <ToggleButton value='in'>
            <Typography variant='caption'>{t('カテゴリー内')}</Typography>
          </ToggleButton>
        )}
      </ToggleButtonGroup>
    </Stack>
  )
}

function SwitchLabels() {
  return (
    <FormGroup>
      <FormControlLabel
        control={<Switch size='small' checked={zoomEnableSignal.value} />}
        label={t('グラフの移動・拡大')}
        sx={{ '.MuiFormControlLabel-label': { fontSize: '11.5px', textWrap: 'nowrap' } }}
        onChange={(e: React.TargetedEvent<HTMLInputElement>) =>
          handleChangeEnableZoom((e.target as any).checked)
        }
      />
    </FormGroup>
  )
}

export default function ToggleButtons() {
  const isMiniMobile = useMediaQuery('(max-width:359px)')
  const isPc = useMediaQuery('(min-width:512px)')

  const sig = rankingRisingSignal.value
  return (
    <Box>
      <Stack
        minHeight='48px'
        direction='row'
        alignItems='center'
        justifyContent={isPc ? 'space-around' : 'space-between'}
      >
        <Typography variant='h3' fontSize='13px' fontWeight='bold' color='#111'>
          {t('ランキングの順位を表示')}
        </Typography>
        {limitSignal.value === 0 && !isPc && <SwitchLabels />}
        {limitSignal.value === 0 && isPc && (
          <Box sx={{ position: 'absolute', ml: '6rem' }}>
            <SwitchLabels />
          </Box>
        )}
        <SettingButton />
      </Stack>
      <Stack
        direction='row'
        spacing={1}
        alignItems='center'
        justifyContent='center'
        m={isMiniMobile ? '0 -1rem' : '0'}
        gap={isMiniMobile ? '2px' : '1rem'}
      >
        <CategoryToggle />
        <Stack direction='row' spacing={1} alignItems='center'>
          {chips1.map(
            (chip) =>
              !(chip[1] === 'ranking' && !toggleShowCategorySignal.value) && (
                <Chip
                  className={`openchat-item-header-chip graph ${sig === chip[1] ? 'selected' : ''}`}
                  label={chip[0]}
                  onClick={() => handleChangeRankingRising(sig === chip[1] ? 'none' : chip[1])}
                  size={isMiniMobile ? 'small' : 'medium'}
                />
              )
          )}
        </Stack>
      </Stack>
    </Box>
  )
}
