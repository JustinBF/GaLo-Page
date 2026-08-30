import { useState } from 'react'
import { redemptionsApi, rewardsApi, useRedemptions, useRewards } from '../api/rewards'
import { RedeemModal } from '../components/RedeemModal'
import { RewardFormModal } from '../components/RewardFormModal'
import { Avatar } from '../components/ui/Avatar'
import { RewardImage } from '../components/ui/RewardImage'
import { useAuth } from '../auth/AuthContext'
import { RoleGate } from '../auth/RoleGate'
import { formatDateTime } from '../lib/format'
import type { Currency, Reward } from '../types'

const CATEGORY_LABEL: Record<string, string> = {
  pokemon: 'Pokémon',
  objeto: 'Objeto',
  cosmetico: 'Cosmético',
  ascenso_rango: 'Ascenso',
  especial: 'Especial',
}

export function Shop() {
  const { isAdmin } = useAuth()
  const [currency, setCurrency] = useState<Currency>('CE')
  const [showRetired, setShowRetired] = useState(false)
  const { rewards, loading, error, reload } = useRewards(currency, showRetired)
  const { redemptions, reload: reloadRedemptions } = useRedemptions()

  const [editing, setEditing] = useState<Reward | null>(null)
  const [creating, setCreating] = useState(false)
  const [redeeming, setRedeeming] = useState<Reward | null>(null)

  async function handleRetire(reward: Reward) {
    if (!confirm(`Retirar "${reward.name}" de la tienda?`)) {
      return
    }
    await rewardsApi.retire(reward.id)
    void reload()
  }

  async function handleDeliver(id: number) {
    await redemptionsApi.setStatus(id, 'entregado')
    void reloadRedemptions()
  }

  async function handleCancel(id: number) {
    if (!confirm('Cancelar el canje y devolver los créditos?')) {
      return
    }
    await redemptionsApi.cancel(id)
    void reloadRedemptions()
  }

  function refreshAll() {
    void reload()
    void reloadRedemptions()
  }

  const visibleRedemptions = redemptions.filter((r) => r.currency === currency)

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Tienda</h1>
          <p className="page-subtitle">
            {isAdmin
              ? 'Catalogo de premios y registro de canjes.'
              : 'Catalogo de premios. Para canjear, contacta con un administrador.'}
          </p>
        </div>
        <div className="page-head__actions">
          <RoleGate>
            <label className="check">
              <input
                type="checkbox"
                checked={showRetired}
                onChange={(e) => setShowRetired(e.target.checked)}
              />
              Ver retirados
            </label>
            <button className="btn" type="button" onClick={() => setCreating(true)}>
              + Nuevo premio
            </button>
          </RoleGate>
        </div>
      </div>

      <div className="tabs">
        <button
          className={`tab ${currency === 'CE' ? 'tab--active tab--ce' : ''}`}
          type="button"
          onClick={() => setCurrency('CE')}
        >
          Tienda CE
          <span className="tab__hint">Premios del Banco del Team</span>
        </button>
        <button
          className={`tab ${currency === 'CO' ? 'tab--active tab--co' : ''}`}
          type="button"
          onClick={() => setCurrency('CO')}
        >
          Tienda CO
          <span className="tab__hint">Ascensos y premios especiales</span>
        </button>
      </div>

      {error && <div className="form-error">{error}</div>}

      {loading && <div className="panel panel--empty">Cargando tienda...</div>}

      {!loading && rewards.length === 0 && (
        <div className="panel panel--empty">
          Todavía no hay premios en la tienda {currency}.
        </div>
      )}

      <div className="shop-grid">
        {rewards.map((reward) => (
          <article
            className={`reward-card ${reward.is_active ? '' : 'reward-card--retired'}`}
            key={reward.id}
          >
            {reward.is_featured && <span className="reward-card__flag">Destacado</span>}

            <RewardImage
              rewardId={reward.id}
              category={reward.category}
              hasImage={reward.has_image}
              version={reward.image_version}
              name={reward.name}
              size={84}
            />

            <h2 className="reward-card__name">{reward.name}</h2>
            <span className="reward-card__cat">
              {CATEGORY_LABEL[reward.category] ?? reward.category}
            </span>

            {reward.description && (
              <p className="reward-card__desc">{reward.description}</p>
            )}

            {reward.grants_rank && (
              <span
                className="rank-badge"
                style={{
                  color: reward.grants_rank.color_hex,
                  borderColor: reward.grants_rank.color_hex,
                  background: `${reward.grants_rank.color_hex}1f`,
                }}
              >
                Sube a {reward.grants_rank.name}
              </span>
            )}

            <div className={`reward-card__cost balance--${currency.toLowerCase()}`}>
              {reward.cost} {currency}
            </div>

            <div className="reward-card__stock">
              {reward.stock === null
                ? 'Stock ilimitado'
                : reward.stock > 0
                  ? `${reward.stock} disponibles`
                  : 'Agotado'}
            </div>

            {isAdmin && (
              <div className="reward-card__actions">
                <button
                  className="btn"
                  type="button"
                  disabled={!reward.in_stock || !reward.is_active}
                  onClick={() => setRedeeming(reward)}
                >
                  Canjear
                </button>
                <button
                  className="icon-btn"
                  type="button"
                  title="Editar"
                  onClick={() => setEditing(reward)}
                >
                  ✏️
                </button>
                {reward.is_active && (
                  <button
                    className="icon-btn"
                    type="button"
                    title="Retirar"
                    onClick={() => handleRetire(reward)}
                  >
                    🚫
                  </button>
                )}
              </div>
            )}
          </article>
        ))}
      </div>

      <h2 className="section-title">Canjes de {currency}</h2>

      {visibleRedemptions.length === 0 && (
        <div className="panel panel--empty">Todavía no hay canjes registrados.</div>
      )}

      {visibleRedemptions.length > 0 && (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Miembro</th>
                <th>Premio</th>
                <th className="num">Coste</th>
                <th>Fecha</th>
                <th>Estado</th>
                {isAdmin && <th className="col-actions" />}
              </tr>
            </thead>
            <tbody>
              {visibleRedemptions.map((redemption) => (
                <tr key={redemption.id}>
                  <td>
                    <div className="cell-member">
                      {redemption.member && (
                        <Avatar member={redemption.member} size={28} />
                      )}
                      <span>{redemption.member?.nick ?? '—'}</span>
                    </div>
                  </td>
                  <td>
                    <div className="cell-member">
                      {redemption.reward && (
                        <RewardImage
                          rewardId={redemption.reward.id}
                          category={redemption.reward.category}
                          hasImage={redemption.reward.has_image}
                          version={redemption.reward.image_version}
                          name={redemption.reward_name}
                          size={28}
                        />
                      )}
                      <span>{redemption.reward_name}</span>
                    </div>
                  </td>
                  <td className={`num balance balance--${currency.toLowerCase()}`}>
                    {redemption.cost_paid}
                  </td>
                  <td style={{ color: 'var(--text-muted)', fontSize: 13 }}>
                    {formatDateTime(redemption.created_at)}
                  </td>
                  <td>
                    <span className={`status status--${redemption.status}`}>
                      {redemption.status}
                    </span>
                  </td>
                  {isAdmin && (
                    <td className="col-actions">
                      {redemption.status === 'pendiente' && (
                        <button
                          className="icon-btn"
                          type="button"
                          title="Marcar entregado"
                          onClick={() => handleDeliver(redemption.id)}
                        >
                          ✅
                        </button>
                      )}
                      {redemption.status !== 'cancelado' && (
                        <button
                          className="icon-btn"
                          type="button"
                          title="Cancelar y devolver créditos"
                          onClick={() => handleCancel(redemption.id)}
                        >
                          ↩️
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

      {(creating || editing) && (
        <RewardFormModal
          reward={editing}
          currency={currency}
          onClose={() => {
            setCreating(false)
            setEditing(null)
          }}
          onSaved={reload}
        />
      )}

      {redeeming && (
        <RedeemModal
          reward={redeeming}
          onClose={() => setRedeeming(null)}
          onDone={refreshAll}
        />
      )}
    </>
  )
}
