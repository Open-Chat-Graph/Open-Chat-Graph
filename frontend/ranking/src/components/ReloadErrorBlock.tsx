import React from 'react'
import { t } from '../config/translation'

const containerStyle: React.CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  gap: '0.75rem',
  textAlign: 'center',
  padding: '1.5rem 1rem',
}

const messageStyle: React.CSSProperties = {
  fontSize: '0.9rem',
  color: 'var(--c-text-sub, #666)',
  lineHeight: 1.5,
}

const buttonStyle: React.CSSProperties = {
  display: 'inline-flex',
  alignItems: 'center',
  justifyContent: 'center',
  minHeight: '44px',
  padding: '0 1.5rem',
  borderRadius: '99rem',
  border: '1px solid var(--c-border, #ccc)',
  background: 'var(--c-bg, #fff)',
  color: 'var(--c-text, inherit)',
  font: 'inherit',
  fontWeight: 600,
  cursor: 'pointer',
}

// 5xx・ネットワークエラー時に出すインライン再読み込みUI。
// ボタン押下でページ全体ではなく、その場のデータだけ取り直す(onReload)
export default function ReloadErrorBlock({ onReload }: { onReload: () => void }) {
  return (
    <div style={containerStyle} role="alert">
      <div style={messageStyle}>
        {t('一時的に読み込めませんでした。時間をおいて再読み込みしてください。')}
      </div>
      <button type="button" style={buttonStyle} onClick={onReload}>
        {t('再読み込み')}
      </button>
    </div>
  )
}
