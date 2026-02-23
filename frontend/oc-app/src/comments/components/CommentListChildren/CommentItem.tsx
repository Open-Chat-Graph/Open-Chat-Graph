import { Theme } from '@emotion/react'
import { ListItem, ListItemText, SxProps, Typography } from '@mui/material'
import LikeButton from '../Button/LikeButton'
import { memo } from 'react'
import {
  convertTimeTagFormatFromMySql,
  formatDatetimeWithWeekdayFromMySql,
} from '../../utils/utils'
import ReportButton from '../Button/ReportButton'
import HashId from './HashId'
import { linkify } from '../../utils/linkify'
import CommentImageGallery from '../CommentImageGallery'

const listItemSx: SxProps<Theme> = {
  p: 0,
  flexDirection: 'column',
  alignItems: 'flex-start',
}

export default memo(function CommentItem(props: CommentItemApi & LikeBtnApi & { images?: CommentImage[]; isOwn?: boolean }) {
  const { id, commentId, name, time, text, status, userIdHash, uaHash, ipHash, empathyCount, insightsCount, negativeCount, voted, images, isOwn } = props

  return (
    <ListItem sx={listItemSx}>
      <ListItemText
        sx={{ m: 0 }}
        primary={
          <Typography
            display="block"
            component="span"
            variant="body2"
            color="text.secondary"
            sx={{ fontSize: '15px' }}
          >
            {`${id}: `}
            <b>{text.length ? `${name ? name : '匿名'}` : '***'}</b>
            <time dateTime={convertTimeTagFormatFromMySql(time)}>{` ${formatDatetimeWithWeekdayFromMySql(time)}`}</time>
            {!text.length && ` ${status || '削除済'}`}
            {!!text.length && !isOwn && <ReportButton id={id} commentId={commentId} />}
            <HashId userIdHash={userIdHash} uaHash={uaHash} ipHash={ipHash} />
          </Typography>
        }
        secondary={
          <Typography
            display="block"
            component="span"
            variant="body1"
            color="text.primary"
            margin={'0 0 8px 0'}
            sx={{
              wordBreak: 'break-all',
              whiteSpace: 'pre-line',
              fontSize: '15px',
              color: text.length ? undefined : '#aaa',
            }}
          >
            {text.length ? linkify(text.replace(/(\r?\n|\r){3,}/g, '\n\n')) : '削除されたコメント😇'}
          </Typography>
        }
      />
      {!!text.length && images && images.length > 0 && (
        <CommentImageGallery images={images} posterName={name || '匿名'} commentNo={id} isOwn={isOwn} />
      )}
      {!!text.length && (
        <LikeButton {...{ empathyCount, insightsCount, negativeCount, voted, commentId }} />
      )}
    </ListItem>
  )
})
