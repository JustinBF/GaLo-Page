import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { api, tokenStore } from '../api/client'
import type { AuthUser } from '../types'

interface AuthState {
  user: AuthUser | null
  loading: boolean
  login: (username: string, password: string, actorLabel?: string) => Promise<void>
  logout: () => Promise<void>
  isAdmin: boolean
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)

  // Al recargar la pagina el token sigue en localStorage: se revalida.
  useEffect(() => {
    if (!tokenStore.get()) {
      setLoading(false)
      return
    }

    api
      .get<{ user: AuthUser }>('/me')
      .then(({ user }) => setUser(user))
      .catch(() => {
        tokenStore.clear()
        setUser(null)
      })
      .finally(() => setLoading(false))
  }, [])

  const login = useCallback(
    async (username: string, password: string, actorLabel?: string) => {
      const data = await api.post<{ token: string; user: AuthUser }>('/login', {
        username,
        password,
        actor_label: actorLabel || null,
      })
      tokenStore.set(data.token)
      setUser(data.user)
    },
    [],
  )

  const logout = useCallback(async () => {
    await api.post('/logout').catch(() => undefined)
    tokenStore.clear()
    setUser(null)
  }, [])

  const value = useMemo<AuthState>(
    () => ({ user, loading, login, logout, isAdmin: user?.is_admin ?? false }),
    [user, loading, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth debe usarse dentro de <AuthProvider>')
  }
  return context
}
