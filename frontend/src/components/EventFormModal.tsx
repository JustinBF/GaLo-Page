import { useEffect, useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'
import { eventsApi } from '../api/events'
import { useMembers } from '../api/members'
import { formatPokeyen, parsePokeyen } from '../lib/format'
import type { Difficulty, Event, EventPayload, EventType } from '../types'
import { Medal } from './ui/Medal'
import { Modal } from './ui/Modal'

interface Props {
  event: Event | null
  onClose: () => void
  onSaved: () => void
}

const TYPES: { value: EventType; label: string }[] = [
  { value: 'torneo', label: 'Torneo' },
  { value: 'caza', label: 'Caza' },
  { value: 'sorteo', label: 'Sorteo' },
  { value: 'otro', label: 'Otro' },
]

const DIFFICULTIES: { value: Difficulty; label: string }[] = [
  { value: 'baja', label: 'Baja' },
  { value: 'media', label: 'Media' },
  { value: 'alta', label: 'Alta' },
  { value: 'extrema', label: 'Extrema' },
]

const PODIUM = [
  { position: 1, label: 'Top 1' },
  { position: 2, label: 'Top 2' },
  { position: 3, label: 'Top 3' },
]

interface PodiumRow {
  memberId: string
  ce: string
}

function initialPodium(event: Event | null): Record<number, PodiumRow> {
  const rows: Record<number, PodiumRow> = {
    1: { memberId: '', ce: '' },
    2: { memberId: '', ce: '' },
    3: { memberId: '', ce: '' },
  }

  event?.results.forEach((result) => {
    rows[result.position] = {
      memberId: String(result.member.id),
      ce: String(result.ce_awarded),
    }
  })

  return rows
}

export function EventFormModal({ event, onClose, onSaved }: Props) {
  const { members: players } = useMembers('players')
  const { members: organizers } = useMembers('organizers')

  const [name, setName] = useState(event?.name ?? '')
  const [type, setType] = useState<EventType>(event?.type ?? 'torneo')
  const [heldAt, setHeldAt] = useState(
    event?.held_at ?? new Date().toISOString().slice(0, 10),
  )
  const [difficulty, setDifficulty] = useState<Difficulty>(event?.difficulty ?? 'media')
  const [prize, setPrize] = useState(
    event ? String(event.prize_value) : '',
  )
  const [organizerId, setOrganizerId] = useState(
    event?.organizer?.id ? String(event.organizer.id) : '',
  )
  const [manualCo, setManualCo] = useState(event?.co_manual_override ?? false)
  const [co, setCo] = useState(event ? String(event.co_awarded) : '0')
  const [suggestedCo, setSuggestedCo] = useState<number | null>(null)
  const [podium, setPodium] = useState(() => initialPodium(event))
  const [notes, setNotes] = useState(event?.notes ?? '')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const prizeValue = parsePokeyen(prize)

  // Consulta al backend cuánto CO toca por este premio. Es el backend quien
  // manda: las reglas se editan y no queremos duplicarlas en el cliente.
  useEffect(() => {
    if (prizeValue === null) {
      setSuggestedCo(null)
      return
    }

    let cancelled = false
    const timer = setTimeout(() => {
      eventsApi
        .suggestCo(prizeValue)
        .then(({ co_awarded }) => {
          if (!cancelled) {
            setSuggestedCo(co_awarded)
          }
        })
        .catch(() => undefined)
    }, 300)

    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [prizeValue])

  function updatePodium(position: number, patch: Partial<PodiumRow>) {
    setPodium((current) => ({
      ...current,
      [position]: { ...current[position], ...patch },
    }))
  }

  async function handleSubmit(submitEvent: FormEvent) {
    submitEvent.preventDefault()
    setError(null)

    if (prizeValue === null) {
      setError('El valor del premio no se entiende. Usa 500k, 1.5m o 750000.')
      return
    }

    const results = PODIUM.flatMap(({ position }) => {
      const row = podium[position]
      if (!row.memberId) {
        return []
      }
      return [
        {
          member_id: Number(row.memberId),
          position,
          ce_awarded: Number(row.ce || 0),
        },
      ]
    })

    const chosen = results.map((r) => r.member_id)
    if (new Set(chosen).size !== chosen.length) {
      setError('Un jugador no puede ocupar dos puestos del podio.')
      return
    }

    const payload: EventPayload = {
      name: name.trim(),
      type,
      held_at: heldAt,
      difficulty,
      prize_value: prizeValue,
      organizer_id: organizerId ? Number(organizerId) : null,
      co_awarded: manualCo ? Number(co || 0) : null,
      notes: notes.trim() || null,
      results,
    }

    setSaving(true)
    try {
      if (event) {
        await eventsApi.update(event.id, payload)
      } else {
        await eventsApi.create(payload)
      }
      onSaved()
      onClose()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo guardar el evento.',
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      title={event ? `Editar ${event.name}` : 'Nuevo evento'}
      onClose={onClose}
      width={620}
    >
      <form onSubmit={handleSubmit}>
        {error && <div className="form-error">{error}</div>}

        <div className="field">
          <label className="field__label" htmlFor="ev-name">
            Nombre del evento
          </label>
          <input
            id="ev-name"
            className="field__input"
            value={name}
            onChange={(e) => setName(e.target.value)}
            maxLength={80}
            required
            autoFocus
          />
        </div>

        <div className="field-row">
          <div className="field">
            <label className="field__label" htmlFor="ev-type">
              Tipo
            </label>
            <select
              id="ev-type"
              className="field__input"
              value={type}
              onChange={(e) => setType(e.target.value as EventType)}
            >
              {TYPES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <div className="field">
            <label className="field__label" htmlFor="ev-date">
              Fecha
            </label>
            <input
              id="ev-date"
              className="field__input"
              type="date"
              value={heldAt}
              onChange={(e) => setHeldAt(e.target.value)}
              required
            />
          </div>

          <div className="field">
            <label className="field__label" htmlFor="ev-diff">
              Dificultad
            </label>
            <select
              id="ev-diff"
              className="field__input"
              value={difficulty}
              onChange={(e) => setDifficulty(e.target.value as Difficulty)}
            >
              {DIFFICULTIES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="field-row">
          <div className="field">
            <label className="field__label" htmlFor="ev-prize">
              Valor del premio
            </label>
            <input
              id="ev-prize"
              className="field__input"
              value={prize}
              onChange={(e) => setPrize(e.target.value)}
              placeholder="500k, 1.5m o 750000"
              required
            />
            <p className="field__hint">
              {prizeValue === null
                ? 'Acepta 500k, 1.5m o el número entero.'
                : `${prizeValue.toLocaleString('es-ES')} ¥ (${formatPokeyen(prizeValue)})`}
            </p>
          </div>

          <div className="field">
            <label className="field__label" htmlFor="ev-org">
              Organizador
            </label>
            <select
              id="ev-org"
              className="field__input"
              value={organizerId}
              onChange={(e) => setOrganizerId(e.target.value)}
            >
              <option value="">Sin organizador</option>
              {organizers.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.nick}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="co-box">
          <div className="co-box__head">
            <span className="co-box__label">Créditos de Organizador</span>
            <span className="co-box__value">
              {manualCo ? Number(co || 0) : (suggestedCo ?? 0)} CO
            </span>
          </div>
          <label className="check">
            <input
              type="checkbox"
              checked={manualCo}
              onChange={(e) => {
                setManualCo(e.target.checked)
                if (e.target.checked) {
                  setCo(String(suggestedCo ?? 0))
                }
              }}
            />
            Fijar a mano en lugar de usar la regla automática
          </label>
          {manualCo ? (
            <input
              className="field__input"
              type="number"
              min={0}
              value={co}
              onChange={(e) => setCo(e.target.value)}
              style={{ marginTop: 10 }}
            />
          ) : (
            <p className="field__hint">
              Calculado por la regla que encaja con el valor del premio.
            </p>
          )}
        </div>

        <div className="podium-editor">
          <div className="field__label">Podio y reparto de CE</div>
          <p className="field__hint" style={{ marginBottom: 10 }}>
            El CE lo decides tu según la dificultad. Deja el puesto vacio si no aplica.
          </p>

          {PODIUM.map(({ position, label }) => (
            <div className="podium-row" key={position}>
              <span className="podium-row__label">
                <Medal position={position} size={20} />
                {label}
              </span>
              <select
                className="field__input"
                value={podium[position].memberId}
                onChange={(e) => updatePodium(position, { memberId: e.target.value })}
                aria-label={`Jugador en ${label}`}
              >
                <option value="">—</option>
                {players.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.nick}
                  </option>
                ))}
              </select>
              <input
                className="field__input podium-row__ce"
                type="number"
                min={0}
                placeholder="CE"
                value={podium[position].ce}
                onChange={(e) => updatePodium(position, { ce: e.target.value })}
                disabled={!podium[position].memberId}
                aria-label={`CE para ${label}`}
              />
            </div>
          ))}
        </div>

        <div className="field">
          <label className="field__label" htmlFor="ev-notes">
            Notas <span style={{ color: 'var(--text-dim)' }}>(opcional)</span>
          </label>
          <textarea
            id="ev-notes"
            className="field__input"
            rows={2}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            maxLength={500}
          />
        </div>

        {event && (
          <p className="field__hint">
            Al guardar, los créditos de este evento se recalculan desde cero.
            Los ajustes manuales no se tocan.
          </p>
        )}

        <div className="modal__actions">
          <button className="btn btn--ghost" type="button" onClick={onClose}>
            Cancelar
          </button>
          <button className="btn" type="submit" disabled={saving}>
            {saving ? 'Guardando...' : 'Guardar evento'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
