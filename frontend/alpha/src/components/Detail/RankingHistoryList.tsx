import type { RankingHistoryItem } from '@/types/api'
import { RankingHistoryItemCard } from './RankingHistoryItemCard'

export function RankingHistoryList({ items }: { items: RankingHistoryItem[] }) {
  return (
    <ul className="space-y-3">
      {items.map((item, i) => (
        <RankingHistoryItemCard key={i} item={item} />
      ))}
    </ul>
  )
}
