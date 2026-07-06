import { describe, it, expect, vi, beforeEach } from 'vitest'

/**
 * 層キャッシュ(fetchRenderer)の回帰テスト。
 *
 * 実バグ: ランキング表示ONで 1週間→全期間→1週間 と移動してからランキングを解除すると、
 * chart が週窓だけで作り直され、その後の全期間タブが currentConfigCoversLimit=true の
 * クライアントスライス(chart.update)で済まされて1週間分しか表示されなくなる。
 * 修正後は assembleResponse が「必要層の揃っている範囲」まで窓を広げて返すため、
 * chart の保持データと層キャッシュのカバー範囲が常に一致する。
 */

// fetcher をモックし、リクエストURLの from/to/series から密な日次データを合成して返す
const fetcherMock = vi.fn()
vi.mock('../fetcher', () => ({
  default: (url: string) => fetcherMock(url),
}))

// chartjs-chart-financial が jsdom で import できないため、チャート本体をモックする
// （テスト対象は層キャッシュのロジックで、chartState 経由で参照されるメンバーだけあればよい）
vi.mock('../../classes/OpenChatChart', () => ({
  default: class {
    rankEmphasis = true
    private isHour = false
    setIsHour(v: boolean) {
      this.isHour = v
    }
    getIsHour() {
      return this.isHour
    }
    setMode() {}
    getMode() {
      return 'line'
    }
  },
}))

const START_DATE = '2026-01-01'
const END_DATE = '2026-07-04'
const DATE_COUNT = 185 // 2026-01-01..2026-07-04 の日数

function addDays(date: string, days: number): string {
  const d = new Date(`${date}T00:00:00Z`)
  d.setUTCDate(d.getUTCDate() + days)
  return d.toISOString().slice(0, 10)
}

function dateRange(from: string, to: string): string[] {
  const dates: string[] = []
  for (let cur = from; cur <= to; cur = addDays(cur, 1)) dates.push(cur)
  return dates
}

function mockChartApi(url: string): ChartResponse {
  const query = new URLSearchParams(url.split('?')[1] ?? '')
  const from = query.get('from') ?? START_DATE
  const to = query.get('to') ?? END_DATE
  const series = (query.get('series') ?? 'member').split(',')

  const date = dateRange(from, to)
  const response: ChartResponse = { date }
  if (series.includes('member')) response.member = date.map(() => 23)
  if (series.includes('position')) {
    response.position = date.map(() => 5)
    response.totalCount = date.map(() => 100)
  }
  return Promise.resolve(response) as unknown as ChartResponse
}

const availability: PositionAvailability = {
  ranking_in: true,
  ranking_all: true,
  rising_in: true,
  rising_all: true,
}

const chartMeta: ChartMeta = {
  startDate: START_DATE,
  endDate: END_DATE,
  dateCount: DATE_COUNT,
  ohlcAvailability: { week: true, month: true, all: true },
  hourAvailability: true,
  positionAvailability: {
    hour: availability,
    week: availability,
    month: availability,
    all: availability,
  },
}

function setupDom() {
  const arg = document.createElement('script')
  arg.id = 'chart-arg'
  arg.type = 'application/json'
  arg.textContent = JSON.stringify({
    id: 1,
    categoryKey: 5,
    categoryName: 'テスト',
    baseUrl: 'https://example.test',
    urlRoot: '',
  } satisfies RankingPositionChartArgDto)
  document.body.appendChild(arg)

  const meta = document.createElement('script')
  meta.id = 'chart-meta'
  meta.type = 'application/json'
  meta.textContent = JSON.stringify(chartMeta)
  document.body.appendChild(meta)
}

describe('fetchRenderer 層キャッシュとクライアントスライスの整合', () => {
  beforeEach(() => {
    vi.resetModules()
    document.body.innerHTML = ''
    fetcherMock.mockReset()
    fetcherMock.mockImplementation(mockChartApi)
    setupDom()
  })

  it('週→全期間→週→ランキング解除の後、全期間タブがクライアントスライスでも全期間ぶんを描画できる', async () => {
    const { fetchChartData, currentConfigCoversLimit } = await import('../fetchRenderer')
    const { graphStore } = await import('../../state/store')
    const { limitAtom, rankingRisingAtom } = await import('../../state/chartState')

    // 1. 1週間タブでランキング表示ON
    graphStore.set(limitAtom, 8)
    graphStore.set(rankingRisingAtom, 'ranking')
    let data = await fetchChartData()
    expect(data.date).toHaveLength(8)

    // 2. 全期間タブへ（不足分をフェッチして全期間を描画）
    graphStore.set(limitAtom, 0)
    expect(currentConfigCoversLimit(0)).toBe(false)
    data = await fetchChartData()
    expect(data.date).toHaveLength(DATE_COUNT)

    // 3. 1週間タブへ戻る（層キャッシュ済み＝クライアントスライスで済む）
    graphStore.set(limitAtom, 8)
    expect(currentConfigCoversLimit(8)).toBe(true)

    // 4. ランキング解除（member層は全期間キャッシュ済みなのでフェッチ不要の再組み立て）
    graphStore.set(rankingRisingAtom, 'none')
    const fetchCallsBefore = fetcherMock.mock.calls.length
    data = await fetchChartData()
    expect(fetcherMock.mock.calls.length).toBe(fetchCallsBefore)

    // 回帰: ここで週窓(8日)だけを返すと chart の保持データが週ぶんに縮み、
    // 次の全期間タブ(coversLimit=true→chart.updateのスライスのみ)が1週間分しか描画できない
    expect(currentConfigCoversLimit(0)).toBe(true)
    expect(data.date).toHaveLength(DATE_COUNT)
    expect(data.member).toHaveLength(DATE_COUNT)
  })

  it('順位層が週窓しか無いときは窓を広げない（未取得範囲をnullで描画しない）', async () => {
    const { fetchChartData } = await import('../fetchRenderer')
    const { graphStore } = await import('../../state/store')
    const { limitAtom, rankingRisingAtom } = await import('../../state/chartState')

    // member層だけ全期間キャッシュさせる
    graphStore.set(limitAtom, 0)
    graphStore.set(rankingRisingAtom, 'none')
    await fetchChartData()

    // 週タブで順位ON: position層は週窓のみ → 組み立ても週窓に留まる
    graphStore.set(limitAtom, 8)
    graphStore.set(rankingRisingAtom, 'ranking')
    const data = await fetchChartData()
    expect(data.date).toHaveLength(8)
    expect(data.position).toHaveLength(8)
  })
})
