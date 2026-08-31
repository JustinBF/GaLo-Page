import { useState, type ChangeEvent } from 'react'
import { ApiError, eventBadgeUrl } from '../api/client'
import { eventsApi } from '../api/events'
import type { Event, EventBadge } from '../types'

interface Props {
  event: Event
}

const SLOTS: { position: number | null; label: string }[] = [
  { position: null, label: 'General' },
  { position: 1, label: 'Oro' },
  { position: 2, label: 'Plata' },
  { position: 3, label: 'Bronce' },
]

/**
 * Insignias propias del evento. La general la lucen todos los del podio; las
 * de puesto mandan sobre ella para quien quedó en ese puesto.
 *
 * Solo al editar: la subida necesita que el evento ya exista.
 */
export function EventBadgesField({ event }: Props) {
  // Estado propio: el evento que recibe el modal no se refresca solo, asi
  // que sin esto la insignia recien subida no se veria hasta reabrirlo.
  const [badges, setBadges] = useState<EventBadge[]>(event.badges)
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const find = (position: number | null): EventBadge | undefined =>
    badges.find((badge) => badge.position === position)

  const slotKey = (position: number | null) => String(position ?? 'general')

  async function run(
    position: number | null,
    action: () => Promise<unknown>,
    next: (current: EventBadge[]) => EventBadge[],
  ) {
    setBusy(slotKey(position))
    setError(null)
    try {
      await action()
      setBadges(next)
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'No se pudo guardar la insignia.',
      )
    } finally {
      setBusy(null)
    }
  }

  function handleFile(position: number | null, e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) {
      return
    }
    void run(
      position,
      () => eventsApi.uploadBadge(event.id, file, position),
      (current) => [
        ...current.filter((badge) => badge.position !== position),
        // La version nueva rompe la cache del navegador.
        { position, version: Math.floor(Date.now() / 1000) },
      ],
    )
    // Permite volver a elegir el mismo archivo tras un fallo.
    e.target.value = ''
  }

  return (
    <div className="badge-field">
      <span className="field__label">Insignias del evento</span>
      <p className="field__hint">
        PNG, JPG o WEBP. Máximo 200 KB y 512x512 píxeles.
      </p>

      {error && <p className="form-error">{error}</p>}

      <div className="badge-slots">
        {SLOTS.map(({ position, label }) => {
          const badge = find(position)
          const key = slotKey(position)

          return (
            <div className="badge-slot" key={key}>
              <span className="badge-slot__label">{label}</span>

              {badge ? (
                <img
                  className="badge-slot__img"
                  src={eventBadgeUrl(event.id, position, badge.version)}
                  alt={`Insignia ${label}`}
                />
              ) : (
                <span className="badge-slot__empty">—</span>
              )}

              <label className="badge-slot__upload">
                {busy === key ? '...' : badge ? 'Cambiar' : 'Subir'}
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  hidden
                  disabled={busy !== null}
                  onChange={(e) => handleFile(position, e)}
                />
              </label>

              {badge && (
                <button
                  className="link-btn"
                  type="button"
                  disabled={busy !== null}
                  onClick={() =>
                    void run(
                      position,
                      () => eventsApi.removeBadge(event.id, position),
                      (current) =>
                        current.filter((badge) => badge.position !== position),
                    )
                  }
                >
                  Quitar
                </button>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
