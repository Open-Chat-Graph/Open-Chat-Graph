import {
  Box,
  Chip,
  Stack,
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
  getPositionAvailabilityForLimit,
} from '../state/chartState'
import SettingButton from './SettingButton'
import { t } from '../util/translation'
import { useIsDark } from '../../themeMui'

const chips1: [string, Exclude<ToggleChart, 'none'>][] = [
  [t('急上昇'), 'rising'],
  [t('ランキング'), 'ranking'],
]

function CategoryToggle() {
  const rankingRising = useAtomValue(rankingRisingAtom)
  const category = useAtomValue(categoryAtom)
  const toggleShowCategory = useAtomValue(toggleShowCategoryAtom)
  const limit = useAtomValue(limitAtom)

  // 期間タブ毎: 選択中の種別でデータが無いカテゴリのボタンはグレーアウト
  // （種別未選択時はどちらかの種別にデータがあれば有効扱い）
  const avail = getPositionAvailabilityForLimit(limit)
  const enableIn =
    rankingRising === 'none' ? avail.ranking_in || avail.rising_in : avail[`${rankingRising}_in`]
  const enableAll =
    rankingRising === 'none'
      ? avail.ranking_all || avail.rising_all
      : avail[`${rankingRising}_all`]

  const handleChangeToggle = (_e: React.SyntheticEvent, alignment: urlParamsValue<'category'> | null) => {
    rankingRising !== 'none' && handleChangeCategory(alignment)
  }

  const isDark = useIsDark()
  const isDisabled = rankingRising === 'none'

  return (
    <Stack
      direction="row"
      spacing={1}
      alignItems="center"
      sx={{ opacity: isDisabled ? 0.2 : undefined }}
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
                    /* グレーアウト時は明るい枠が悪目立ちするので通常枠と同じ色に */
                    borderColor: isDisabled ? '#71767b' : '#d3d6d8',
                    '&:hover': {
                      backgroundColor: '#71767b',
                    },
                  },
                  '&:hover': {
                    backgroundColor: 'rgba(231, 233, 234, 0.15)',
                  },
                  '&.Mui-disabled': {
                    color: 'rgba(239, 241, 242, 0.35)',
                    borderColor: '#3f4347',
                  },
                },
              }
            : undefined
        }
      >
        <ToggleButton value="all" disabled={!enableAll}>
          <Typography variant="caption">{t('すべて')}</Typography>
        </ToggleButton>
        {toggleShowCategory && (
          <ToggleButton value="in" disabled={!enableIn}>
            <Typography variant="caption">{t('カテゴリー内')}</Typography>
          </ToggleButton>
        )}
      </ToggleButtonGroup>
    </Stack>
  )
}

export default function ToggleButtons() {
  const isMiniMobile = useMediaQuery('(max-width:359px)')
  const isPc = useMediaQuery('(min-width:512px)')

  const rankingRising = useAtomValue(rankingRisingAtom)
  const limit = useAtomValue(limitAtom)
  const toggleShowCategory = useAtomValue(toggleShowCategoryAtom)

  // 期間タブ毎: データが0件の種別チップはグレーアウト（完全に消すと空白がバグに見えるため）
  const avail = getPositionAvailabilityForLimit(limit)
  const enableChip: Record<Exclude<ToggleChart, 'none'>, boolean> = {
    rising: avail.rising_in || avail.rising_all,
    ranking: avail.ranking_in || avail.ranking_all,
  }
  // 表示中の期間にデータが全く無い場合は見出しも薄く
  const enableAny = enableChip.rising || enableChip.ranking

  return (
    <Box>
      <Stack
        minHeight="48px"
        direction="row"
        alignItems="center"
        justifyContent={isPc ? 'space-around' : 'space-between'}
      >
        <Typography
          variant="h3"
          fontSize="13px"
          fontWeight="bold"
          color="var(--c-text-1)"
          sx={{ opacity: enableAny ? undefined : 0.4 }}
        >
          {t('ランキングの順位を表示')}
        </Typography>
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
                  onClick={
                    enableChip[chip[1]]
                      ? () => handleChangeRankingRising(rankingRising === chip[1] ? 'none' : chip[1])
                      : undefined
                  }
                  size={isMiniMobile ? 'small' : 'medium'}
                  sx={
                    enableChip[chip[1]]
                      ? undefined
                      : { opacity: 0.4, cursor: 'default', pointerEvents: 'none' }
                  }
                />
              )
          )}
        </Stack>
      </Stack>
    </Box>
  )
}
