import * as React from 'react'
import * as PopoverPrimitive from '@radix-ui/react-popover'
import { cn } from '@/lib/utils'

interface InfoChipProps {
  /** トリガー（省略表示する本文）。クリック/タップ・ホバーでチップを開く */
  trigger: React.ReactNode
  /** チップ内に出す内容（全文ラベル・URL など） */
  children: React.ReactNode
  className?: string
  side?: 'top' | 'bottom' | 'left' | 'right'
  align?: 'start' | 'center' | 'end'
  triggerClassName?: string
}

/**
 * 省略表示された項目の「全貌」をタップ／ホバーで見せる小さなチップ（ポップオーバー）。
 *
 * リスト行は幅が限られ truncate するため、詳細（全文ラベル・元URL 等）をここに逃がす。
 * Radix Popover を Portal で body に出すので、overflow-y-auto の窓の中でもクリップされない。
 * 重ね順は tailwind の `z-popover`(75) トークン（ヘッダ60 より上）。生 z-[NN] は使わない。
 *
 * - クリック／タップで開く。外側クリック（や Esc）で閉じる（Radix Popover の既定）。
 *   ホバーでは開かない（誤爆・タッチでの張り付き回避）。
 */
export function InfoChip({
  trigger,
  children,
  className,
  side = 'top',
  align = 'start',
  triggerClassName,
}: InfoChipProps) {
  return (
    <PopoverPrimitive.Root>
      <PopoverPrimitive.Trigger asChild>
        <button
          type="button"
          className={cn('min-w-0 cursor-pointer text-left', triggerClassName)}
        >
          {trigger}
        </button>
      </PopoverPrimitive.Trigger>
      <PopoverPrimitive.Portal>
        <PopoverPrimitive.Content
          side={side}
          align={align}
          sideOffset={6}
          collisionPadding={8}
          onOpenAutoFocus={(e) => e.preventDefault()}
          className={cn(
            'z-popover max-w-[min(20rem,calc(100vw-1rem))] rounded-md border bg-popover px-3 py-2 text-xs leading-relaxed text-popover-foreground shadow-md',
            'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2',
            className,
          )}
        >
          {children}
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}
