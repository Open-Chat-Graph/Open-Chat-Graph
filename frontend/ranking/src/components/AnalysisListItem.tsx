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

/** 増加の統計（増加数＋増加率＋始点→現在）。増加・じわじわ成長とも同じ表示。 */
function DeltaStats({ item }: { item: AnalysisItem }) {
  const diff = item.diff ?? 0
  const symbol = diff > 0 ? 'positive' : diff < 0 ? 'negative' : ''
  return (
    <span className={`stats-wrapper all ${symbol}`}>
      <span>{signed(diff)}人</span>
      {item.pct != null && <span> ({signedPct(item.pct)})</span>}
      {item.base != null && (
        <span className="api-created-at">
          {item.base.toLocaleString()} → {item.member.toLocaleString()}
        </span>
      )}
    </span>
  )
}

export default function AnalysisListItem({
  item,
  showCategory,
}: {
  item: AnalysisItem
  showCategory: boolean
}) {
  const { id, name, desc, member, img, emblem, joinMethodType, category } = item
  // 長期分析からの遷移は全期間グラフを fragment state で開く
  const ocUrl = `${rankingArgDto.baseUrl}/oc/${id}#graph?limit=all`

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
        </div>
        {/* 増減の数値は専用の1行に（はみ出し防止・青/赤の数字を見やすく） */}
        <div className="analysis-delta" style={{ fontSize: 13, lineHeight: '1.15rem' }}>
          <DeltaStats item={item} />
        </div>
        {/* カテゴリで絞り込み中は各行のカテゴリ表示は冗長なので出さない */}
        {showCategory && category >= 0 && (
          <div className="item-lower-category">
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
          </div>
        )}
      </footer>
    </div>
  )
}
