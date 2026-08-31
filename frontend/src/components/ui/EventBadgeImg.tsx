import { useState } from 'react'
import { eventBadgeUrl } from '../../api/client'
import type { EventBadge } from '../../types'

interface Props {
  eventId: number
  badge: EventBadge
  eventName: string
  size?: number
}

/**
 * Insignia personalizada de un evento. Si la imagen no carga no pinta nada:
 * un icono roto en mitad del palmarés queda peor que su ausencia.
 */
export function EventBadgeImg({ eventId, badge, eventName, size = 40 }: Props) {
  const [failed, setFailed] = useState(false)

  if (failed) {
    return null
  }

  return (
    <img
      className="event-badge"
      src={eventBadgeUrl(eventId, badge.position, badge.version)}
      alt={`Insignia de ${eventName}`}
      title={eventName}
      style={{ width: size, height: size }}
      onError={() => setFailed(true)}
    />
  )
}
