import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, api, rankIconUrl } from '../api/client'
import { ranksApi } from '../api/podium'
import { useAuth } from '../auth/AuthContext'
import { formatPokeyen } from '../lib/format'
import type { Rank } from '../types'

interface CoRule {
  id: number
  label: string
  min_prize_value: number
  max_prize_value: number | null
  co_amount: number
  priority: number
  is_active: boolean
}

function RankRow({ rank, onChanged }: { rank: Rank; onChanged: () => void }) {
  const fileInput = useRef<HTMLInputElement>(null)
  const [name, setName] = useState(rank.name)
  const [color, setColor] = useState(rank.color_hex)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const dirty = name !== rank.name || color !== rank.color_hex

  async function save() {
    setError(null)
    setSaving(true)
    try {
      await ranksApi.update(rank.id, { name: name.trim(), color_hex: color })
      onChanged()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo guardar.')
    } finally {
      setSaving(false)
    }
  }

  async function uploadIcon(file: File) {
    setError(null)
    try {
      await ranksApi.uploadIcon(rank.id, file)
      onChanged()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo subir el icono.',
      )
    }
  }

  return (
    <div className="rank-row">
      <span className="rank-row__icon">
        {rank.has_icon ? (
          <img src={rankIconUrl(rank.id, rank.icon_version ?? null)} alt={rank.name} />
        ) : (
          <span style={{ color: 'var(--text-dim)', fontSize: 11 }}>sin icono</span>
        )}
      </span>

      <span className="rank-row__level">Nv. {rank.level}</span>

      <input
        className="field__input"
        value={name}
        onChange={(e) => setName(e.target.value)}
        maxLength={40}
        aria-label={`Nombre del rango ${rank.name}`}
      />

      <input
        className="rank-row__color"
        type="color"
        value={color}
        onChange={(e) => setColor(e.target.value)}
        aria-label={`Color del rango ${rank.name}`}
      />

      <div className="rank-row__actions">
        <button
          className="btn btn--ghost"
          type="button"
          onClick={() => fileInput.current?.click()}
        >
          Icono
        </button>
        {rank.has_icon && (
          <button
            className="icon-btn"
            type="button"
            title="Quitar icono"
            onClick={async () => {
              await ranksApi.removeIcon(rank.id)
              onChanged()
            }}
          >
            🚫
          </button>
        )}
        <button className="btn" type="button" onClick={save} disabled={!dirty || saving}>
          {saving ? '...' : 'Guardar'}
        </button>
      </div>

      <input
        ref={fileInput}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        hidden
        onChange={(e) => {
          const file = e.target.files?.[0]
          if (file) {
            void uploadIcon(file)
          }
          e.target.value = ''
        }}
      />

      {error && <div className="form-error rank-row__error">{error}</div>}
    </div>
  )
}

export function Settings() {
  const { isAdmin } = useAuth()
  const [ranks, setRanks] = useState<Rank[]>([])
  const [rules, setRules] = useState<CoRule[]>([])

  const loadRanks = useCallback(async () => {
    setRanks(await ranksApi.all())
  }, [])

  const loadRules = useCallback(async () => {
    setRules(await api.get<CoRule[]>('/co-rules'))
  }, [])

  useEffect(() => {
    void loadRanks()
    void loadRules()
  }, [loadRanks, loadRules])

  if (!isAdmin) {
    return (
      <div className="panel panel--empty">
        Esta seccion es solo para administradores.
      </div>
    )
  }

  return (
    <>
      <h1 className="page-title">Ajustes</h1>
      <p className="page-subtitle">
        Rangos del team y reglas de conversion de premios a CO.
      </p>

      <section className="panel">
        <h2 className="panel__title">Rangos</h2>
        <p className="panel__hint">
          Sube aquí los iconos que quieras usar. Máximo 256x256 px y 200 KB.
        </p>

        <div className="rank-list">
          {ranks.map((rank) => (
            <RankRow key={rank.id} rank={rank} onChanged={loadRanks} />
          ))}
        </div>
      </section>

      <section className="panel" style={{ marginTop: 16 }}>
        <h2 className="panel__title">Reglas de CO</h2>
        <p className="panel__hint">
          Determinan cuánto CO se sugiere al crear un evento según el valor del
          premio. El admin siempre puede sobrescribirlo a mano.
        </p>

        <div className="table-wrap" style={{ marginTop: 14 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Regla</th>
                <th className="num">Desde</th>
                <th className="num">Hasta</th>
                <th className="num">CO</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {rules.length === 0 && (
                <tr>
                  <td colSpan={5} className="table-empty">
                    No hay reglas definidas.
                  </td>
                </tr>
              )}
              {rules.map((rule) => (
                <tr key={rule.id}>
                  <td>{rule.label}</td>
                  <td className="num">{formatPokeyen(rule.min_prize_value)} ¥</td>
                  <td className="num">
                    {rule.max_prize_value === null
                      ? 'sin techo'
                      : `${formatPokeyen(rule.max_prize_value)} ¥`}
                  </td>
                  <td className="num balance balance--co">{rule.co_amount}</td>
                  <td>
                    <span
                      className={`status status--${rule.is_active ? 'entregado' : 'cancelado'}`}
                    >
                      {rule.is_active ? 'activa' : 'inactiva'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </>
  )
}
