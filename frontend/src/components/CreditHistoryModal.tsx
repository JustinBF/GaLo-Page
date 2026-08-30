import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'
import { creditsApi } from '../api/events'
import { useAuth } from '../auth/AuthContext'
import { formatDateTime } from '../lib/format'
import type { CreditTransaction, Currency, Member } from '../types'
import { Avatar } from './ui/Avatar'
import { Modal } from './ui/Modal'

interface Props {
  member: Member
  currency: Currency
  onClose: () => void
  onChanged: () => void
}

const REASON_LABEL: Record<string, string> = {
  event_win: 'Podio en evento',
  event_organized: 'Evento organizado',
  redemption: 'Canje en la tienda',
  manual_adjust: 'Ajuste manual',
  correction: 'Corrección',
}

export function CreditHistoryModal({ member, currency, onClose, onChanged }: Props) {
  const { isAdmin } = useAuth()
  const [transactions, setTransactions] = useState<CreditTransaction[]>([])
  const [loading, setLoading] = useState(true)
  const [balance, setBalance] = useState(
    currency === 'CE' ? member.ce_balance : member.co_balance,
  )

  const [amount, setAmount] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const result = await creditsApi.history(member.id)
      setTransactions(result.data.filter((t) => t.currency === currency))
    } finally {
      setLoading(false)
    }
  }, [member.id, currency])

  useEffect(() => {
    void load()
  }, [load])

  async function handleAdjust(event: FormEvent) {
    event.preventDefault()
    setError(null)

    const value = Number(amount)
    if (!Number.isInteger(value) || value === 0) {
      setError('Escribe una cantidad distinta de cero. Usa un negativo para restar.')
      return
    }

    setSaving(true)
    try {
      const result = await creditsApi.adjust(member.id, {
        currency,
        amount: value,
        note: note.trim(),
      })
      setBalance(currency === 'CE' ? result.ce_balance : result.co_balance)
      setAmount('')
      setNote('')
      await load()
      onChanged()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo aplicar el ajuste.',
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal title={`Historial de ${member.nick}`} onClose={onClose} width={560}>
      <div className="history-head">
        <Avatar member={member} size={48} />
        <div>
          <div className="history-head__nick">{member.nick}</div>
          <div
            className={`history-head__balance balance--${currency.toLowerCase()}`}
          >
            {balance} {currency}
          </div>
        </div>
      </div>

      {isAdmin && (
        <form className="adjust-form" onSubmit={handleAdjust}>
          <div className="field__label">Ajuste manual de {currency}</div>
          {error && <div className="form-error">{error}</div>}
          <div className="adjust-form__row">
            <input
              className="field__input adjust-form__amount"
              type="number"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="+50 o -20"
              aria-label="Cantidad"
            />
            <input
              className="field__input"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Motivo (obligatorio)"
              maxLength={200}
              aria-label="Motivo"
            />
            <button className="btn" type="submit" disabled={saving}>
              {saving ? '...' : 'Aplicar'}
            </button>
          </div>
          <p className="field__hint">
            Usa un número negativo para restar. Queda registrado con tu nombre.
          </p>
        </form>
      )}

      <div className="field__label" style={{ marginTop: 20 }}>
        Movimientos
      </div>

      {loading && <p className="panel__hint">Cargando...</p>}

      {!loading && transactions.length === 0 && (
        <p className="panel__hint">
          Sin movimientos de {currency} todavía.
        </p>
      )}

      <ul className="ledger">
        {transactions.map((transaction) => (
          <li className="ledger__item" key={transaction.id}>
            <div className="ledger__main">
              <span className="ledger__reason">
                {REASON_LABEL[transaction.reason] ?? transaction.reason}
              </span>
              {transaction.note && (
                <span className="ledger__note">{transaction.note}</span>
              )}
              <span className="ledger__date">
                {formatDateTime(transaction.created_at)}
              </span>
            </div>
            <span
              className={`ledger__amount ${
                transaction.amount >= 0 ? 'is-positive' : 'is-negative'
              }`}
            >
              {transaction.amount >= 0 ? '+' : ''}
              {transaction.amount}
            </span>
          </li>
        ))}
      </ul>
    </Modal>
  )
}
