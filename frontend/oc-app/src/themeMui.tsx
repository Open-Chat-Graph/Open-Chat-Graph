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
          default: '#000000', // slate-900
          paper: '#16181c', // slate-800
        },
        primary: {
          main: '#1d9bf0', // shadcn primary
          light: '#7dd3fc', // sky-300
          dark: '#3b82f6', // blue-500
        },
        text: {
          primary: '#f5f7f8', // shadcn foreground
          secondary: '#7d8287', // shadcn muted-foreground
        },
        divider: '#16181c', // slate-800
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
                backgroundColor: '#1d9bf0',
                color: '#000000',
                '&:hover': {
                  backgroundColor: '#7dd3fc',
                },
                '&:disabled': {
                  backgroundColor: '#2f3336',
                  color: '#7d8287',
                },
              },
              '&.MuiButton-outlined': {
                borderColor: '#3f4347',
                borderWidth: '1.5px',
                color: '#f5f7f8',
                '&:hover': {
                  borderColor: '#1d9bf0',
                  backgroundColor: 'rgba(29, 155, 240, 0.15)',
                },
                '&:disabled': {
                  borderColor: '#2f3336',
                  color: '#7d8287',
                },
              },
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
      MuiTab: {
        styleOverrides: {
          root: {
            ...(isDark && {
              color: '#7d8287',
              opacity: 1,
              '&.Mui-selected': {
                color: '#f5f7f8',
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
