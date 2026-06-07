import { memo, useState, useRef } from 'react'
import { Plus, Check, AlertCircle, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Card, CardContent } from '@/components/ui/card'
import { OfficialIcon, SpecialIcon } from '@/components/icons'
import { Sparkline } from '@/components/Common/Sparkline'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import { searchHighlightClass } from '@/lib/theme-colors'
import { diffColorClass, diffBgClass } from '@/lib/colors'
import type { OpenChat } from '@/types/api'

interface OpenChatCardProps {
  chat: OpenChat
  inMyList?: boolean
  onCardClick: (chatId: number) => void
  onAddToMyList?: (chatId: number, event: React.MouseEvent) => void
  // マイリスト用のプロップ
  onRemove?: (chatId: number) => void
  selectionMode?: boolean
  isSelected?: boolean
  onToggleSelection?: (chatId: number) => void
  onRangeSelection?: (chatId: number, allItemIds: number[]) => void
  allItemIds?: number[]
  onEnterSelectionMode?: () => void
  // ソート条件（検索ページ用）
  currentSort?: string
  // 検索キーワード（ハイライト用）
  searchKeyword?: string
  // スパークライン（7日の人数推移ポイント列）。
  // sparklinePoints を渡すページだけ右端カラムが表示される（渡さない画面には影響しない）。
  sparklinePoints?: number[]
}

// ランキングデータが有効かチェック
const isValidRankingData = (value: number | null | undefined): boolean => {
  return value !== null && value !== undefined
}

// ソート軸 → カードに見せる成長指標のラベル
const sortMetricLabel = (sort?: string): string => {
  switch (sort) {
    case 'hourly_diff':
      return '1時間'
    case 'diff_24h':
      return '24時間'
    case 'diff_1w':
      return '1週間'
    default:
      return '24時間'
  }
}

// 開設日(api_created_at)を YYYY/MM/DD に整形。作成日ソート時にカードへ出す。
// api_created_at は実体が unix秒(number) で来る（型は string だが）。数値文字列・日時文字列も吸収。
const formatOpenDate = (raw?: string | number | null): string | null => {
  if (raw === undefined || raw === null || raw === '') return null
  let d: Date
  if (typeof raw === 'number' || /^\d+$/.test(String(raw))) {
    const n = Number(raw)
    d = new Date(n > 9999999999 ? n : n * 1000) // 10桁以下は秒とみなしてミリ秒へ
  } else {
    const s = String(raw)
    d = new Date(s.includes('T') ? s : s.replace(' ', 'T'))
  }
  if (!isNaN(d.getTime())) {
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
  }
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[1]}/${m[2]}/${m[3]}` : null
}

// 増減値の色クラス → lib/colors.ts へ移動

// 増減値の表示文字列（符号付き）
const formatDiff = (diff: number): string =>
  `${diff > 0 ? '+' : diff === 0 ? '±' : ''}${diff.toLocaleString()}`

// キーワード周辺でテキストをトリミング
const truncateAroundKeyword = (text: string, keyword: string, maxLength: number = 150): string => {
  if (!keyword || !text) {
    return text
  }

  // キーワードを分割（全角・半角スペース対応）
  const keywords = keyword.split(/[\s\u3000]+/).filter(k => k.length > 0)
  let firstMatchIndex = -1
  let matchedKeywordLength = 0

  // 最初に一致するキーワードを探す
  for (const kw of keywords) {
    const index = text.toLowerCase().indexOf(kw.toLowerCase())
    if (index !== -1) {
      firstMatchIndex = index
      matchedKeywordLength = kw.length
      break
    }
  }

  // キーワードが見つからない場合は先頭から
  if (firstMatchIndex === -1) {
    return text.length <= maxLength ? text : text.substring(0, maxLength) + '...'
  }

  // キーワードの前後に15文字ずつ余裕を持たせる
  const margin = 15
  const start = Math.max(0, firstMatchIndex - margin)
  const end = Math.min(text.length, firstMatchIndex + matchedKeywordLength + margin)

  const prefix = start > 0 ? '...' : ''
  const suffix = end < text.length ? '...' : ''

  return prefix + text.substring(start, end) + suffix
}

// テキスト内のキーワードをハイライト
const highlightText = (text: string, keyword: string): React.ReactElement => {
  if (!keyword || !text) {
    return <>{text}</>
  }

  // キーワードを分割（全角・半角スペース対応）
  const keywords = keyword.split(/[\s\u3000]+/).filter(k => k.length > 0)

  // 正規表現のメタ文字をエスケープ
  const escapedKeywords = keywords.map(k => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))

  // 正規表現を作成（大文字小文字無視）
  const pattern = new RegExp(`(${escapedKeywords.join('|')})`, 'gi')

  // テキストを分割
  const parts = text.split(pattern)

  return (
    <>
      {parts.map((part, i) => {
        // キーワードに一致するか確認
        const isMatch = keywords.some(k => part.toLowerCase() === k.toLowerCase())
        return isMatch ? <strong key={i} className={searchHighlightClass}>{part}</strong> : <span key={i}>{part}</span>
      })}
    </>
  )
}

export const OpenChatCard = memo(({
  chat,
  inMyList = false,
  onCardClick,
  onAddToMyList,
  onRemove,
  selectionMode = false,
  isSelected = false,
  onToggleSelection,
  onRangeSelection,
  allItemIds = [],
  onEnterSelectionMode,
  currentSort,
  searchKeyword,
  sparklinePoints,
}: OpenChatCardProps) => {
  const hasHourlyData = isValidRankingData(chat.increasedMember)
  const has24hData = isValidRankingData(chat.diff24h)
  const has1wData = isValidRankingData(chat.diff1w)
  const isNotInRanking = !chat.isInRanking

  // ソートが1時間・24時間・1週間で、該当データがN/Aの場合はカードの透明度を上げる
  const shouldReduceOpacity =
    (currentSort === 'hourly_diff' && !hasHourlyData) ||
    (currentSort === 'diff_24h' && !has24hData) ||
    (currentSort === 'diff_1w' && !has1wData)

  // 本家同様、成長指標は「成長系ソートのときだけ」その軸を見せる。
  // 人数/作成日ソートでは右端に数字を出さない（メンバー数はメタ行に既出）。
  const metricKey: 'hourly_diff' | 'diff_24h' | 'diff_1w' | null =
    currentSort === 'hourly_diff' || currentSort === 'diff_24h' || currentSort === 'diff_1w'
      ? currentSort
      : null
  const metric =
    metricKey === 'hourly_diff'
      ? { has: hasHourlyData, diff: chat.increasedMember, percent: chat.percentageIncrease }
      : metricKey === 'diff_1w'
        ? { has: has1wData, diff: chat.diff1w, percent: chat.percent1w }
        : metricKey === 'diff_24h'
          ? { has: has24hData, diff: chat.diff24h, percent: chat.percent24h }
          : null

  const longPressTimerRef = useRef<number | null>(null)
  const [isPressing, setIsPressing] = useState(false)
  const mouseDownPosRef = useRef<{ x: number; y: number } | null>(null)
  const hasMovedRef = useRef(false)

  const handleMouseDown = (event: React.MouseEvent) => {
    mouseDownPosRef.current = { x: event.clientX, y: event.clientY }
    hasMovedRef.current = false
  }

  const handleMouseMove = (event: React.MouseEvent) => {
    if (mouseDownPosRef.current) {
      const deltaX = Math.abs(event.clientX - mouseDownPosRef.current.x)
      const deltaY = Math.abs(event.clientY - mouseDownPosRef.current.y)
      // 5px以上移動したらドラッグと判定
      if (deltaX > 5 || deltaY > 5) {
        hasMovedRef.current = true
      }
    }
  }

  const handleClick = (event: React.MouseEvent) => {
    // ドラッグしていた場合は何もしない
    if (hasMovedRef.current) {
      mouseDownPosRef.current = null
      hasMovedRef.current = false
      return
    }

    if (selectionMode && onToggleSelection) {
      // Shift+クリックで範囲選択
      if (event.shiftKey && onRangeSelection && allItemIds.length > 0) {
        onRangeSelection(chat.id, allItemIds)
      } else {
        onToggleSelection(chat.id)
      }
    } else {
      onCardClick(chat.id)
    }

    mouseDownPosRef.current = null
    hasMovedRef.current = false
  }

  const handleTouchStart = () => {
    if (selectionMode || !onEnterSelectionMode) return

    setIsPressing(true)
    longPressTimerRef.current = window.setTimeout(() => {
      onEnterSelectionMode()
      setTimeout(() => {
        if (onToggleSelection) {
          onToggleSelection(chat.id)
        }
      }, 50)
    }, 500)
  }

  const handleTouchEnd = () => {
    setIsPressing(false)
    if (longPressTimerRef.current) {
      clearTimeout(longPressTimerRef.current)
      longPressTimerRef.current = null
    }
  }

  const handleTouchMove = () => {
    setIsPressing(false)
    if (longPressTimerRef.current) {
      clearTimeout(longPressTimerRef.current)
      longPressTimerRef.current = null
    }
  }

  const handleContextMenu = (event: React.MouseEvent | React.TouchEvent) => {
    // コンテキストメニューを防止
    event.preventDefault()
  }

  return (
    <Card
      data-testid={`openchat-card-${chat.id}`}
      className={`hover:shadow-md transition-shadow cursor-pointer overflow-hidden select-none ${
        isSelected ? 'bg-accent' : ''
      } ${isPressing ? 'scale-[0.98]' : ''} ${shouldReduceOpacity ? 'opacity-50' : ''}`}
      onClick={handleClick}
      onMouseDown={handleMouseDown}
      onMouseMove={handleMouseMove}
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
      onTouchMove={handleTouchMove}
      onContextMenu={handleContextMenu}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {selectionMode && (
          <Checkbox
            checked={isSelected}
            onCheckedChange={() => onToggleSelection?.(chat.id)}
            onClick={(e) => e.stopPropagation()}
            className="flex-shrink-0 mt-0.5"
            data-testid={`checkbox-${chat.id}`}
          />
        )}
        {chat.img && (
          <img
            src={imgPreviewUrl(chat.img)}
            alt={chat.name}
            className="w-12 h-12 md:w-14 md:h-14 rounded-full object-cover flex-shrink-0"
          />
        )}

        <div className="flex-1 min-w-0">
          {/* 名前 */}
          <h3 className="text-[15px] md:text-base font-semibold leading-snug break-words line-clamp-2">
            {chat.emblem === 2 && (
              <OfficialIcon className="w-[18px] h-[18px] inline-block align-text-bottom mr-1" />
            )}
            {chat.emblem === 1 && (
              <SpecialIcon className="w-[19px] h-[18px] inline-block align-text-bottom mr-1" />
            )}
            {searchKeyword ? highlightText(chat.name, searchKeyword) : chat.name}
          </h3>

          {/* 説明（本家相当：小さめ・行間詰め・2行） */}
          {chat.desc && (
            <p className="mt-0.5 text-xs leading-snug text-muted-foreground break-words line-clamp-2">
              {searchKeyword ? highlightText(truncateAroundKeyword(chat.desc, searchKeyword), searchKeyword) : chat.desc}
            </p>
          )}

          {/* メタ行：メンバー数 ・ カテゴリ ＋ ソート軸の成長指標 */}
          <div className="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
            <span className="font-semibold text-foreground">
              メンバー {formatMemberCompact(chat.member)}
            </span>
            {chat.categoryName && (
              <>
                <span aria-hidden className="opacity-50">・</span>
                <span className="truncate">{chat.categoryName}</span>
              </>
            )}

            {currentSort === 'created_at' && formatOpenDate(chat.registeredAt) ? (
              <span className="ml-auto whitespace-nowrap">
                <span className="text-muted-foreground">{formatOpenDate(chat.registeredAt)} 開設</span>
              </span>
            ) : isNotInRanking ? (
              <Badge variant="secondary" className="ml-auto flex items-center gap-1 text-[11px] h-5 px-1.5 font-normal">
                <AlertCircle className="h-3 w-3" />
                非掲載
              </Badge>
            ) : metricKey && metric && metric.has ? (
              <span className="ml-auto whitespace-nowrap">
                <span className="text-muted-foreground">{sortMetricLabel(metricKey)} </span>
                <span className={`font-semibold ${diffColorClass(metric.diff)}`}>
                  {formatDiff(metric.diff)}
                  {metric.percent !== null && metric.percent !== undefined && metric.diff !== 0 && (
                    <span className="ml-0.5 font-normal">({metric.percent > 0 ? '+' : ''}{metric.percent.toFixed(1)}%)</span>
                  )}
                </span>
              </span>
            ) : null}
          </div>
        </div>

        {/* スパークライン＋増減チップ（w-16 確保・未着時は幅だけ保持） */}
        {(sparklinePoints !== undefined || (metricKey && metric && metric.has)) && (
          <div className="flex-shrink-0 flex flex-col items-end gap-1 w-16">
            {sparklinePoints !== undefined && sparklinePoints.length >= 2 ? (
              <Sparkline points={sparklinePoints} width={64} height={22} />
            ) : (
              <div style={{ width: 64, height: 22 }} />
            )}
            {metricKey && metric && metric.has && metric.diff !== 0 && (
              <span
                className={`inline-block rounded-full px-1.5 py-0 text-[11px] font-mono leading-5 whitespace-nowrap ${diffBgClass(metric.diff)}`}
              >
                {formatDiff(metric.diff)}
              </span>
            )}
          </div>
        )}

        {/* 追加 / 削除ボタン */}
        {!selectionMode && (onRemove || onAddToMyList) && (
          <div className="flex-shrink-0 -mr-1">
            {onRemove ? (
              <Button
                variant="ghost"
                size="icon"
                className="relative h-8 w-8 before:absolute before:-inset-1.5 before:content-['']"
                onClick={(e) => {
                  e.stopPropagation()
                  onRemove(chat.id)
                }}
                data-testid={`remove-button-${chat.id}`}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            ) : onAddToMyList ? (
              <Button
                variant={inMyList ? 'secondary' : 'ghost'}
                size="icon"
                className="relative h-8 w-8 before:absolute before:-inset-1.5 before:content-['']"
                onClick={(e) => onAddToMyList(chat.id, e)}
                disabled={inMyList}
              >
                {inMyList ? <Check className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
              </Button>
            ) : null}
          </div>
        )}
      </CardContent>
    </Card>
  )
})

OpenChatCard.displayName = 'OpenChatCard'
