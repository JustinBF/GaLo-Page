import { useCallback, useEffect, useState } from 'react'
import { api } from './client'
import type { Collection, CreditTransaction, Event, EventPayload, Single } from '../types'

export function useEvents() {
  const [events, setEvents] = useState<Event[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const result = await api.get<Collection<Event>>('/events')
      setEvents(result.data)
    } catch {
      setError('No se pudieron cargar los eventos.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void reload()
  }, [reload])

  return { events, loading, error, reload }
}

export const eventsApi = {
  create: (payload: EventPayload) => api.post<Single<Event>>('/admin/events', payload),

  update: (id: number, payload: EventPayload) =>
    api.put<Single<Event>>(`/admin/events/${id}`, payload),

  remove: (id: number) => api.delete(`/admin/events/${id}`),

  /** Consulta el CO que corresponde a un premio según las reglas vigentes. */
  suggestCo: (prizeValue: number) =>
    api.post<{ co_awarded: number }>('/admin/events/suggest-co', {
      prize_value: prizeValue,
    }),
}

export const creditsApi = {
  history: (memberId: number) =>
    api.get<Collection<CreditTransaction>>(`/members/${memberId}/credits`),

  recent: () => api.get<Collection<CreditTransaction>>('/credits/recent'),

  adjust: (
    memberId: number,
    payload: { currency: 'CE' | 'CO'; amount: number; note: string },
  ) =>
    api.post<{ ce_balance: number; co_balance: number }>(
      `/admin/members/${memberId}/credits`,
      payload,
    ),
}
