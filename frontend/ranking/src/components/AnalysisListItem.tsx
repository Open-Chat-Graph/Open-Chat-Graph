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

/** じわじわ成長 metric の統計（合計増加・年率・継続年数。専門語は i ボタンの説明に逃がす） */
function SteadyStats({ item }: { item: AnalysisItem }) {
  const years = item.historyDays != null ? (item.historyDays / 365.25).toFixed(1) : null
  // 期間中の合計増加 ≒ 回帰の傾き × 日数（「何人増えたか」を直感的に示す）
  const grown =
    item.slope != null && item.historyDays != null ? Math.round(item.slope * item.historyDays) : null
  return (
    <span className="stats-wrapper all positive">
      {grown != null && <span>{signed(grown)}人</span>}
      {(item.cagr != null || years != null) && (
        <span className="api-created-at">
          {item.cagr != null ? `年${signedPct(item.cagr)}` : ''}
          {item.cagr != null && years != null ? ' ・ ' : ''}
          {years != null ? `${years}年` : ''}
        </span>
      )}
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
  // 長期分析からの遷移は全期間グラフ(?limit=all)を開く
  const ocUrl = `${rankingArgDto.baseUrl}/oc/${id}?limit=all`

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
