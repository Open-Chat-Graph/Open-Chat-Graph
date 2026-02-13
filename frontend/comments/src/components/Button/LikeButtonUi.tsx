import { Button, Stack, Typography } from '@mui/material'
import { LikeBtnHandler, LikeBtnState } from './LikeButton'
import { memo } from 'react'

type BtnTypoProps = {
  text: string | number
  size: string
  color?: string
}

function BtnTypo({ text, size, color }: BtnTypoProps) {
  return (
    <Typography display="block" component="span" fontSize={size} color={color} lineHeight={'115%'}>
      {text}
    </Typography>
  )
}

type BtnProps = {
  title: string
  count: number
  voted: boolean
  handler: LikeBtnHandler
  type: LikeBtnType
  disabled: boolean
}

function Btn(props: BtnProps) {
  const { title, count, voted, handler, type, disabled } = props
  const textColor = voted ? undefined : 'text.secondary'

  return (
    <Button
      color="error"
      onClick={() => handler(type)}
      disabled={disabled}
      sx={{ border: '1px solid #efefef', minHeight: '28px' }}
    >
      <Stack gap="2px" direction="row" alignItems="center">
        <Stack direction="row" alignItems="center" gap={'4px'}>
          <BtnTypo text={title} size="10px" color={textColor} />
          <BtnTypo text={count} size="12px" color={textColor} />
        </Stack>
      </Stack>
    </Button>
  )
}

type BtnWrapper = { title: string; count: number; type: LikeBtnType }

export default memo(function LikeButtonUi(props: LikeBtnState & { handler: LikeBtnHandler }) {
  const { empathyCount, insightsCount: _insightsCount, negativeCount, voted, handler } = props

  const BtnWrapper = (props: BtnWrapper) => (
    <Btn {...{ ...props, handler }} voted={voted === props.type} disabled={!!voted && voted !== props.type} />
  )

  return (
    <Stack gap="8px" direction="row">
      <BtnWrapper title="いいね！" count={empathyCount} type={'empathy'} />
      <BtnWrapper title="うーん…" count={negativeCount} type={'negative'} />
    </Stack>
  )
})
