import { useEffect } from 'react'
import { useInView } from 'react-intersection-observer'
import { Box, Typography } from '@mui/material'
import { isSP } from '../utils/utils'
import { DummyOpenChatListItem } from './OpenChatListItem'
import AnalysisListItem from './AnalysisListItem'
import type { AnalysisJob } from '../hooks/AnalysisHooks'

const ROOT_MARGIN = isSP() ? '200px' : '600px'

const dummyContainerStyle = { opacity: 0.6 } as const

export default function FetchAnalysisList({ job }: { job: AnalysisJob }) {
  const { phase, items, totalCount, isLastPage, loadMore, resultMetric } = job

  const { ref: sentinelRef, inView } = useInView({ rootMargin: ROOT_MARGIN, threshold: 0 })

  // 末尾センチネルが見えたら次を描画/取得（メモリ内スライス拡張 or 次バッチ）
  useEffect(() => {
    if (inView && phase === 'done' && !isLastPage) {
      loadMore()
    }
  }, [inView, phase, isLastPage, loadMore])

  if (phase === 'idle') {
    return (
      <Box sx={{ textAlign: 'center', color: 'var(--c-text-sub, #888)', py: 6, px: 2 }}>
        <Typography sx={{ fontSize: 14 }}>
          上のバーで条件を選び「分析する」を押してください
        </Typography>
        <Typography sx={{ fontSize: 12, mt: 1, opacity: 0.8 }}>
          長期の増加数・増加率や、数年かけてじわじわ伸びている部屋を探せます
        </Typography>
      </Box>
    )
  }

  if (phase === 'error') {
    return (
      <Box sx={{ textAlign: 'center', py: 6 }}>
        <Typography sx={{ fontSize: 14 }}>通信エラー😥 もう一度お試しください</Typography>
        <Typography sx={{ fontSize: 12, mt: 1, opacity: 0.7 }}>
          条件を変えて再検索してください
        </Typography>
      </Box>
    )
  }

  if (phase === 'running') {
    return (
      <ol className="openchat-item-container" style={dummyContainerStyle}>
        <DummyOpenChatListItem />
      </ol>
    )
  }

  // phase === 'done'
  if (items.length === 0) {
    return (
      <Box sx={{ textAlign: 'center', py: 6 }}>
        <Typography sx={{ fontSize: 14 }}>条件に合う部屋が見つかりませんでした</Typography>
        <Typography sx={{ fontSize: 12, mt: 1, opacity: 0.8 }}>
          期間・カテゴリ・キーワードを変えて再検索してください
        </Typography>
      </Box>
    )
  }

  return (
    <>
      <Box sx={{ px: 0.5, py: 1 }}>
        <Typography component="span" sx={{ fontSize: 13, fontWeight: 600 }}>
          {totalCount.toLocaleString()} 件
        </Typography>
      </Box>

      <ol className="openchat-item-container">
        {items.map((item, i) => (
          <li key={`${resultMetric}/${item.id}`} className="OpenChatListItem-outer">
            <AnalysisListItem item={item} metric={resultMetric} />
            {(i + 1) % 10 === 0 && i + 1 < totalCount && (
              <div style={{ marginBottom: '2rem' }}>
                <div className="record-count middle">
                  <span>
                    {totalCount.toLocaleString()} 件中 {(i + 2).toLocaleString()} 件目〜
                  </span>
                </div>
              </div>
            )}
          </li>
        ))}
      </ol>

      {!isLastPage && (
        <ol className="openchat-item-container" style={dummyContainerStyle} ref={sentinelRef}>
          <DummyOpenChatListItem />
        </ol>
      )}
    </>
  )
}
