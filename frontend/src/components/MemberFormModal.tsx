import { useRef, useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'
import { membersApi, useRanks } from '../api/members'
import type { Member } from '../types'
import { Avatar } from './ui/Avatar'
import { Modal } from './ui/Modal'

interface Props {
  member: Member | null
  defaultRole: 'player' | 'organizer'
  onClose: () => void
  onSaved: () => void
}

export function MemberFormModal({ member, defaultRole, onClose, onSaved }: Props) {
  const ranks = useRanks()
  const fileInput = useRef<HTMLInputElement>(null)

  const [nick, setNick] = useState(member?.nick ?? '')
  const [rankId, setRankId] = useState<string>(member?.rank?.id.toString() ?? '')
  const [isPlayer, setIsPlayer] = useState(member?.is_player ?? defaultRole === 'player')
  const [isOrganizer, setIsOrganizer] = useState(
    member?.is_organizer ?? defaultRole === 'organizer',
  )
  const [notes, setNotes] = useState(member?.notes ?? '')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  // Refleja el avatar recién subido sin recargar toda la tabla.
  const [preview, setPreview] = useState(member)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    const payload = {
      nick: nick.trim(),
      rank_id: rankId ? Number(rankId) : null,
      is_player: isPlayer,
      is_organizer: isOrganizer,
      notes: notes.trim() || null,
    }

    try {
      if (member) {
        await membersApi.update(member.id, payload)
      } else {
        await membersApi.create(payload)
      }
      onSaved()
      onClose()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo guardar.',
      )
    } finally {
      setSaving(false)
    }
  }

  async function handleAvatar(file: File) {
    if (!member) {
      return
    }
    setError(null)
    try {
      const result = await membersApi.uploadAvatar(member.id, file)
      setPreview(result.data)
      onSaved()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo subir la imagen.',
      )
    }
  }

  async function handleRemoveAvatar() {
    if (!member) {
      return
    }
    const result = await membersApi.removeAvatar(member.id)
    setPreview(result.data)
    onSaved()
  }

  return (
    <Modal title={member ? `Editar ${member.nick}` : 'Nuevo miembro'} onClose={onClose}>
      <form onSubmit={handleSubmit}>
        {error && <div className="form-error">{error}</div>}

        {member && preview && (
          <div className="avatar-editor">
            <Avatar member={preview} size={64} />
            <div className="avatar-editor__actions">
              <button
                className="btn btn--ghost"
                type="button"
                onClick={() => fileInput.current?.click()}
              >
                Subir PNG
              </button>
              {preview.has_avatar && (
                <button
                  className="btn btn--ghost"
                  type="button"
                  onClick={handleRemoveAvatar}
                >
                  Quitar
                </button>
              )}
              <p className="field__hint">Max. 512x512 px y 200 KB.</p>
            </div>
            <input
              ref={fileInput}
              type="file"
              accept="image/png,image/jpeg,image/webp"
              hidden
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) {
                  void handleAvatar(file)
                }
                e.target.value = ''
              }}
            />
          </div>
        )}

        <div className="field">
          <label className="field__label" htmlFor="nick">
            Nick
          </label>
          <input
            id="nick"
            className="field__input"
            value={nick}
            onChange={(e) => setNick(e.target.value)}
            maxLength={40}
            required
            autoFocus
          />
        </div>

        <div className="field">
          <label className="field__label" htmlFor="rank">
            Rango de jugador
          </label>
          <select
            id="rank"
            className="field__input"
            value={rankId}
            onChange={(e) => setRankId(e.target.value)}
          >
            <option value="">Sin rango</option>
            {ranks.map((rank) => (
              <option key={rank.id} value={rank.id}>
                {rank.name}
              </option>
            ))}
          </select>
          <p className="field__hint">
            El rango de organizador no se edita aquí: se canjea con CO en la tienda.
          </p>
        </div>

        <div className="field field--checks">
          <label className="check">
            <input
              type="checkbox"
              checked={isPlayer}
              onChange={(e) => setIsPlayer(e.target.checked)}
            />
            Aparece en la tabla CE
          </label>
          <label className="check">
            <input
              type="checkbox"
              checked={isOrganizer}
              onChange={(e) => setIsOrganizer(e.target.checked)}
            />
            Aparece en la tabla CO
          </label>
        </div>

        <div className="field">
          <label className="field__label" htmlFor="notes">
            Notas <span style={{ color: 'var(--text-dim)' }}>(opcional)</span>
          </label>
          <textarea
            id="notes"
            className="field__input"
            rows={2}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            maxLength={500}
          />
        </div>

        <div className="modal__actions">
          <button className="btn btn--ghost" type="button" onClick={onClose}>
            Cancelar
          </button>
          <button className="btn" type="submit" disabled={saving}>
            {saving ? 'Guardando...' : 'Guardar'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
