import { memo } from 'react'
import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from 'recharts'
import type { TooltipContentProps } from 'recharts'
import type { MergedRow } from './useFolderGraphData'
import { dataKeyFor } from './useFolderGraphData'

export interface RoomMeta {
  id: number
  name: string
  color: string
}

interface FolderOverlayChartProps {
  rows: MergedRow[]
  /** グラフに描く（チェックON）ルーム。順序は色割当と同じ。 */
  rooms: RoomMeta[]
}

// 日付ラベルを M/D に短縮（YYYY-MM-DD 前提。想定外はそのまま）
function shortDate(value: string): string {
  const m = /^\d{4}-(\d{2})-(\d{2})/.exec(value)
  if (!m) return value
  return `${parseInt(m[1], 10)}/${parseInt(m[2], 10)}`
}

function formatMember(value: number): string {
  return value.toLocaleString('ja-JP')
}

const CustomTooltip = ({
  active,
  payload,
  label,
  rooms,
}: TooltipContentProps<number, string> & { rooms: RoomMeta[] }) => {
  if (!active || !payload || payload.length === 0) return null

  // dataKey からルームを引いて、色チップ付きで人数を多い順に並べる。
  const lines = payload
    .map((p) => {
      const key = String(p.dataKey ?? '')
      const room = rooms.find((r) => dataKeyFor(r.id) === key)
      if (!room) return null
      const value = p.value
      if (value == null) return null
      return { room, value: Number(value) }
    })
    .filter((x): x is { room: RoomMeta; value: number } => x !== null)
    .sort((a, b) => b.value - a.value)

  if (lines.length === 0) return null

  return (
    <div className="rounded-md border bg-popover/95 px-3 py-2 text-xs shadow-md backdrop-blur-sm">
      <div className="mb-1.5 font-semibold text-popover-foreground">{shortDate(String(label))}</div>
      <div className="space-y-1">
        {lines.map(({ room, value }) => (
          <div key={room.id} className="flex items-center gap-2">
            <span
              className="h-2.5 w-2.5 flex-shrink-0 rounded-full"
              style={{ backgroundColor: room.color }}
            />
            <span className="max-w-[140px] truncate text-muted-foreground">{room.name}</span>
            <span className="ml-auto font-medium tabular-nums text-popover-foreground">
              {formatMember(value)}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}

/**
 * フォルダ配下ルームのメンバー数を1つに重ねる線グラフ。
 * - 表示するのは props.rooms（チェックON）だけ。OFFは Line を描かない。
 * - 欠損(null)は connectNulls={false} で線を途切れさせ、データの実態を偽らない。
 * - 期間の絞り込みは呼び出し側で rows を加工して渡す（ここは渡された行をそのまま描く）。
 */
export const FolderOverlayChart = memo(({ rows, rooms }: FolderOverlayChartProps) => {
  const tickColor = 'hsl(var(--muted-foreground))'
  const gridColor = 'hsl(var(--border))'

  const formatYTick = (v: number) => {
    if (v >= 10000) return `${(v / 10000).toFixed(v % 10000 === 0 ? 0 : 1)}万`
    return v.toLocaleString('ja-JP')
  }

  return (
    <ResponsiveContainer width="100%" height="100%">
      <LineChart data={rows} margin={{ top: 8, right: 12, bottom: 4, left: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke={gridColor} vertical={false} />
        <XAxis
          dataKey="date"
          tickFormatter={shortDate}
          tick={{ fill: tickColor, fontSize: 11 }}
          stroke={gridColor}
          minTickGap={28}
          tickMargin={6}
        />
        <YAxis
          allowDecimals={false}
          width={52}
          tick={{ fill: tickColor, fontSize: 11 }}
          stroke={gridColor}
          tickFormatter={formatYTick}
          domain={['auto', 'auto']}
        />
        <Tooltip
          content={(props) => (
            <CustomTooltip {...(props as TooltipContentProps<number, string>)} rooms={rooms} />
          )}
          cursor={{ stroke: gridColor, strokeWidth: 1 }}
        />
        {rooms.map((room) => (
          <Line
            key={room.id}
            type="monotone"
            dataKey={dataKeyFor(room.id)}
            name={room.name}
            stroke={room.color}
            strokeWidth={2}
            dot={false}
            activeDot={{ r: 3.5, strokeWidth: 0 }}
            connectNulls={false}
            isAnimationActive={false}
          />
        ))}
      </LineChart>
    </ResponsiveContainer>
  )
})

FolderOverlayChart.displayName = 'FolderOverlayChart'
