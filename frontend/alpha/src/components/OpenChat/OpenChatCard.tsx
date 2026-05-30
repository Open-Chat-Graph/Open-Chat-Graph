import { memo, useState, useRef } from 'react'
import { Plus, Check, AlertCircle, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { OfficialIcon, SpecialIcon } from '@/components/icons'
import { imgPreviewUrl } from '@/lib/imageUrl'
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
}

// ランキングデータが有効かチェック
const isValidRankingData = (value: number | null | undefined): boolean => {
  return value !== null && value !== undefined
}

// 入室タイプのラベルを取得
const getJoinMethodLabel = (type: number) => {
  switch (type) {
    case 1:
      return '承認制'
    case 2:
      return '参加コード'
    default:
      return '全体公開'
  }
}

// タイムスタンプを日付文字列に変換
const formatTimestamp = (timestamp: string | number): string => {
  // 数値の場合
  if (typeof timestamp === 'number') {
    const ms = timestamp > 9999999999 ? timestamp / 1000 : timestamp * 1000
    return new Date(ms).toLocaleDateString('ja-JP')
  }
  // 文字列で数値のみの場合
  if (typeof timestamp === 'string' && timestamp.match(/^\d+$/)) {
    const ts = parseInt(timestamp)
    const ms = ts > 9999999999 ? ts / 1000 : ts * 1000
    return new Date(ms).toLocaleDateString('ja-JP')
  }
  // その他の場合はそのまま返す
  return String(timestamp)
}

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
        return isMatch ? <strong key={i} className="font-bold text-blue-600 dark:text-blue-400">{part}</strong> : <span key={i}>{part}</span>
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
      <CardHeader className="p-3 md:p-6">
        <div className="flex items-start justify-between gap-2 md:gap-4">
          <div className="flex items-start gap-2 md:gap-4 flex-1 min-w-0">
            {selectionMode && (
              <Checkbox
                checked={isSelected}
                onCheckedChange={() => onToggleSelection?.(chat.id)}
                onClick={(e) => e.stopPropagation()}
                className="flex-shrink-0 mt-1"
                data-testid={`checkbox-${chat.id}`}
              />
            )}
            {chat.img && (
              <img
                src={imgPreviewUrl(chat.img)}
                alt={chat.name}
                className="w-12 h-12 md:w-16 md:h-16 rounded-full object-cover flex-shrink-0"
              />
            )}
            <div className="flex-1 min-w-0">
              <CardTitle className="text-base md:text-lg break-words mb-1">
                {chat.emblem === 2 && (
                  <OfficialIcon className="w-5 h-5 inline-block align-middle mr-1 md:mr-2 -mt-0.5" />
                )}
                {chat.emblem === 1 && (
                  <SpecialIcon className="w-[21px] h-5 inline-block align-middle mr-1 md:mr-2 -mt-0.5" />
                )}
                {searchKeyword ? highlightText(chat.name, searchKeyword) : chat.name}
              </CardTitle>
              {chat.desc && (
                <CardDescription className="line-clamp-2 break-words">
                  {searchKeyword ? highlightText(truncateAroundKeyword(chat.desc, searchKeyword), searchKeyword) : chat.desc}
                </CardDescription>
              )}
            </div>
          </div>
          {!selectionMode && (
            <>
              {onRemove ? (
                <Button
                  variant="ghost"
                  size="icon"
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
                  variant={inMyList ? "secondary" : "ghost"}
                  size="icon"
                  onClick={(e) => onAddToMyList(chat.id, e)}
                  disabled={inMyList}
                >
                  {inMyList ? (
                    <Check className="h-4 w-4" />
                  ) : (
                    <Plus className="h-4 w-4" />
                  )}
                </Button>
              ) : null}
            </>
          )}
        </div>
      </CardHeader>
      <CardContent className="p-3 pt-0 md:p-6 md:pt-0">
        <div className="flex flex-wrap gap-x-2 gap-y-1 text-sm">
          <div className="whitespace-nowrap">
            <span className="text-muted-foreground text-xs">メンバー: </span>
            <span className="font-semibold">{chat.member.toLocaleString()}人</span>
          </div>
          <div className="whitespace-nowrap">
            <span className="text-muted-foreground text-xs">入室: </span>
            <span>
              {getJoinMethodLabel(chat.join_method_type)}
            </span>
          </div>
          {chat.categoryName && (
            <div className="break-words">
              <span className="text-muted-foreground text-xs">カテゴリ: </span>
              <span>{chat.categoryName}</span>
            </div>
          )}

          {/* 改行 */}
          <div className="w-full" />

          {isNotInRanking ? (
            <>
              {/* 1週間ソート時は1週間統計を先に表示 */}
              {currentSort === 'diff_1w' ? (
                <>
                  <div className="whitespace-nowrap">
                    <span className={`text-muted-foreground text-xs ${currentSort === 'diff_1w' ? 'font-bold' : ''}`}>1週間: </span>
                    {has1wData ? (
                      <span className={`${currentSort === 'diff_1w' ? 'font-bold' : ''} ${chat.diff1w > 0 ? 'text-green-600 dark:text-green-500' : chat.diff1w < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                        {chat.diff1w > 0 ? '+' : chat.diff1w === 0 ? '±' : ''}{chat.diff1w.toLocaleString()}
                        {chat.percent1w !== null && chat.percent1w !== undefined && chat.diff1w !== 0 && (
                          <span className="text-xs ml-1">({chat.percent1w > 0 ? '+' : ''}{chat.percent1w.toFixed(1)}%)</span>
                        )}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">N/A</span>
                    )}
                  </div>
                  <Badge variant="secondary" className="flex items-center gap-1 text-xs h-5">
                    <AlertCircle className="h-3 w-3" />
                    ランキング非掲載
                  </Badge>
                </>
              ) : (
                <>
                  <Badge variant="secondary" className="flex items-center gap-1 text-xs h-5">
                    <AlertCircle className="h-3 w-3" />
                    ランキング非掲載
                  </Badge>
                  <div className="whitespace-nowrap">
                    <span className="text-muted-foreground text-xs">1週間: </span>
                    {has1wData ? (
                      <span className={chat.diff1w > 0 ? 'text-green-600 dark:text-green-500' : chat.diff1w < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}>
                        {chat.diff1w > 0 ? '+' : chat.diff1w === 0 ? '±' : ''}{chat.diff1w.toLocaleString()}
                        {chat.percent1w !== null && chat.percent1w !== undefined && chat.diff1w !== 0 && (
                          <span className="text-xs ml-1">({chat.percent1w > 0 ? '+' : ''}{chat.percent1w.toFixed(1)}%)</span>
                        )}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">N/A</span>
                    )}
                  </div>
                </>
              )}
            </>
          ) : (
            <>
              {/* 統計値を定義 */}
              {(() => {
                const hourlyDiv = (
                  <div key="hourly" className="whitespace-nowrap">
                    <span className={`text-muted-foreground text-xs ${currentSort === 'hourly_diff' ? 'font-bold' : ''}`}>1時間: </span>
                    {hasHourlyData ? (
                      <span className={`${currentSort === 'hourly_diff' ? 'font-bold' : ''} ${chat.increasedMember > 0 ? 'text-green-600 dark:text-green-500' : chat.increasedMember < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                        {chat.increasedMember > 0 ? '+' : chat.increasedMember === 0 ? '±' : ''}{chat.increasedMember.toLocaleString()}
                        {chat.percentageIncrease !== null && chat.percentageIncrease !== undefined && chat.increasedMember !== 0 && (
                          <span className="text-xs ml-1">({chat.percentageIncrease > 0 ? '+' : ''}{chat.percentageIncrease.toFixed(1)}%)</span>
                        )}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">N/A</span>
                    )}
                  </div>
                )

                const daily24hDiv = (
                  <div key="24h" className="whitespace-nowrap">
                    <span className={`text-muted-foreground text-xs ${currentSort === 'diff_24h' ? 'font-bold' : ''}`}>24時間: </span>
                    {has24hData ? (
                      <span className={`${currentSort === 'diff_24h' ? 'font-bold' : ''} ${chat.diff24h > 0 ? 'text-green-600 dark:text-green-500' : chat.diff24h < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                        {chat.diff24h > 0 ? '+' : chat.diff24h === 0 ? '±' : ''}{chat.diff24h.toLocaleString()}
                        {chat.percent24h !== null && chat.percent24h !== undefined && chat.diff24h !== 0 && (
                          <span className="text-xs ml-1">({chat.percent24h > 0 ? '+' : ''}{chat.percent24h.toFixed(1)}%)</span>
                        )}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">N/A</span>
                    )}
                  </div>
                )

                const weekly1wDiv = (
                  <div key="1w" className="whitespace-nowrap">
                    <span className={`text-muted-foreground text-xs ${currentSort === 'diff_1w' ? 'font-bold' : ''}`}>1週間: </span>
                    {has1wData ? (
                      <span className={`${currentSort === 'diff_1w' ? 'font-bold' : ''} ${chat.diff1w > 0 ? 'text-green-600 dark:text-green-500' : chat.diff1w < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                        {chat.diff1w > 0 ? '+' : chat.diff1w === 0 ? '±' : ''}{chat.diff1w.toLocaleString()}
                        {chat.percent1w !== null && chat.percent1w !== undefined && chat.diff1w !== 0 && (
                          <span className="text-xs ml-1">({chat.percent1w > 0 ? '+' : ''}{chat.percent1w.toFixed(1)}%)</span>
                        )}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">N/A</span>
                    )}
                  </div>
                )

                // ソート条件に応じて順番を変更
                if (currentSort === 'diff_24h') {
                  return [daily24hDiv, hourlyDiv, weekly1wDiv]
                } else if (currentSort === 'diff_1w') {
                  return [weekly1wDiv, hourlyDiv, daily24hDiv]
                }
                // デフォルト順（hourly_diff、member、created_at、または未指定）
                return [hourlyDiv, daily24hDiv, weekly1wDiv]
              })()}
            </>
          )}

          {/* 改行 */}
          <div className="w-full" />

          {chat.registeredAt && (
            <div className="whitespace-nowrap">
              <span className="text-muted-foreground text-xs">作成: </span>
              <span>{formatTimestamp(chat.registeredAt)}</span>
            </div>
          )}
          {chat.createdAt && (
            <div className="whitespace-nowrap">
              <span className="text-muted-foreground text-xs">登録: </span>
              <span>{new Date(chat.createdAt * 1000).toLocaleDateString('ja-JP')}</span>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
})

OpenChatCard.displayName = 'OpenChatCard'
