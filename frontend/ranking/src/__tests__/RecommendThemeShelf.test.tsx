import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { Provider } from 'jotai'
import { SWRConfig } from 'swr'
import RecommendThemeShelf from '../components/RecommendThemeShelf'

// /oclist-tags が「表示中の上位ルームのタグ」を返す前提のモック。
beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn(() =>
      Promise.resolve({
        ok: true,
        json: () =>
          Promise.resolve([
            { name: 'ゲーム', slug: '%E3%82%B2%E3%83%BC%E3%83%A0' },
            { name: '雑談', slug: '%E9%9B%91%E8%AB%87' },
          ]),
      })
    )
  )
})

describe('RecommendThemeShelf', () => {
  it('取得したタグを /recommend/{slug} への本物の <a> リンクで描画する', async () => {
    render(
      <MemoryRouter>
        <Provider>
          <RecommendThemeShelf category={0} subCategory="" />
        </Provider>
      </MemoryRouter>
    )

    await waitFor(() => expect(screen.getAllByRole('link')).toHaveLength(2))

    const links = screen.getAllByRole('link')
    // urlRoot '' + サーバ urlencode 済み slug を再エンコードせず連結すること（既存 /recommend と一致）
    expect(links[0]).toHaveAttribute('href', '/recommend/%E3%82%B2%E3%83%BC%E3%83%A0')
    expect(links[0]).toHaveTextContent('ゲーム')
    expect(links[1]).toHaveAttribute('href', '/recommend/%E9%9B%91%E8%AB%87')
    expect(screen.getByText('関連テーマ')).toBeInTheDocument()
  })

  it('取得前はスケルトンで高さを確保する（null で潰してリストをガタつかせない）', () => {
    // 解決しない fetch にして「読み込み中」状態を再現する。
    vi.stubGlobal(
      'fetch',
      vi.fn(() => new Promise(() => {}))
    )

    const { container } = render(
      // 前テストの SWR グローバルキャッシュを引き継がないよう、空キャッシュで隔離する。
      <SWRConfig value={{ provider: () => new Map() }}>
        <MemoryRouter>
          <Provider>
            <RecommendThemeShelf category={0} subCategory="" />
          </Provider>
        </MemoryRouter>
      </SWRConfig>
    )

    // 取得前はチップ（リンク）は無いが、高さ確保用のスケルトンは描画されている。
    expect(screen.queryAllByRole('link')).toHaveLength(0)
    expect(container.querySelector('.MuiSkeleton-root')).not.toBeNull()
  })
})
