import { rankIconUrl } from '../../api/client'
import type { Rank } from '../../types'

export function RankBadge({ rank }: { rank: Rank | null }) {
  if (!rank) {
    return <span className="rank-badge rank-badge--none">Sin rango</span>
  }

  return (
    <span
      className="rank-badge"
      style={{
        color: rank.color_hex,
        borderColor: rank.color_hex,
        background: `${rank.color_hex}1f`,
      }}
    >
      {rank.has_icon && (
        <img
          className="rank-icon"
          src={rankIconUrl(rank.id, rank.icon_version ?? null)}
          alt=""
        />
      )}
      {rank.name}
    </span>
  )
}
