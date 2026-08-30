import type { ReactNode } from 'react'
import { useAuth } from './AuthContext'

/**
 * Oculta controles de escritura a los jugadores. Es solo cosmetico:
 * la proteccion real vive en el middleware EnsureAdmin del backend.
 */
export function RoleGate({ children }: { children: ReactNode }) {
  const { isAdmin } = useAuth()
  return isAdmin ? <>{children}</> : null
}
