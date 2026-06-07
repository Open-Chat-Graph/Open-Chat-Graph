import type { ReactNode } from 'react'
import { Button } from '@/components/ui/button'

interface EmptyStateProps {
  /** Lucide アイコン等。primaryカラーで描画されます */
  icon: ReactNode
  /** 1行の見出し */
  title: string
  /** 1行の説明文（muted） */
  description: string
  /** CTA ボタン（最大1つ）。不要なら省略 */
  action?: {
    label: string
    onClick: () => void
    icon?: ReactNode
  }
  /** 補足文。アクションの下に出る小さなテキスト */
  hint?: string
  /**
   * ゴーストプレビュースロット。opacity-40 でラップして
   * サンプルカード風ダミーを載せられる（任意）。
   */
  children?: ReactNode
  /** 小型版（セクション内の空箱 etc.） */
  small?: boolean
}

/**
 * 共通 EmptyState コンポーネント。
 * 破線枠・灰色箱をやめ、primaryグラデーション丸枠 + アイコン + 見出し + 説明 + CTA で統一。
 */
export function EmptyState({
  icon,
  title,
  description,
  action,
  hint,
  children,
  small = false,
}: EmptyStateProps) {
  return (
    <div
      className={`flex flex-col items-center text-center ${
        small ? 'gap-2 py-4 px-3' : 'gap-4 py-10 px-4'
      }`}
    >
      {/* アイコン背景（primaryグラデーション丸枠） */}
      <div
        className={`flex items-center justify-center rounded-full bg-gradient-to-br from-primary/20 to-primary/5 text-primary flex-shrink-0 ${
          small ? 'h-10 w-10' : 'h-16 w-16'
        }`}
      >
        <span className={small ? '[&>svg]:h-5 [&>svg]:w-5' : '[&>svg]:h-7 [&>svg]:w-7'}>
          {icon}
        </span>
      </div>

      {/* テキスト */}
      <div className={`space-y-1 ${small ? 'max-w-[18rem]' : 'max-w-xs'}`}>
        <p className={`font-semibold ${small ? 'text-sm' : 'text-base'}`}>{title}</p>
        <p className={`text-muted-foreground leading-relaxed ${small ? 'text-xs' : 'text-sm'}`}>
          {description}
        </p>
      </div>

      {/* CTA */}
      {action && (
        <Button
          size={small ? 'sm' : 'default'}
          className={`gap-1.5 ${small ? 'h-8 text-xs' : ''}`}
          onClick={action.onClick}
        >
          {action.icon && (
            <span className="[&>svg]:h-4 [&>svg]:w-4">{action.icon}</span>
          )}
          {action.label}
        </Button>
      )}

      {/* 補足文 */}
      {hint && (
        <p className="text-xs text-muted-foreground max-w-[16rem]">{hint}</p>
      )}

      {/* ゴーストプレビュースロット */}
      {children && (
        <div className="w-full opacity-40 pointer-events-none select-none" aria-hidden="true">
          {children}
        </div>
      )}
    </div>
  )
}
