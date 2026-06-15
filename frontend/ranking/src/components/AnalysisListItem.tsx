import { Chip, Skeleton } from '@mui/material'
import { imageBaseUrl, OPEN_CHAT_CATEGORY_OBJ, rankingArgDto } from '../config/config'
import { formatMember, sprintfT, t } from '../config/translation'

function EmblemIcon({ emblem }: { emblem: OpenChat['emblem'] }) {
  return <span className={`super-icon ${emblem === 1 ? 'sp' : 'official'}`}></span>
}

function LockIcon() {
  return <span className={'lock-icon'}></span>
}

const signed = (n: number) => (n >= 0 ? '+' : '') + n.toLocaleString()
const signedPct = (p: number) => (p >= 0 ? '+' : '') + p.toFixed(1) + '%'

/** 期間増加 metric の統計（増加数＋増加率＋始点→現在） */
function IncreaseStats({ item }: { item: AnalysisItem }) {
  const diff = item.diff ?? 0
  const symbol = diff > 0 ? 'positive' : diff < 0 ? 'negative' : ''
  return (
    <span className={`stats-wrapper all ${symbol}`}>
      <span>{signed(diff)}</span>
      {item.pct != null && <span> ({signedPct(item.pct)})</span>}
      {item.base != null && (
        <span className="api-created-at">
          {item.base.toLocaleString()} → {item.member.toLocaleString()}
        </span>
      )}
    </span>
  )
}

/** じわじわ成長 metric の統計（年率・安定度R²・継続年数） */
function SteadyStats({ item }: { item: AnalysisItem }) {
  const years = item.historyDays != null ? (item.historyDays / 365.25).toFixed(1) : null
  const stability = item.r2 != null ? Math.round(item.r2 * 100) : null
  return (
    <span className="stats-wrapper all positive">
      {item.cagr != null && <span>年率{signedPct(item.cagr)}</span>}
      {stability != null && <span> ・ 安定度{stability}%</span>}
      {years != null && <span className="api-created-at">{years}年で {signed((item.member - (item.base ?? item.member)))}</span>}
    </span>
  )
}

export default function AnalysisListItem({
  item,
  metric,
}: {
  item: AnalysisItem
  metric: AnalysisMetric
}) {
  const { id, name, desc, member, img, emblem, joinMethodType, category } = item
  const ocUrl = `${rankingArgDto.baseUrl}/oc/${id}`

  return (
    <div className="openchat-item">
      <a className="overlay-link" href={ocUrl} tabIndex={-1}>
        <span className="visually-hidden">{name}</span>
      </a>
      <div className="item-img-outer">
        <div style={{ opacity: 0.55, width: '100%', height: '100%' }}>
          <Skeleton variant="circular" width="100%" height="100%" />
        </div>
        <img className="item-img" src={`${imageBaseUrl}${img}`} alt={`${name}`} loading="lazy"></img>
      </div>
      <h3>
        <a className="item-title-link" href={ocUrl}>
          {emblem !== 0 && <EmblemIcon emblem={emblem} />}
          {joinMethodType === 2 && <LockIcon />}
          {name}
        </a>
      </h3>
      <p className="item-desc">{desc}</p>
      <footer className="item-lower">
        <div className="item-lower-stats">
          <span>{sprintfT('メンバー %s人', formatMember(member))}</span>
          {metric === 'increase' ? <IncreaseStats item={item} /> : <SteadyStats item={item} />}
        </div>
        <div className="item-lower-category">
          {category >= 0 && (
            <Chip
              sx={{
                height: 'fit-content',
                fontSize: 13,
                display: 'flex',
                width: 'fit-content',
                mt: '2px',
              }}
              label={category > 0 ? OPEN_CHAT_CATEGORY_OBJ[category] : t('その他')}
              size="small"
            />
          )}
        </div>
      </footer>
    </div>
  )
}
