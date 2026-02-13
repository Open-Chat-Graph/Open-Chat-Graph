import {
  categorySignal,
  chart,
  limitSignal,
  loading,
  rankingRisingSignal,
} from '../signal/chartState'
import { setRenderPositionBtns } from '../app'
import fetcher from './fetcher'
import { defaultBar, defaultCategory, defaultLimitNum } from './urlParam'
import { t } from './translation'

export const chatArgDto: RankingPositionChartArgDto = JSON.parse(
  (document.getElementById('chart-arg') as HTMLScriptElement).textContent!
)
export const statsDto: StatisticsChartDto = JSON.parse(
  (document.getElementById('stats-dto') as HTMLScriptElement).textContent!
)

export const langCode = chatArgDto.urlRoot.replace(/^\/+/, '') as '' | 'tw' | 'th'

const getApiQuery = (param: ChartApiParam, isHour: boolean) => {
  const query = {
    sort: '',
    category: '',
    start_date: isHour ? '' : statsDto.startDate,
    end_date: isHour ? '' : statsDto.endDate,
  }

  switch (param) {
    case 'ranking':
      query.sort = 'ranking'
      query.category = chatArgDto.categoryKey?.toString() ?? ''
      break
    case 'ranking_all':
      query.sort = 'ranking'
      query.category = '0'
      break
    case 'rising':
      query.sort = 'rising'
      query.category = chatArgDto.categoryKey?.toString() ?? ''
      break
    case 'rising_all':
      query.sort = 'rising'
      query.category = '0'
  }

  return new URLSearchParams(query).toString()
}

const renderChart =
  (param: ChartApiParam, animation: boolean, limit: ChartLimit) => (data: RankingPositionChart) => {
    loading.value = false
    const isRising = param === 'rising' || param === 'rising_all'

    chart.render(
      {
        date: data.date.length ? data.date : statsDto.date,
        graph1: data.member.length ? data.member : statsDto.member,
        graph2: data.position,
        time: data.time,
        totalCount: data.totalCount,
      },
      {
        label1: t('メンバー数'),
        label2: isRising ? t('公式急上昇の順位') : t('公式ランキングの順位'),
        category: param.indexOf('all') !== -1 ? t('すべて') : chatArgDto.categoryName,
        isRising,
      },
      animation,
      limit
    )
  }

const renderMemberChart =
  (animation: boolean, limit: ChartLimit) => (data: RankingPositionChart | StatisticsChartDto) => {
    loading.value = false
    chart.render(
      {
        date: data.date,
        graph1: data.member,
        graph2: [],
        time: [],
        totalCount: [],
      },
      {
        label1: t('メンバー数'),
        label2: '',
        category: chatArgDto.categoryName,
      },
      animation,
      limit
    )
  }

export function renderChartWithoutRanking() {
  renderMemberChart(true, limitSignal.value as ChartLimit)(statsDto)
}

export async function fetchChart(animation: boolean) {
  const path: PotisionPath = chart.getIsHour() ? 'position_hour' : 'position'
  const limit: ChartLimit = limitSignal.value === 25 ? 31 : limitSignal.value

  // メンバーグラフのみの場合
  if (rankingRisingSignal.value === 'none') {
    setRenderPositionBtns(true)

    if (chart.getIsHour()) {
      loading.value = true
      await fetcher<RankingPositionChart>(
        `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/${path}?${getApiQuery('ranking', false)}`
      ).then(renderMemberChart(animation, limit))
    } else {
      renderMemberChart(animation, limit)(statsDto)
    }
    return
  }

  const param: ChartApiParam = `${rankingRisingSignal.value}${
    categorySignal.value === 'all' ? '_all' : ''
  }`

  loading.value = true
  await fetcher<RankingPositionChart>(
    `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/${path}?${getApiQuery(param, false)}`
  ).then((data) => {
    const isDefaultGraph =
      limitSignal.value === defaultLimitNum &&
      rankingRisingSignal.value === defaultBar &&
      categorySignal.value === defaultCategory

    if (!isDefaultGraph) {
      setRenderPositionBtns(true)
      renderChart(param, animation, limit)(data)
      return
    }

    setRenderPositionBtns(true)
    renderChart(param, animation, limit)(data)
  })
}
