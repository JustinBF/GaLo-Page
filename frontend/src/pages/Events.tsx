import { useState } from 'react'
import { eventsApi, useEvents } from '../api/events'
import { EventFormModal } from '../components/EventFormModal'
import { Avatar } from '../components/ui/Avatar'
import { Medal } from '../components/ui/Medal'
import { useAuth } from '../auth/AuthContext'
import { RoleGate } from '../auth/RoleGate'
import { formatDate, formatPokeyen } from '../lib/format'
import type { Event } from '../types'

const DIFFICULTY_COLOR: Record<string, string> = {
  baja: 'var(--text-dim)',
  media: 'var(--ce)',
  alta: 'var(--gold)',
  extrema: 'var(--danger)',
}

export function Events() {
  const { isAdmin } = useAuth()
  const { events, loading, error, reload } = useEvents()
  const [editing, setEditing] = useState<Event | null>(null)
  const [creating, setCreating] = useState(false)

  async function handleDelete(event: Event) {
    if (
      !confirm(
        `Eliminar "${event.name}"? Se retiraran los créditos que repartio.`,
      )
    ) {
      return
    }
    await eventsApi.remove(event.id)
    void reload()
  }

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Eventos</h1>
          <p className="page-subtitle">
            Historial de eventos del team, podios y créditos repartidos.
          </p>
        </div>
        <RoleGate>
          <button className="btn" type="button" onClick={() => setCreating(true)}>
            + Nuevo evento
          </button>
        </RoleGate>
      </div>

      {error && <div className="form-error">{error}</div>}

      {loading && <div className="panel panel--empty">Cargando eventos...</div>}

      {!loading && events.length === 0 && (
        <div className="panel panel--empty">
          Todavía no hay eventos registrados.
        </div>
      )}

      <div className="event-list">
        {events.map((event) => {
          // Defensivo: si la API va por detrás del frontend, esta lista no
          // viene y la tarjeta entera tumbaba la página.
          const organizers = event.organizers ?? []

          return (
          <article className="event-card" key={event.id}>
            <header className="event-card__head">
              <div>
                <h2 className="event-card__title">{event.name}</h2>
                <div className="event-card__meta">
                  <span>{formatDate(event.held_at)}</span>
                  <span className="dot" />
                  <span style={{ textTransform: 'capitalize' }}>{event.type}</span>
                  <span className="dot" />
                  <span style={{ color: DIFFICULTY_COLOR[event.difficulty] }}>
                    Dificultad {event.difficulty}
                  </span>
                </div>
              </div>

              {isAdmin && (
                <div className="event-card__actions">
                  <button
                    className="icon-btn"
                    type="button"
                    title="Editar"
                    onClick={() => setEditing(event)}
                  >
                    ✏️
                  </button>
                  <button
                    className="icon-btn"
                    type="button"
                    title="Eliminar"
                    onClick={() => handleDelete(event)}
                  >
                    🗑️
                  </button>
                </div>
              )}
            </header>

            <div className="event-card__stats">
              <div className="stat">
                <span className="stat__label">Premio</span>
                <span className="stat__value stat__value--gold">
                  {formatPokeyen(event.prize_value)} ¥
                </span>
              </div>
              <div className="stat">
                <span className="stat__label">CE repartido</span>
                <span className="stat__value stat__value--ce">
                  {event.total_ce_awarded}
                </span>
              </div>
              <div className="stat">
                <span className="stat__label">
                  CO {event.co_manual_override && <em>(manual)</em>}
                </span>
                <span className="stat__value stat__value--co">{event.co_awarded}</span>
              </div>
              <div className="stat">
                <span className="stat__label">
                  {organizers.length === 1 ? 'Organizador' : 'Organizadores'}
                </span>
                <span className="stat__value">
                  {organizers.length > 0 ? (
                    <span className="organizer-list">
                      {organizers.map((organizer) => (
                        <span className="cell-member" key={organizer.id}>
                          <Avatar member={organizer} size={24} />
                          {organizer.nick}
                          {organizers.length > 1 && (
                            <em className="organizer-share">
                              {organizer.co_share} CO ·{' '}
                              {formatPokeyen(organizer.prize_share)} ¥
                            </em>
                          )}
                        </span>
                      ))}
                    </span>
                  ) : (
                    <span style={{ color: 'var(--text-dim)' }}>—</span>
                  )}
                </span>
              </div>
            </div>

            {(event.results ?? []).length > 0 && (
              <div className="event-card__podium">
                {(event.results ?? []).map((result) => (
                  <div className="podium-chip" key={result.position}>
                    <Medal position={result.position} size={22} />
                    <Avatar member={result.member} size={26} />
                    <span className="podium-chip__nick">{result.member.nick}</span>
                    <span className="podium-chip__ce">+{result.ce_awarded} CE</span>
                  </div>
                ))}
              </div>
            )}

            {event.notes && <p className="event-card__notes">{event.notes}</p>}
          </article>
          )
        })}
      </div>

      {(creating || editing) && (
        <EventFormModal
          event={editing}
          onClose={() => {
            setCreating(false)
            setEditing(null)
          }}
          onSaved={reload}
        />
      )}
    </>
  )
}
