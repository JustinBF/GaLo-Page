import { useCallback, useEffect, useState } from 'react'
import { api } from './client'
import type { DuesWeek } from '../types'

/**
 * Cuotas de una semana. Sin `week` devuelve la semana ISO en curso; con una
 * fecha, la semana a la que pertenece.
 */
export function useDues(week: string | null) {
  const [data, setData] = useState<DuesWeek | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const query = week ? `?week=${week}` : ''
      setData(await api.get<DuesWeek>(`/dues${query}`))
    } catch {
      setError('No se pudieron cargar las cuotas.')
    } finally {
      setLoading(false)
    }
  }, [week])

  useEffect(() => {
    void reload()
  }, [reload])

  return { data, loading, error, reload }
}

export const duesApi = {
  /** Marca el check: cobra y manda el importe al Banco del Team. */
  charge: (memberId: number, week: string, amount: number) =>
    api.post('/admin/dues', { member_id: memberId, week, amount }),

  /** Desmarca el check: retira el cobro y su movimiento del banco. */
  revert: (memberId: number, week: string) =>
    api.delete('/admin/dues', { member_id: memberId, week }),

  setAmount: (amount: number) =>
    api.put<{ default_amount: number }>('/admin/dues/amount', { amount }),
}
