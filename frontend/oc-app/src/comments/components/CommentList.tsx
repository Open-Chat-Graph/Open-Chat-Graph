import CommentItemUi from './CommentListChildren/CommentItem'
import ReadMoreCommentButton from './Button/ReadMoreCommentButton'
import EmptyListItem from './CommentListChildren/EmptyListItem'
import useSWRInfinite from 'swr/infinite'
import { LinearProgress, List } from '@mui/material'
import { containerSx } from '../style/sx'
import { fetchApi } from '../utils/utils'
import { useAtomValue } from 'jotai'
import { postedItemState } from '../state/postedItemState'
import ReportDialog from './Dialog/ReportDialog'
import ImageReportDialog from './Dialog/ImageReportDialog'
import { appInitTagDto } from '../config/appInitTagDto'

function PostedItem({ postedItem, lastId }: { postedItem: CommentItem[]; lastId: number }) {
  return postedItem.map((el, i) => {
    const n = postedItem.length - i
    const id = lastId ? lastId + n : n

    return <CommentItemUi {...{ ...el.comment, id, ...el.like, images: el.images }} isOwn key={id} />
  })
}

const swrOptions = {
  revalidateOnReconnect: false,
  revalidateIfStale: false,
  revalidateOnFocus: false,
  revalidateFirstPage: false,
}

export default function CommentList({ limit }: { limit: number }) {
  const { data, setSize, size, isValidating } = useSWRInfinite<CommentItem[]>(
    (i: number) =>
      `${window.location.origin}/comment/${appInitTagDto.openChatId}?page=${i}&limit=${limit}`,
    fetchApi<CommentItem[]>,
    swrOptions
  )

  const postedItem = useAtomValue(postedItemState)
  const myUserId = (() => { try { return localStorage.getItem('oc-my-user-id') } catch { return null } })()

  return (
    <>
      <ReportDialog />
      <ImageReportDialog />
      {data && (
        <List sx={{ ...containerSx, gap: '2.4rem', p: 0 }}>
          {!postedItem.length && data[0].length === 0 && <EmptyListItem />}
          {<PostedItem postedItem={postedItem} lastId={data[0][0]?.comment.id} />}
          {data.flat().map((el) => (
            <CommentItemUi {...{ ...el.comment, ...el.like, images: el.images }} isOwn={!!myUserId && el.comment.userIdHash === myUserId} key={el.comment.id} />
          ))}
        </List>
      )}
      {isValidating && <LinearProgress />}
      {data && data[data.length - 1].length >= limit && (
        <ReadMoreCommentButton onClick={() => setSize(size + 1)} />
      )}
    </>
  )
}
