import { useState, type FormEvent } from 'react'
import { bankApi, useBank } from '../api/bank'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { formatDateTime, formatPokeyen, parsePokeyen } from '../lib/format'
import { Modal } from './ui/Modal'

interface Props {
  onClose: () => void
  onChanged: () => void
}

/**
 * Banco del Team: saldo y libro de aportes.
 *
 * El dinero se mueve fuera de la web, aquí solo queda constancia de quién
 * puso cada cantidad y para qué.
 */
export function BankPanel({ onClose, onChanged }: Props) {
  const { isAdmin } = useAuth()
  const { ledger, loading, reload } = useBank()

  const [who, setWho] = useState('')
  const [amount, setAmount] = useState('')
  const [description, setDescription] = useState('')
  const [outgoing, setOutgoing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const parsed = parsePokeyen(amount)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)

    if (parsed === null || parsed === 0) {
      setError('Escribe una cantidad válida. Acepta 500k, 1.5m o el número entero.')
      return
    }

    setSaving(true)
    try {
      await bankApi.add({
        contributor_name: who.trim(),
        amount: outgoing ? -parsed : parsed,
        description: description.trim(),
      })
      setWho('')
      setAmount('')
      setDescription('')
      await reload()
      onChanged()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo registrar el movimiento.',
      )
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('¿Borrar este apunte? El saldo se recalculará.')) {
      return
    }
    await bankApi.remove(id)
    await reload()
    onChanged()
  }

  return (
    <Modal title="Banco del Equipo" onClose={onClose} width={620}>
      <div className="bank-hero">
        <span className="bank-hero__label">Saldo actual</span>
        <span className="bank-hero__amount">{formatPokeyen(ledger.balance)} ¥</span>
        <span className="bank-hero__exact">
          {ledger.balance.toLocaleString('es-ES')} Pokéyenes
        </span>
      </div>

      {isAdmin && (
        <form className="bank-form" onSubmit={handleSubmit}>
          <div className="field__label">Registrar movimiento</div>

          {error && <div className="form-error">{error}</div>}

          <div className="bank-form__row">
            <input
              className="field__input"
              value={who}
              onChange={(e) => setWho(e.target.value)}
              placeholder="¿Quién aportó?"
              maxLength={60}
              aria-label="Nombre de quien aporta"
              required
            />
            <input
              className="field__input"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="2m"
              aria-label="Cantidad"
              required
            />
          </div>

          <input
            className="field__input"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Descripción breve (de dónde salió el dinero)"
            maxLength={200}
            aria-label="Descripción"
            required
          />

          <div className="bank-form__foot">
            <label className="check">
              <input
                type="checkbox"
                checked={outgoing}
                onChange={(e) => setOutgoing(e.target.checked)}
              />
              Es una salida de dinero
            </label>

            <span className="field__hint">
              {parsed === null
                ? 'Acepta 500k, 2m o el número entero.'
                : `${outgoing ? '−' : '+'}${parsed.toLocaleString('es-ES')} ¥`}
            </span>

            <button className="btn" type="submit" disabled={saving}>
              {saving ? 'Guardando...' : 'Registrar'}
            </button>
          </div>

          <p className="field__hint">
            El nombre es texto libre: quien aporta no tiene por qué estar en la web.
          </p>
        </form>
      )}

      <div className="field__label" style={{ marginTop: 22 }}>
        Movimientos
      </div>

      {loading && <p className="panel__hint">Cargando...</p>}

      {!loading && ledger.movements.length === 0 && (
        <p className="panel__hint">Todavía no hay movimientos registrados.</p>
      )}

      <ul className="ledger">
        {ledger.movements.map((movement) => (
          <li className="ledger__item" key={movement.id}>
            <div className="ledger__main">
              <span className="ledger__reason">{movement.contributor_name}</span>
              <span className="ledger__note">{movement.description}</span>
              <span className="ledger__date">{formatDateTime(movement.created_at)}</span>
            </div>

            <div className="ledger__right">
              <span
                className={`ledger__amount ${
                  movement.amount >= 0 ? 'is-positive' : 'is-negative'
                }`}
              >
                {movement.amount >= 0 ? '+' : '−'}
                {formatPokeyen(Math.abs(movement.amount))} ¥
              </span>
              {isAdmin && (
                <button
                  className="icon-btn"
                  type="button"
                  title="Borrar apunte"
                  onClick={() => handleDelete(movement.id)}
                >
                  🗑️
                </button>
              )}
            </div>
          </li>
        ))}
      </ul>
    </Modal>
  )
}
