import { Theme } from '@emotion/react'
import { ListItem, ListItemText, SxProps, Typography } from '@mui/material'
import LikeButton from '../Button/LikeButton'
import { memo } from 'react'
import { convertTimeTagFormatFromMySql, formatDatetimeWithWeekdayFromMySql } from '../../utils/utils'
import ReportButton from '../Button/ReportButton'

const listItemSx: SxProps<Theme> = {
  p: 0,
  flexDirection: 'column',
  alignItems: 'flex-start',
}

export default memo(function CommentItem(props: CommentItemApi & LikeBtnApi) {
  const { id, commentId, name, time, text, userId, empathyCount, insightsCount, negativeCount, voted } = props

  return (
    <ListItem sx={listItemSx}>
      <ListItemText
        sx={{ m: 0 }}
        primary={
          <Typography display="block" component="span" variant="body2" color="text.secondary" sx={{ fontSize: '13px' }}>
            {`${id}: `}
            <b>{text.length ? `${name ? name : '匿名'}` : '***'}</b>
            <time dateTime={convertTimeTagFormatFromMySql(time)}>{` ${formatDatetimeWithWeekdayFromMySql(time)}`}</time>
            {text.length ? (userId ? ` ID:${userId}` : '') : userId ? ` ${userId}` : ' 削除済'}
            {!!text.length && <ReportButton id={id} commentId={commentId} />}
          </Typography>
        }
        secondary={
          <Typography
            display="block"
            component="span"
            variant="body1"
            color="text.primary"
            margin={'8px 0'}
            sx={{
              wordBreak: 'break-all',
              whiteSpace: 'pre-line',
              fontSize: '15px',
              color: text.length ? undefined : '#aaa',
            }}
          >
            {text.length ? text.replace(/(\r?\n|\r){3,}/g, '\n\n') : '削除されたコメント😇'}
          </Typography>
        }
      />
      {!!text.length && <LikeButton {...{ empathyCount, insightsCount, negativeCount, voted, commentId }} />}
    </ListItem>
  )
})
