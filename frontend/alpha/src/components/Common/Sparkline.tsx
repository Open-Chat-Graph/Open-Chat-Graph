/**
 * 純 SVG 折れ線スパークライン。
 * - 依存ライブラリなし
 * - 全点同値でも水平線を中央に表示
 * - トレンド（最後 - 最初）で stroke 色を text-up / text-down / text-muted-foreground から選択
 * - 線幅 1.5、角丸線端
 * - 同色 8% 塗りのエリア付き
 */

interface SparklineProps {
  points: number[]
  width?: number
  height?: number
}

export function Sparkline({ points, width = 64, height = 22 }: SparklineProps) {
  if (points.length < 2) return null

  const pad = 1.5 // 線端が欠けないよう内側にパディング

  const minVal = Math.min(...points)
  const maxVal = Math.max(...points)
  const range = maxVal - minVal

  // y 座標の正規化（min==max のとき中央水平線）
  const toY = (v: number): number => {
    if (range === 0) return height / 2
    return pad + ((maxVal - v) / range) * (height - pad * 2)
  }

  // x 座標（等間隔）
  const toX = (i: number): number =>
    pad + (i / (points.length - 1)) * (width - pad * 2)

  const coords = points.map((v, i) => ({ x: toX(i), y: toY(v) }))

  // ポリライン文字列
  const linePoints = coords.map(({ x, y }) => `${x},${y}`).join(' ')

  // エリア（閉じたパス: 折れ線 → 右下 → 左下 → 閉じる）
  const areaPath =
    `M ${coords.map(({ x, y }) => `${x},${y}`).join(' L ')} ` +
    `L ${coords[coords.length - 1].x},${height} ` +
    `L ${coords[0].x},${height} Z`

  const trend = points[points.length - 1] - points[0]
  const colorClass =
    trend > 0 ? 'text-up' : trend < 0 ? 'text-down' : 'text-muted-foreground'

  return (
    <svg
      width={width}
      height={height}
      viewBox={`0 0 ${width} ${height}`}
      className={`overflow-visible flex-shrink-0 ${colorClass}`}
      aria-hidden="true"
    >
      {/* エリア塗り（8% 不透明度） */}
      <path
        d={areaPath}
        fill="currentColor"
        fillOpacity={0.08}
        stroke="none"
      />
      {/* 折れ線 */}
      <polyline
        points={linePoints}
        fill="none"
        stroke="currentColor"
        strokeWidth={1.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
