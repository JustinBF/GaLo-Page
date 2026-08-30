import { useMemo, useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'
import { useMembers } from '../api/members'
import { redemptionsApi } from '../api/rewards'
import type { Reward } from '../types'
import { Modal } from './ui/Modal'
import { RewardImage } from './ui/RewardImage'

interface Props {
  reward: Reward
  onClose: () => void
  onDone: () => void
}

/**
 * Solo el admin registra canjes: la cuenta de jugador es compartida, así que
 * el jugador pide por fuera y aquí se apunta.
 */
export function RedeemModal({ reward, onClose, onDone }: Props) {
  // Los ascensos de rango se conceden a organizadores.
  const scope = reward.currency === 'CO' ? 'organizers' : 'players'
  const { members } = useMembers(scope)

  const [memberId, setMemberId] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const selected = useMemo(
    () => members.find((m) => String(m.id) === memberId) ?? null,
    [members, memberId],
  )

  const balance = selected
    ? reward.currency === 'CE'
      ? selected.ce_balance
      : selected.co_balance
    : null

  const canAfford = balance === null || balance >= reward.cost

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)

    if (!memberId) {
      setError('Elige a quién se le entrega el premio.')
      return
    }

    setSaving(true)
    try {
      await redemptionsApi.create({
        member_id: Number(memberId),
        reward_id: reward.id,
        note: note.trim() || null,
      })
      onDone()
      onClose()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo registrar el canje.',
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal title="Registrar canje" onClose={onClose}>
      <form onSubmit={handleSubmit}>
        {error && <div className="form-error">{error}</div>}

        <div className="redeem-summary">
          <RewardImage
            rewardId={reward.id}
            category={reward.category}
            hasImage={reward.has_image}
            version={reward.image_version}
            name={reward.name}
            size={56}
          />
          <div>
            <div className="redeem-summary__name">{reward.name}</div>
            <div className={`redeem-summary__cost balance--${reward.currency.toLowerCase()}`}>
              {reward.cost} {reward.currency}
            </div>
          </div>
        </div>

        <div className="field">
          <label className="field__label" htmlFor="rd-member">
            {reward.currency === 'CO' ? 'Organizador' : 'Jugador'}
          </label>
          <select
            id="rd-member"
            className="field__input"
            value={memberId}
            onChange={(e) => setMemberId(e.target.value)}
            required
          >
            <option value="">Elige un miembro</option>
            {members.map((member) => (
              <option key={member.id} value={member.id}>
                {member.nick} (
                {reward.currency === 'CE' ? member.ce_balance : member.co_balance}{' '}
                {reward.currency})
              </option>
            ))}
          </select>

          {selected && (
            <p className={`field__hint ${canAfford ? '' : 'is-error'}`}>
              {canAfford
                ? `Le quedarán ${balance! - reward.cost} ${reward.currency}.`
                : `Le faltan ${reward.cost - balance!} ${reward.currency}.`}
            </p>
          )}
        </div>

        {reward.grants_rank && (
          <p className="field__hint">
            Al canjearlo, {selected?.nick ?? 'el organizador'} pasará al rango{' '}
            <strong style={{ color: reward.grants_rank.color_hex }}>
              {reward.grants_rank.name}
            </strong>
            .
          </p>
        )}

        <div className="field">
          <label className="field__label" htmlFor="rd-note">
            Nota <span style={{ color: 'var(--text-dim)' }}>(opcional)</span>
          </label>
          <input
            id="rd-note"
            className="field__input"
            value={note}
            onChange={(e) => setNote(e.target.value)}
            maxLength={200}
            placeholder="Ej: entregado por Discord"
          />
        </div>

        <div className="modal__actions">
          <button className="btn btn--ghost" type="button" onClick={onClose}>
            Cancelar
          </button>
          <button className="btn" type="submit" disabled={saving || !canAfford}>
            {saving ? 'Registrando...' : 'Registrar canje'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
