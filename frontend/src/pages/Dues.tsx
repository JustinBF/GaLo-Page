import { useState } from 'react'
import { duesApi, useDues } from '../api/dues'
import { ApiError } from '../api/client'
import { Avatar } from '../components/ui/Avatar'
import { useAuth } from '../auth/AuthContext'
import { RoleGate } from '../auth/RoleGate'
import { formatDate, formatPokeyen, parsePokeyen } from '../lib/format'

/** Mueve una fecha ISO un número de semanas. */
function shiftWeek(date: string, weeks: number): string {
  const moved = new Date(`${date}T00:00:00`)
  moved.setDate(moved.getDate() + weeks * 7)
  return moved.toISOString().slice(0, 10)
}

export function Dues() {
  const { isAdmin } = useAuth()
  // null = la semana en curso, que decide el backend.
  const [week, setWeek] = useState<string | null>(null)
  const { data, loading, error, reload } = useDues(week)

  const [busy, setBusy] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [amountDraft, setAmountDraft] = useState('')
  const [savingAmount, setSavingAmount] = useState(false)

  async function toggle(memberId: number, paid: boolean, amount: number) {
    if (!data) {
      return
    }

    setBusy(memberId)
    setActionError(null)
    try {
      if (paid) {
        await duesApi.revert(memberId, data.week_start)
      } else {
        await duesApi.charge(memberId, data.week_start, amount)
      }
      void reload()
    } catch (err) {
      setActionError(
        err instanceof ApiError ? err.message : 'No se pudo actualizar la cuota.',
      )
    } finally {
      setBusy(null)
    }
  }

  /** Cobra un importe distinto al de la cuota global para un jugador. */
  async function chargeCustom(memberId: number, nick: string) {
    if (!data) {
      return
    }

    const raw = prompt(
      `Importe a cobrar a ${nick} (en Pokeyenes):`,
      String(data.default_amount),
    )

    if (raw === null) {
      return
    }

    const amount = parsePokeyen(raw)
    if (amount === null) {
      setActionError('Importe no válido.')
      return
    }

    await toggle(memberId, false, amount)
  }

  async function saveAmount() {
    const amount = parsePokeyen(amountDraft)
    if (amount === null) {
      setActionError('Importe no válido.')
      return
    }

    setSavingAmount(true)
    setActionError(null)
    try {
      await duesApi.setAmount(amount)
      setAmountDraft('')
      void reload()
    } catch (err) {
      setActionError(
        err instanceof ApiError ? err.message : 'No se pudo guardar la cuota.',
      )
    } finally {
      setSavingAmount(false)
    }
  }

  const paidCount = data?.rows.filter((row) => row.paid).length ?? 0
  const total = data?.rows.length ?? 0
  const collected = data?.rows.reduce((sum, row) => sum + (row.amount ?? 0), 0) ?? 0

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Cuotas semanales</h1>
          <p className="page-subtitle">
            Solo quien está al día puede participar en los eventos. Al marcar
            el check, el importe entra en el Banco del Team.
          </p>
        </div>
      </div>

      {error && <div className="form-error">{error}</div>}
      {actionError && <div className="form-error">{actionError}</div>}

      {data && (
        <div className="dues-bar">
          <div className="dues-week">
            <button
              className="btn btn--ghost"
              type="button"
              onClick={() => setWeek(shiftWeek(data.week_start, -1))}
            >
              ← Anterior
            </button>

            <div className="dues-week__label">
              <strong>
                {formatDate(data.week_start)} — {formatDate(data.week_end)}
              </strong>
              <span className="panel__hint">
                {paidCount} de {total} al día · {formatPokeyen(collected)} ¥ recaudado
              </span>
            </div>

            <button
              className="btn btn--ghost"
              type="button"
              onClick={() => setWeek(shiftWeek(data.week_start, 1))}
            >
              Siguiente →
            </button>

            <button
              className="btn btn--ghost"
              type="button"
              onClick={() => setWeek(null)}
            >
              Hoy
            </button>
          </div>

          <RoleGate>
            <div className="dues-amount">
              <span className="stat__label">
                Cuota actual: {formatPokeyen(data.default_amount)} ¥
              </span>
              <input
                className="field__input"
                placeholder="Nueva cuota (ej. 50k)"
                value={amountDraft}
                onChange={(e) => setAmountDraft(e.target.value)}
              />
              <button
                className="btn"
                type="button"
                disabled={savingAmount || !amountDraft.trim()}
                onClick={() => void saveAmount()}
              >
                Guardar
              </button>
            </div>
          </RoleGate>
        </div>
      )}

      {loading && <p className="panel__hint">Cargando...</p>}

      {data && data.rows.length === 0 && (
        <p className="panel__hint">No hay jugadores activos.</p>
      )}

      {data && data.rows.length > 0 && (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Jugador</th>
                <th>Estado</th>
                <th className="num">Importe</th>
                {isAdmin && <th className="col-actions">Cobro</th>}
              </tr>
            </thead>
            <tbody>
              {data.rows.map((row) => (
                <tr key={row.member.id}>
                  <td>
                    <span className="cell-member">
                      <Avatar member={row.member} size={28} />
                      {row.member.nick}
                    </span>
                  </td>

                  <td>
                    <span
                      className={`status status--${row.paid ? 'entregado' : 'pendiente'}`}
                    >
                      {row.paid ? 'Al día' : 'Pendiente'}
                    </span>
                  </td>

                  <td className="num">
                    {row.amount === null ? '—' : `${formatPokeyen(row.amount)} ¥`}
                  </td>

                  {isAdmin && (
                    <td className="col-actions">
                      <label className="check">
                        <input
                          type="checkbox"
                          checked={row.paid}
                          disabled={busy === row.member.id}
                          onChange={() =>
                            void toggle(row.member.id, row.paid, data.default_amount)
                          }
                        />
                        Pagó
                      </label>

                      {!row.paid && (
                        <button
                          className="icon-btn"
                          type="button"
                          title="Cobrar otro importe"
                          disabled={busy === row.member.id}
                          onClick={() =>
                            void chargeCustom(row.member.id, row.member.nick)
                          }
                        >
                          ✏️
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
