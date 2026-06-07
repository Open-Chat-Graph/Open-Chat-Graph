import { memo } from 'react'
import { useNavigate } from 'react-router-dom'
import { FolderOpen, Sparkles } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { formatMemberCompact } from '@/lib/formatMember'
import { timeAgo, formatDateTime } from './timeAgo'
import type { FolderAdd } from '@/types/api'

interface FolderAddCardProps {
  item: FolderAdd
  onOpen: (item: FolderAdd) => void
}

/**
 * フォルダへの自動追加通知カード（type: 'folder_add'）。
 *
 * - サマリ通知（payload.count あり）: 初回フィルで複数部屋をまとめた1通。
 *   「フォルダ『◯◯』に N 件をまとめて追加しました」＋サンプル部屋名を表示。
 *   タップでフォルダ一覧へ遷移（個別ルームではなくフォルダを見てもらう）。
 * - 個別通知（payload.count なし）: 毎時の新着追加。
 *   タップで追加された部屋の詳細ページへ遷移する。
 */
export const FolderAddCard = memo(({ item, onOpen }: FolderAddCardProps) => {
  const navigate = useNavigate()
  const isSummary = item.payload.count !== undefined

  const handleClick = () => {
    onOpen(item)
    if (isSummary) {
      // サマリはフォルダ一覧（マイリスト）へ
      navigate('/mylist')
    } else {
      navigate(`/openchat/${item.payload.openChatId}`)
    }
  }

  // サマリ通知の表示文言を組み立てる
  const summaryTitle = isSummary
    ? `フォルダ「${item.payload.folderName}」に${item.payload.count}件をまとめて追加しました`
    : null

  const sampleLine =
    isSummary && item.payload.sampleNames && item.payload.sampleNames.length > 0
      ? (() => {
          const shown = item.payload.sampleNames!.slice(0, 3)
          const rest = (item.payload.count ?? 0) - shown.length
          return rest > 0
            ? shown.join('、') + ` ほか${rest}件`
            : shown.join('、')
        })()
      : null

  return (
    <Card
      data-testid={`folder-add-${item.id}`}
      onClick={handleClick}
      className={`cursor-pointer overflow-hidden transition-shadow hover:shadow-md select-none ${
        item.isRead ? '' : 'border-l-2 border-l-primary bg-primary/[0.03]'
      }`}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {/* フォルダアイコンプレースホルダー */}
        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-muted md:h-14 md:w-14">
          <Sparkles className="h-5 w-5 text-primary" />
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5">
            {!item.isRead && (
              <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" aria-label="未読" />
            )}
            <Badge variant="secondary" className="h-5 gap-1 px-1.5 text-[11px] font-normal">
              <FolderOpen className="h-3 w-3" />
              自動追加
            </Badge>
            <span className="ml-auto flex-shrink-0 text-[11px] text-muted-foreground">
              {timeAgo(item.createdAt)}
            </span>
          </div>

          {isSummary ? (
            /* サマリ通知 */
            <>
              <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
                {summaryTitle}
              </h3>
              {sampleLine && (
                <p className="mt-0.5 text-xs text-muted-foreground line-clamp-1">
                  {sampleLine}
                </p>
              )}
            </>
          ) : (
            /* 個別通知 */
            <>
              <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
                {item.payload.name}
              </h3>

              <p className="mt-0.5 text-xs text-muted-foreground">
                フォルダ「{item.payload.folderName}」に新着部屋が追加されました
              </p>

              <div className="mt-1.5 flex items-center gap-x-1.5 text-xs text-muted-foreground">
                <span className="font-semibold text-foreground">
                  メンバー {formatMemberCompact(item.payload.member ?? 0)}
                </span>
              </div>
            </>
          )}

          <p className="mt-1 text-[11px] text-muted-foreground/80 tabular-nums">
            {formatDateTime(item.createdAt)} に追加
          </p>
        </div>
      </CardContent>
    </Card>
  )
})

FolderAddCard.displayName = 'FolderAddCard'
