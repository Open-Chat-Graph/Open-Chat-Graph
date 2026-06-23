import React, { memo, useRef } from 'react'
import useInfiniteFetchApi, { useRateLimitWaiting } from '../hooks/InfiniteFetchApi'
import ReloadErrorBlock from './ReloadErrorBlock'
import { useAtomValue } from 'jotai'
import { listParamsState } from '../store/atom'
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown'
import OpenChatListItem, { DummyOpenChatListItem } from './OpenChatListItem'
import OCListTitleDesc from './OCListTitleDesc'
import OCListTotalCount from './OCListTotalCount'
import { sprintfT, t } from '../config/translation'

const dummyContainerStyle: React.CSSProperties = { opacity: 0.55 }

const DummyList = memo(function DummyList({
  data,
  error,
  isValidating,
  isLastPage,
  useInViewRef,
  reload,
}: {
  data: boolean
  error: Error | undefined
  isValidating: boolean
  isLastPage: boolean
  useInViewRef: (node?: Element | null | undefined) => void
  reload: () => void
}) {
  // 429の再試行待機中はスケルトン(isValidatingで表示が続く)に重ねて案内文を出す
  const rateLimitWaiting = useRateLimitWaiting()
  // 5xx・ネットワークエラーは再読み込みで回復しうるので、専用の再読み込みUIを出す
  const isServerBusy = error?.name === 'ServerBusyError'

  return (
    <>
      {rateLimitWaiting && (
        <div style={{ textAlign: 'center', padding: '0.5rem' }}>
          {t('アクセスが集中しています。10秒ほど待って自動で再読み込みします…')}
        </div>
      )}
      {((!data && !error) || isValidating) && (
        <ol className="openchat-item-container" style={dummyContainerStyle}>
          <DummyOpenChatListItem />
        </ol>
      )}
      {!(isValidating || isLastPage || error) && data && (
        <ol className="openchat-item-container" style={dummyContainerStyle} ref={useInViewRef}>
          <DummyOpenChatListItem />
        </ol>
      )}
      {error && !rateLimitWaiting && isServerBusy && <ReloadErrorBlock onReload={reload} />}
      {error && !rateLimitWaiting && !isServerBusy && (
        <div style={{ textAlign: 'center' }}>
          {error.name === 'RateLimitError'
            ? t('アクセスが集中しています。しばらくしてから再度お試しください') + '🙏'
            : t('通信エラー') + '😥'}
        </div>
      )}
    </>
  )
})

const ListItem = memo(OpenChatListItem)

const ListContext = memo(function ListContext({
  cateIndex,
  list,
  totalCount,
  sort,
  data,
  query,
}: {
  cateIndex: number
  list: ListParams['list']
  totalCount: number | undefined
  sort: ListParams['sort']
  data: OpenChat[]
  query: string
}) {
  const items = useRef<[string, React.JSX.Element[]]>(['', []])

  const dataLen = data.length
  let curLen = items.current[1].length

  if (items.current[0] === query && curLen === dataLen) {
    return <ol className="openchat-item-container">{items.current[1]}</ol>
  }

  if (items.current[0] !== query) {
    items.current[0] = query
    items.current[1] = []
    curLen = 0
  }

  for (let i = curLen; i < dataLen; i++) {
    items.current[1][i] = (
      <li key={`${cateIndex}/${i}`} className="OpenChatListItem-outer">
        <ListItem
          listParam={list}
          {...data[i]}
          cateIndex={cateIndex}
          showNorth={list === 'daily' && sort === 'rank' && i + 1 <= 3}
        />
        {(i + 1) % 10 === 0 && i + 1 < (totalCount ?? 0) && (
          <div style={{ marginBottom: '2rem' }}>
            <div className="record-count middle">
              <KeyboardArrowDownIcon sx={{ fontSize: '14px', display: 'block' }} />
              <span>
                {sprintfT(
                  '%s 件中 %s 件目～',
                  totalCount?.toLocaleString() ?? 0,
                  (i + 2).toLocaleString()
                )}
              </span>
            </div>
          </div>
        )}
      </li>
    )
  }

  return <ol className="openchat-item-container">{items.current[1]}</ol>
})

const TotalCount = memo(OCListTotalCount)

function FetchDummyList({ query, cateIndex }: { query: string; cateIndex: number }) {
  const { data } = useInfiniteFetchApi<OpenChat>(query)
  const params = useAtomValue(listParamsState)
  const totalCount = data?.length === 0 ? 0 : data ? data[0].totalCount : undefined

  return (
    <div>
      <OCListTotalCount
        totalCount={totalCount}
        cateIndex={cateIndex}
        keyword={params.keyword}
        subCategory=""
      />
      <div className="OpenChatListItem-outer">
        <ol className="openchat-item-container" style={data ? undefined : dummyContainerStyle}>
          {data ? (
            data.map((oc, i) => (
              <OpenChatListItem
                key={i}
                listParam={params.list}
                {...oc}
                cateIndex={cateIndex}
                showNorth={false}
              />
            ))
          ) : (
            <DummyOpenChatListItem />
          )}
        </ol>
      </div>
    </div>
  )
}

const ListTitleDesc = memo(OCListTitleDesc)

export function DummyOpenChatRankingList({
  query,
  cateIndex,
  shelf,
}: {
  query: string
  cateIndex: number
  // 関連テーマ棚。絶対配置コンテナの中（リストの上）に入れて、スクロール中スワイプでも棚の高さ分
  // リストを押し下げず、棚もリストと一緒に正しい位置へ来るようにする。
  shelf?: React.ReactNode
}) {
  const params = useAtomValue(listParamsState)

  return (
    <div className="dummy-list" style={{ position: 'relative' }}>
      {/* 絶対配置のラッパに「棚」と「リスト」を縦に並べる。棚を .div-fetchOpenChatRankingList の中に
          入れると、`.div-fetchOpenChatRankingList * { font-family }`(OpenChatList.css)で棚のフォントが
          上書きされ、通常フローのアクティブ側の棚（var(--font-family)）と字面/サイズがズレる（iOSで顕著・
          切替直後にガタつく）。棚はこのセレクタの外＝ラッパ直下に置き、アクティブ側と同じフォントにする。 */}
      <div style={{ position: 'absolute', top: `${window.scrollY}px`, width: '100%' }}>
        {shelf}
        <div className="div-fetchOpenChatRankingList">
          <ListTitleDesc
            cateIndex={cateIndex}
            isSearch={!!params.keyword}
            list={params.list}
            visibility={false}
          />
          <FetchDummyList cateIndex={cateIndex} query={query} />
        </div>
      </div>
    </div>
  )
}

export function FetchOpenChatRankingList({
  query,
  cateIndex,
}: {
  query: string
  cateIndex: number
}) {
  const { data, useInViewRef, isValidating, isLastPage, error, reload } =
    useInfiniteFetchApi<OpenChat>(query)
  const params = useAtomValue(listParamsState)
  const totalCount = data?.length === 0 ? 0 : data ? data[0].totalCount : undefined

  return (
    <div className="ranking-list">
      <div className="div-fetchOpenChatRankingList">
        <ListTitleDesc cateIndex={cateIndex} isSearch={!!params.keyword} list={params.list} />
        <TotalCount
          totalCount={totalCount}
          cateIndex={cateIndex}
          subCategory={params.sub_category}
          keyword={params.keyword}
        />
        {data && (
          <ListContext
            cateIndex={cateIndex}
            totalCount={totalCount}
            data={data}
            list={params.list}
            sort={params.sort}
            query={query}
          />
        )}
        <DummyList
          data={!!data}
          error={error}
          isValidating={isValidating}
          isLastPage={isLastPage}
          useInViewRef={useInViewRef}
          reload={reload}
        />
      </div>
    </div>
  )
}

export default FetchOpenChatRankingList
