import { useCallback, useEffect, useState } from 'react'
import { api } from './client'

export interface BankMovement {
  id: number
  contributor_name: string
  amount: number
  description: string
  created_at: string | null
  by: string | null
}

export interface BankLedger {
  balance: number
  movements: BankMovement[]
}

export function useBank() {
  const [ledger, setLedger] = useState<BankLedger>({ balance: 0, movements: [] })
  const [loading, setLoading] = useState(true)

  const reload = useCallback(async () => {
    setLoading(true)
    try {
      setLedger(await api.get<BankLedger>('/bank'))
    } catch {
      setLedger({ balance: 0, movements: [] })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void reload()
  }, [reload])

  return { ledger, loading, reload }
}

export const bankApi = {
  /** Registra quién aportó, cuánto y para qué. Negativo = salida de dinero. */
  add: (payload: { contributor_name: string; amount: number; description: string }) =>
    api.post<BankLedger>('/admin/bank', payload),

  remove: (id: number) => api.delete<BankLedger>(`/admin/bank/${id}`),
}
