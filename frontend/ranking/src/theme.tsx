/* ============================================================
   MUI ダークテーマ（ranking）
   oc-app の themeMui.tsx と同内容（プロジェクト間で import できないため複製。
   値の正は旧試験実装 app.tsx — 変更時は oc-app 側と同期すること）。
   ============================================================ */
import { useEffect, useMemo, useState, ReactNode } from 'react'
import { ThemeProvider, createTheme } from '@mui/material'

export function isDarkMode(): boolean {
  const attr = document.documentElement.getAttribute('data-theme')
  if (attr === 'dark') return true
  if (attr === 'light') return false
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function onThemeChange(cb: (isDark: boolean) => void): () => void {
  const handler = () => cb(isDarkMode())
  document.addEventListener('octhemechange', handler)
  const mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null
  mq?.addEventListener?.('change', handler)
  return () => {
    document.removeEventListener('octhemechange', handler)
    mq?.removeEventListener?.('change', handler)
  }
}

export function useIsDark(): boolean {
  const [isDark, setIsDark] = useState<boolean>(() => isDarkMode())
  useEffect(() => onThemeChange(setIsDark), [])
  return isDark
}

export function buildMuiTheme(isDark: boolean) {
  return createTheme({
    palette: {
      mode: isDark ? 'dark' : 'light',
      ...(isDark && {
        background: {
          default: '#000000',
          paper: '#16181c',
        },
        primary: {
          main: '#1d9bf0',
          light: '#7dd3fc',
          dark: '#3b82f6',
        },
        text: {
          primary: '#f5f7f8',
          secondary: '#71767b',
        },
        divider: '#16181c',
      }),
    },
    components: {
      MuiButton: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            fontWeight: 500,
            ...(isDark && {
              '&.MuiButton-text': {
                color: '#f5f7f8',
                '&:hover': {
                  backgroundColor: 'rgba(231, 233, 234, 0.1)',
                },
              },
            }),
          },
        },
      },
    },
  })
}

export function OcThemeProvider({ children }: { children: ReactNode }) {
  const isDark = useIsDark()
  const theme = useMemo(() => buildMuiTheme(isDark), [isDark])
  return <ThemeProvider theme={theme}>{children}</ThemeProvider>
}
