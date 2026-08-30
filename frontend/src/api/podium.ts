import { useCallback, useEffect, useState } from 'react'
import { api, uploadFile } from './client'
import type {
  PodiumEntry,
  PodiumPeriod,
  PodiumResponse,
  PodiumScope,
  Rank,
  TeamSettings,
} from '../types'

export function usePodium(scope: PodiumScope, period: PodiumPeriod) {
  const [entries, setEntries] = useState<PodiumEntry[]>([])
  const [currency, setCurrency] = useState<'CE' | 'CO'>('CE')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    setLoading(true)

    api
      .get<PodiumResponse>(`/podium?scope=${scope}&period=${period}`)
      .then((result) => {
        if (!cancelled) {
          setEntries(result.podium)
          setCurrency(result.currency)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setEntries([])
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [scope, period])

  return { entries, currency, loading }
}

export function useSettings() {
  const [settings, setSettings] = useState<TeamSettings | null>(null)

  const reload = useCallback(async () => {
    try {
      setSettings(await api.get<TeamSettings>('/settings'))
    } catch {
      setSettings(null)
    }
  }, [])

  useEffect(() => {
    void reload()
  }, [reload])

  return { settings, reload }
}

export const settingsApi = {
  update: (payload: { bank_balance: number; team_name?: string }) =>
    api.put<TeamSettings>('/admin/settings', payload),
}

export const ranksApi = {
  all: () => api.get<Rank[]>('/ranks'),

  update: (id: number, payload: { name?: string; color_hex?: string }) =>
    api.put<Rank>(`/admin/ranks/${id}`, payload),

  uploadIcon: (id: number, file: File) => {
    const body = new FormData()
    body.append('icon', file)
    return uploadFile<{ id: number; has_icon: boolean; icon_version: number }>(
      `/admin/ranks/${id}/icon`,
      body,
    )
  },

  removeIcon: (id: number) =>
    api.delete<{ id: number; has_icon: boolean }>(`/admin/ranks/${id}/icon`),
}
