import React from 'react'
import { SiteTitleBtn } from './SiteHeader'
import { rankingArgDto } from '../config/config'

export default function SiteHeaderVertical({ height }: { height: string }) {
  return (
    <header className="site_header_outer" style={{ height, display: 'flex', flexDirection: 'column' }}>
      <div className="site_header" style={{ margin: 'auto' }}>
        <SiteTitleBtn />
      </div>
      <div className="refresh-time" style={{ textAlign: 'center', margin: 'auto' }}>
        <span className="refresh-icon"></span>
        <time>{rankingArgDto.rankingUpdatedAt}</time>
      </div>
    </header>
  )
}
