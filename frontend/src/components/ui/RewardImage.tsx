import { useState } from 'react'
import { rewardImageUrl } from '../../api/client'
import type { RewardCategory } from '../../types'

const CATEGORY_ICON: Record<RewardCategory, string> = {
  pokemon: '🔴',
  objeto: '🎒',
  cosmetico: '👕',
  ascenso_rango: '⭐',
  especial: '✨',
}

interface Props {
  rewardId: number
  category: RewardCategory
  hasImage: boolean
  version: number | null
  name: string
  size?: number
}

/** Imagen del premio, con el icono de su categoría como respaldo. */
export function RewardImage({
  rewardId,
  category,
  hasImage,
  version,
  name,
  size = 64,
}: Props) {
  const [failed, setFailed] = useState(false)
  const showImage = hasImage && !failed

  return (
    <span
      className="reward-img"
      style={{ width: size, height: size, fontSize: size * 0.42 }}
    >
      {showImage ? (
        <img
          src={rewardImageUrl(rewardId, version)}
          alt={name}
          onError={() => setFailed(true)}
        />
      ) : (
        CATEGORY_ICON[category]
      )}
    </span>
  )
}
