import { useCallback, useEffect, useState } from 'react'
import { api, uploadFile } from './client'
import type {
  Collection,
  Currency,
  Redemption,
  Reward,
  RewardPayload,
  Single,
} from '../types'

export function useRewards(currency: Currency, includeInactive = false) {
  const [rewards, setRewards] = useState<Reward[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const reload = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const params = new URLSearchParams({ currency })
      if (includeInactive) {
        params.set('include_inactive', '1')
      }
      const result = await api.get<Collection<Reward>>(`/rewards?${params}`)
      setRewards(result.data)
    } catch {
      setError('No se pudo cargar la tienda.')
    } finally {
      setLoading(false)
    }
  }, [currency, includeInactive])

  useEffect(() => {
    void reload()
  }, [reload])

  return { rewards, loading, error, reload }
}

export function useRedemptions() {
  const [redemptions, setRedemptions] = useState<Redemption[]>([])
  const [loading, setLoading] = useState(true)

  const reload = useCallback(async () => {
    setLoading(true)
    try {
      const result = await api.get<Collection<Redemption>>('/redemptions')
      setRedemptions(result.data)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void reload()
  }, [reload])

  return { redemptions, loading, reload }
}

export const rewardsApi = {
  create: (payload: RewardPayload) => api.post<Single<Reward>>('/admin/rewards', payload),

  update: (id: number, payload: RewardPayload) =>
    api.put<Single<Reward>>(`/admin/rewards/${id}`, payload),

  retire: (id: number) => api.delete(`/admin/rewards/${id}`),

  uploadImage: (id: number, file: File) => {
    const body = new FormData()
    body.append('image', file)
    return uploadFile<Single<Reward>>(`/admin/rewards/${id}/image`, body)
  },

  removeImage: (id: number) => api.delete<Single<Reward>>(`/admin/rewards/${id}/image`),
}

export const redemptionsApi = {
  forMember: (memberId: number) =>
    api.get<Collection<Redemption>>(`/members/${memberId}/redemptions`),

  create: (payload: { member_id: number; reward_id: number; note: string | null }) =>
    api.post<{
      redemption: Redemption
      ce_balance: number
      co_balance: number
    }>('/admin/redemptions', payload),

  setStatus: (id: number, status: 'pendiente' | 'entregado') =>
    api.put<Single<Redemption>>(`/admin/redemptions/${id}`, { status }),

  cancel: (id: number) =>
    api.post<{ redemption: Redemption; ce_balance: number; co_balance: number }>(
      `/admin/redemptions/${id}/cancel`,
    ),
}
