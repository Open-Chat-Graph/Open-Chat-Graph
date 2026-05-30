import { Sun, Moon, Monitor } from 'lucide-react'
import { useTheme } from '@/providers/theme-provider'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export function ThemeToggle() {
  const { theme, setTheme } = useTheme()

  const themes = [
    { value: 'light' as const, label: 'ライト', icon: Sun },
    { value: 'dark' as const, label: 'ダーク', icon: Moon },
    { value: 'system' as const, label: 'AUTO', icon: Monitor },
  ]

  return (
    <div className="flex gap-2">
      {themes.map(({ value, label, icon: Icon }) => (
        <Button
          key={value}
          variant={theme === value ? 'default' : 'outline'}
          size="sm"
          onClick={() => setTheme(value)}
          className={cn(
            'flex-1 gap-2',
            theme === value && 'pointer-events-none'
          )}
        >
          <Icon className="h-4 w-4" />
          {label}
        </Button>
      ))}
    </div>
  )
}
