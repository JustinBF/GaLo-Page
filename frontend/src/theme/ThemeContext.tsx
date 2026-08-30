import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

export type Theme = 'light' | 'dark'

const STORAGE_KEY = 'galo.theme'

interface ThemeState {
  theme: Theme
  toggle: () => void
}

const ThemeContext = createContext<ThemeState | null>(null)

/** Preferencia guardada; si no hay, la del sistema operativo. */
function initialTheme(): Theme {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (saved === 'light' || saved === 'dark') {
    return saved
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light'
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>(initialTheme)

  // El atributo en <html> es lo que activa el bloque de tokens del tema.
  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem(STORAGE_KEY, theme)
  }, [theme])

  const toggle = useCallback(() => {
    setTheme((current) => (current === 'dark' ? 'light' : 'dark'))
  }, [])

  const value = useMemo(() => ({ theme, toggle }), [theme, toggle])

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

export function useTheme(): ThemeState {
  const context = useContext(ThemeContext)
  if (!context) {
    throw new Error('useTheme debe usarse dentro de <ThemeProvider>')
  }
  return context
}
