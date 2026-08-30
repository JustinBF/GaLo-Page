import { useTheme } from '../../theme/ThemeContext'

export function ThemeToggle() {
  const { theme, toggle } = useTheme()
  const isDark = theme === 'dark'

  return (
    <button
      className="round-btn theme-toggle"
      type="button"
      onClick={toggle}
      title={isDark ? 'Cambiar a modo día' : 'Cambiar a modo noche'}
      aria-label={isDark ? 'Cambiar a modo día' : 'Cambiar a modo noche'}
      aria-pressed={isDark}
    >
      <span
        className="theme-toggle__icon"
        style={{ transform: isDark ? 'rotate(0deg)' : 'rotate(180deg)' }}
      >
        {isDark ? '🌙' : '☀️'}
      </span>
    </button>
  )
}
