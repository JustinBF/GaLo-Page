export type Role = 'admin' | 'player'

export interface AuthUser {
  id: number
  username: string
  role: Role
  is_admin: boolean
  actor_label: string | null
}

export interface TeamSettings {
  team_name: string
  bank_balance: number
}

/** Rango del team: Minino, Meowth, Persian, Gatos Sombra, Gran Felino, Gato Alpha. */
export interface Rank {
  id: number
  name: string
  slug: string
  level: number
  scope: 'player' | 'organizer' | 'both'
  color_hex: string
  has_icon?: boolean
  icon_version?: number | null
}

export type Currency = 'CE' | 'CO'

export interface Member {
  id: number
  nick: string
  is_player: boolean
  is_organizer: boolean
  is_active: boolean
  notes: string | null
  rank: Rank | null
  organizer_rank: Rank | null
  ce_balance: number
  co_balance: number
  /** Solo en los listados con agregados. */
  top1?: number
  top2?: number
  top3?: number
  events_organized?: number
  prizes_total?: number
  has_avatar: boolean
  avatar_url: string | null
  avatar_version: number | null
}

export interface MemberPayload {
  nick: string
  rank_id: number | null
  is_player: boolean
  is_organizer: boolean
  is_active: boolean
  notes: string | null
}

/** Las colecciones de Laravel vienen envueltas en `data`. */
export interface Collection<T> {
  data: T[]
}

export interface Single<T> {
  data: T
}

export type EventType = 'torneo' | 'caza' | 'sorteo' | 'otro'
export type Difficulty = 'baja' | 'media' | 'alta' | 'extrema'

/** Referencia ligera a un miembro, para podios y organizadores. */
export interface MemberRef {
  id: number
  nick: string
  has_avatar: boolean
  avatar_version: number | null
}

export interface EventResult {
  position: number
  ce_awarded: number
  member: MemberRef
}

/** Un podio del miembro visto desde su perfil: el evento y el puesto. */
export interface MemberResult {
  position: number
  ce_awarded: number
  event: {
    id: number
    name: string
    type: EventType
    held_at: string | null
    difficulty: Difficulty
  }
}

export interface Event {
  id: number
  name: string
  type: EventType
  held_at: string | null
  difficulty: Difficulty
  prize_value: number
  co_awarded: number
  co_manual_override: boolean
  notes: string | null
  organizer: MemberRef | null
  results: EventResult[]
  total_ce_awarded: number
}

export interface EventPayload {
  name: string
  type: EventType
  held_at: string
  difficulty: Difficulty
  prize_value: number
  organizer_id: number | null
  /** null = aplicar la regla automática de CO. */
  co_awarded: number | null
  notes: string | null
  results: { member_id: number; position: number; ce_awarded: number }[]
}

export type CreditReason =
  | 'event_win'
  | 'event_organized'
  | 'redemption'
  | 'manual_adjust'
  | 'correction'

export interface CreditTransaction {
  id: number
  currency: Currency
  amount: number
  reason: CreditReason
  note: string | null
  created_at: string | null
  member?: { id: number; nick: string }
  event?: { id: number; name: string } | null
  by?: string | null
}

export type RewardCategory =
  | 'pokemon'
  | 'objeto'
  | 'cosmetico'
  | 'ascenso_rango'
  | 'especial'

export interface Reward {
  id: number
  name: string
  description: string | null
  currency: Currency
  cost: number
  category: RewardCategory
  /** null = stock ilimitado */
  stock: number | null
  in_stock: boolean
  is_active: boolean
  is_featured: boolean
  sort_order: number
  grants_rank: { id: number; name: string; color_hex: string } | null
  has_image: boolean
  image_version: number | null
}

export interface RewardPayload {
  name: string
  description: string | null
  currency: Currency
  cost: number
  category: RewardCategory
  grants_rank_id: number | null
  stock: number | null
  is_active: boolean
  is_featured: boolean
}

export type RedemptionStatus = 'pendiente' | 'entregado' | 'cancelado'

export interface Redemption {
  id: number
  /** Congelados al momento del canje: el premio puede cambiar despues. */
  reward_name: string
  currency: Currency
  cost_paid: number
  status: RedemptionStatus
  note: string | null
  created_at: string | null
  member?: MemberRef
  reward?: {
    id: number
    category: RewardCategory
    has_image: boolean
    image_version: number | null
  } | null
  by?: string | null
}

export type PodiumScope = 'players' | 'organizers'
export type PodiumPeriod = 'all' | 'month' | 'year'

export interface PodiumEntry {
  position: number
  id: number
  nick: string
  score: number
  has_avatar: boolean
  avatar_version: number | null
  rank: {
    id: number
    name: string
    color_hex: string
    has_icon: boolean
  } | null
}

export interface PodiumResponse {
  scope: PodiumScope
  period: PodiumPeriod
  currency: Currency
  podium: PodiumEntry[]
}
