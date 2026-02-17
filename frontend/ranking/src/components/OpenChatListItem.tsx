import React from 'react'
import { LIMIT_ITEMS } from '../hooks/InfiniteFetchApi'
import { Chip, Skeleton } from '@mui/material'
import { imageBaseUrl, OPEN_CHAT_CATEGORY_OBJ, rankingArgDto } from '../config/config'
import NorthIcon from '@mui/icons-material/North'
import { formatMember, sprintfT, t } from '../config/translation'

export function DummyOpenChatListItem({ len = LIMIT_ITEMS }: { len?: number }) {
  return (
    <>
      {new Array(len).fill(0).map((el, i) => (
        <li className="openchat-item" key={i}>
          <div className="item-img-outer">
            <Skeleton variant="circular" width="100%" height="100%" />
          </div>
          <Skeleton sx={{ mb: '5px' }} height={19} />
          <Skeleton sx={{ mb: '4px' }} height={13} />
          <Skeleton sx={{ mb: '4px' }} height={13} />
          <Skeleton width="50%" height={13} />
        </li>
      ))}
    </>
  )
}

function EmblemIcon({ emblem }: { emblem: OpenChat['emblem'] }) {
  return <span className={`super-icon ${emblem === 1 ? 'sp' : 'official'}`}></span>
}

function LockIcon() {
  return <span className={'lock-icon'}></span>
}
const getOcUrlParam = (listParam: ListParams['list'], cateIndex: number): string => {
  switch (listParam) {
    case 'hourly':
      return '?limit=hour'
    case 'daily':
      return '?limit=hour'
    case 'ranking':
      return `?bar=ranking&limit=hour${cateIndex === 0 ? '&category=all' : ''}`
    case 'rising':
      return `?bar=rising&limit=hour${cateIndex === 0 ? '&category=all' : ''}`
    default:
      return ''
  }
}

export default function OpenChatListItem({
  id,
  name,
  desc,
  member,
  img,
  emblem,
  joinMethodType,
  category,
  symbolIncrease,
  increasedMember,
  percentageIncrease,
  createdAt,
  cateIndex,
  listParam,
  showNorth,
}: OpenChat & {
  cateIndex: number
  listParam: ListParams['list']
  showNorth: boolean
}) {
  const ocUrl = `${rankingArgDto.baseUrl}/oc/${id}${getOcUrlParam(listParam, cateIndex)}`

  return (
    <div className="openchat-item">
      <a className="overlay-link" href={ocUrl} tabIndex={-1}>
        <span className="visually-hidden">{name}</span>
      </a>
      <div className="item-img-outer">
        <div style={{ opacity: 0.55, width: '100%', height: '100%' }}>
          <Skeleton variant="circular" width="100%" height="100%" />
        </div>
        <img
          className="item-img"
          src={`${imageBaseUrl}${img}`}
          alt={`${name}`}
          loading="lazy"
        ></img>
      </div>
      <h3>
        <a className="item-title-link" href={ocUrl}>
          {emblem !== 0 && <EmblemIcon emblem={emblem} />}
          {joinMethodType === 2 && <LockIcon />}
          {name}
        </a>
        {showNorth && (
          <NorthIcon className="show-north" sx={{ fontSize: '14px', color: '#07B53B' }} />
        )}
      </h3>
      <p className="item-desc">{desc}</p>
      <footer className="item-lower">
        <div className="item-lower-stats">
          <span>{sprintfT('メンバー %s人', formatMember(member))}</span>
          <span className={`stats-wrapper ${listParam} ${symbolIncrease ?? ''}`}>
            {increasedMember && <span>{increasedMember}</span>}
            {percentageIncrease && <span> ({percentageIncrease})</span>}
            {createdAt && <span className="api-created-at">作成 {createdAt}</span>}
          </span>
        </div>
        <div className="item-lower-category">
          {cateIndex === 0 && category >= 0 && (
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
