import { useCallback, useEffect, useState } from 'react'
import { api, uploadFile } from './client'
import type { Collection, Member, MemberPayload, MemberResult, Rank, Single } from '../types'

export type MemberScope = 'players' | 'organizers' | 'all'

export function useMembers(scope: MemberScope, includeInactive = false) {
  const [members, setMembers] = useState<Member[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const params = new URLSearchParams({ scope })
      if (includeInactive) {
        params.set('include_inactive', '1')
      }
      const result = await api.get<Collection<Member>>(`/members?${params}`)
      setMembers(result.data)
    } catch {
      setError('No se pudo cargar la tabla.')
    } finally {
      setLoading(false)
    }
  }, [scope, includeInactive])

  useEffect(() => {
    void reload()
  }, [reload])

  return { members, loading, error, reload }
}

export function useRanks() {
  const [ranks, setRanks] = useState<Rank[]>([])

  useEffect(() => {
    api.get<Rank[]>('/ranks').then(setRanks).catch(() => setRanks([]))
  }, [])

  return ranks
}

export const membersApi = {
  /** Palmarés: eventos en los que estuvo en el podio, con su puesto. */
  results: (id: number) => api.get<Collection<MemberResult>>(`/members/${id}/results`),

  create: (payload: Partial<MemberPayload>) =>
    api.post<Single<Member>>('/admin/members', payload),

  update: (id: number, payload: Partial<MemberPayload>) =>
    api.put<Single<Member>>(`/admin/members/${id}`, payload),

  deactivate: (id: number) => api.delete(`/admin/members/${id}`),

  uploadAvatar: (id: number, file: File) => {
    const body = new FormData()
    body.append('avatar', file)
    return uploadFile<Single<Member>>(`/admin/members/${id}/avatar`, body)
  },

  removeAvatar: (id: number) =>
    api.delete<Single<Member>>(`/admin/members/${id}/avatar`),
}
