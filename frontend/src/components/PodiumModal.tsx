import { useState } from 'react'
import { avatarUrl, rankIconUrl } from '../api/client'
import { usePodium } from '../api/podium'
import type { PodiumEntry, PodiumPeriod, PodiumScope } from '../types'
import { Medal } from './ui/Medal'
import { Modal } from './ui/Modal'

interface Props {
  onClose: () => void
}

const PERIODS: { value: PodiumPeriod; label: string }[] = [
  { value: 'all', label: 'Histórico' },
  { value: 'year', label: 'Este año' },
  { value: 'month', label: 'Este mes' },
]

// Orden visual del podio: el segundo a la izquierda, el campeon en el centro.
const LAYOUT = [2, 1, 3]

function PodiumStep({
  entry,
  currency,
}: {
  entry: PodiumEntry | undefined
  currency: string
}) {
  if (!entry) {
    return <div className="podium-step podium-step--empty" />
  }

  return (
    <div className={`podium-step podium-step--${entry.position}`}>
      <Medal position={entry.position} size={entry.position === 1 ? 40 : 32} />

      <span className="podium-step__avatar">
        {entry.has_avatar ? (
          <img src={avatarUrl(entry.id, entry.avatar_version)} alt={entry.nick} />
        ) : (
          entry.nick.charAt(0).toUpperCase()
        )}
      </span>

      <span className="podium-step__nick">{entry.nick}</span>

      {entry.rank && (
        <span className="podium-step__rank" style={{ color: entry.rank.color_hex }}>
          {entry.rank.has_icon && (
            <img
              className="rank-icon"
              src={rankIconUrl(entry.rank.id, null)}
              alt=""
            />
          )}
          {entry.rank.name}
        </span>
      )}

      <span className="podium-step__score">
        {entry.score} {currency}
      </span>

      <div className="podium-step__base">{entry.position}</div>
    </div>
  )
}

export function PodiumModal({ onClose }: Props) {
  const [scope, setScope] = useState<PodiumScope>('players')
  const [period, setPeriod] = useState<PodiumPeriod>('all')
  const { entries, currency, loading } = usePodium(scope, period)

  const byPosition = new Map(entries.map((entry) => [entry.position, entry]))

  return (
    <Modal title="Podio del team" onClose={onClose} width={620}>
      <div className="podium-controls">
        <div className="seg">
          <button
            className={`seg__btn ${scope === 'players' ? 'seg__btn--active' : ''}`}
            type="button"
            onClick={() => setScope('players')}
          >
            Jugadores
          </button>
          <button
            className={`seg__btn ${scope === 'organizers' ? 'seg__btn--active' : ''}`}
            type="button"
            onClick={() => setScope('organizers')}
          >
            Organizadores
          </button>
        </div>

        <div className="seg">
          {PERIODS.map((option) => (
            <button
              key={option.value}
              className={`seg__btn ${period === option.value ? 'seg__btn--active' : ''}`}
              type="button"
              onClick={() => setPeriod(option.value)}
            >
              {option.label}
            </button>
          ))}
        </div>
      </div>

      {loading && <p className="panel__hint">Cargando podio...</p>}

      {!loading && entries.length === 0 && (
        <p className="panel__hint">
          Todavía no hay {scope === 'players' ? 'jugadores' : 'organizadores'} con
          créditos en este periodo.
        </p>
      )}

      {!loading && entries.length > 0 && (
        <>
          <div className="podium">
            {LAYOUT.map((position) => (
              <PodiumStep
                key={position}
                entry={byPosition.get(position)}
                currency={currency}
              />
            ))}
          </div>

          {period !== 'all' && (
            <p className="field__hint" style={{ textAlign: 'center' }}>
              Suma de créditos ganados en el periodo. Lo gastado en la tienda no resta.
            </p>
          )}
        </>
      )}
    </Modal>
  )
}
