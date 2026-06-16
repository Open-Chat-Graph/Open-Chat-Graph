import OpenChatChart from '../../OpenChatChart'
import getRankingBarLabelRange from './getRankingBarLabelRange'
import { sprintfT } from '../../../util/translation'

// 目盛りに使う「きりの良い順位」候補（広いレンジ向け。対数的に並ぶ）
const NICE_RANKS = [
  1, 2, 3, 4, 5, 7, 10, 15, 20, 30, 50, 75, 100, 150, 200, 300, 500, 750, 1000, 1500, 2000, 3000,
  5000, 7000, 10000,
]

/** x 以上で最小の「きりの良い数」(1,2,5,10,20,50,…)。狭いレンジの目盛りステップに使う */
function niceStep(x: number): number {
  if (x <= 1) return 1
  const mag = Math.pow(10, Math.floor(Math.log10(x)))
  const f = x / mag
  const nice = f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10
  return nice * mag
}

export interface RankBarScale {
  min: number
  max: number
  /** linear のみ使用（非線形では chart.js の自動目盛りに任せるため undefined） */
  stepSize?: number
  /** 非線形のみ: 目盛りを明示配置する（最良/最悪順位を上下端のグリッド線に必ず合わせる） */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  afterBuildTicks?: (axis: any) => void
  /** 軸値→「N 位」ラベル。連続重複は空に間引く（描画ごとに新しいクロージャを作る） */
  makeTickCallback: () => (v: number) => string
}

/**
 * 順位バー軸(temperatureChart, 折れ線モード)の min/max と目盛りラベルを返す。
 * buildOptions（初期構築）と zoomOptions（ズーム時の再計算）の両方から使い、整合を一箇所に集約する。
 *
 * - linear: 従来どおり getRankingBarLabelRange の等間隔レンジ＋stepSize
 * - log/sqrt: 0〜「最良順位の変換値＋余白」。上位ほど縦に広がる。目盛り値は chart.js が
 *   軸値空間で等間隔に置き、callback が valueToRank で実順位ラベルへ戻す
 */
export default function getRankBarScale(
  ocChart: OpenChatChart,
  reversedData: (number | null)[]
): RankBarScale {
  if (ocChart.isLinearRankScale()) {
    const r = getRankingBarLabelRange(ocChart, reversedData)
    return {
      min: r.dataMin,
      max: r.dataMax,
      stepSize: r.stepSize,
      makeTickCallback: () => {
        let lastTick = 0
        return (v: number) => {
          const rank = Math.ceil(ocChart.valueToRank(v))
          if (!rank || rank === lastTick) return ''
          lastTick = rank
          return sprintfT('%s 位', rank)
        }
      },
    }
  }

  // 非線形: 上端＝最良順位の高さ（余白なし）。最良順位と最悪順位を必ず上下端のグリッド線に置き、
  // その間にきりの良い順位を等間隔（軸値空間）で配置する。これで「目盛り最大≠データ最大」の差をなくす。
  let maxVal = 0
  for (const v of reversedData) if (v != null && v > maxVal) maxVal = v
  const axisMax = maxVal > 0 ? maxVal : 1

  return {
    min: 0,
    max: axisMax,
    afterBuildTicks: (axis) => {
      const lo = ocChart.graph2Min // 最良(小さい)順位 → 上端
      const hi = ocChart.graph2ScaleWorst // 実質的な最悪順位(外れ値除外) → 下端付近
      if (!isFinite(lo) || !isFinite(hi) || hi < 1 || hi <= lo) return
      const top = ocChart.rankToValue(lo) || axisMax
      const minGap = top / 13 // ラベルが詰まりすぎない最小間隔（軸値空間）

      // 中間目盛り: 広いレンジは対数的な NICE_RANKS。候補が少ない狭いレンジ
      // （例: 127〜139位）は線形ステップで補い、グリッドが極端に少なくならないようにする
      let mids = NICE_RANKS.filter((r) => r > lo && r < hi)
      if (mids.length < 4) {
        const step = niceStep((hi - lo) / 6)
        mids = []
        for (let r = Math.ceil((lo + 1) / step) * step; r < hi; r += step) mids.push(r)
      }

      // 最悪順位(hi)は別扱い。それより上の候補を上(最良)→下へ minGap 間引きで並べる
      const hiV = ocChart.rankToValue(hi)
      const cand = [lo, ...mids]
        .map((r) => ({ r, v: ocChart.rankToValue(r) }))
        .filter((t) => t.v > hiV + 1e-9 && t.v <= top + 1e-9)
        .sort((a, b) => b.v - a.v)

      const picked: { r: number; v: number }[] = []
      for (const c of cand) {
        const last = picked[picked.length - 1]
        if (!last || last.v - c.v >= minGap) picked.push(c) // lo(最良)は必ず先頭=上端
      }
      // 最悪順位を必ず一番下に表示。直上の目盛りが近すぎる（超広レンジで圧縮され重なる）場合は
      // それを外して「一番下だけ」にする
      while (picked.length && picked[picked.length - 1].v - hiV < minGap) picked.pop()
      picked.push({ r: hi, v: hiV })
      axis.ticks = picked.map((c) => ({ value: c.v }))
    },
    makeTickCallback:
      () =>
      (v: number): string => {
        if (v <= 0) return ''
        const rank = Math.round(ocChart.valueToRank(v))
        return rank >= 1 ? sprintfT('%s 位', rank) : ''
      },
  }
}
