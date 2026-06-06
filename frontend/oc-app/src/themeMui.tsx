/* ============================================================
   MUI ダークテーマ（oc-app: graph / comments 共用）
   値の正: 旧試験実装 src/app.tsx の createTheme（shadcn/ui Slate 準拠、
   ボタン・選択状態まで調整済み）。値を変えずに移植。
   テーマ判定・購読は graph/util/theme.ts（octhemechange）に従う。
   ============================================================ */
import { useEffect, useMemo, useState, ReactNode } from 'react'
import { ThemeProvider, createTheme } from '@mui/material'
import { isDarkMode, onThemeChange } from './graph/util/theme'

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
          default: '#0f172a', // slate-900
          paper: '#1e293b', // slate-800
        },
        primary: {
          main: '#5b9cf6', // shadcn primary
          light: '#7dd3fc', // sky-300
          dark: '#3b82f6', // blue-500
        },
        text: {
          primary: '#f8fafc', // shadcn foreground
          secondary: '#94a3b8', // shadcn muted-foreground
        },
        divider: '#1e293b', // slate-800
      }),
    },
    components: {
      MuiButton: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            fontWeight: 500,
            ...(isDark && {
              '&.MuiButton-contained': {
                backgroundColor: '#5b9cf6',
                color: '#0f172a',
                '&:hover': {
                  backgroundColor: '#7dd3fc',
                },
                '&:disabled': {
                  backgroundColor: '#334155',
                  color: '#94a3b8',
                },
              },
              '&.MuiButton-outlined': {
                borderColor: '#475569',
                borderWidth: '1.5px',
                color: '#f8fafc',
                '&:hover': {
                  borderColor: '#5b9cf6',
                  backgroundColor: 'rgba(91, 156, 246, 0.15)',
                },
                '&:disabled': {
                  borderColor: '#334155',
                  color: '#64748b',
                },
              },
              '&.MuiButton-text': {
                color: '#f8fafc',
                '&:hover': {
                  backgroundColor: 'rgba(148, 163, 184, 0.1)',
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
