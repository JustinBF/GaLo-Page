import { useRef, useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'
import { useRanks } from '../api/members'
import { rewardsApi } from '../api/rewards'
import type { Currency, Reward, RewardCategory, RewardPayload } from '../types'
import { Modal } from './ui/Modal'
import { RewardImage } from './ui/RewardImage'

interface Props {
  reward: Reward | null
  currency: Currency
  onClose: () => void
  onSaved: () => void
}

const CATEGORIES: { value: RewardCategory; label: string }[] = [
  { value: 'pokemon', label: 'Pokémon' },
  { value: 'objeto', label: 'Objeto' },
  { value: 'cosmetico', label: 'Cosmético' },
  { value: 'ascenso_rango', label: 'Ascenso de rango' },
  { value: 'especial', label: 'Especial' },
]

export function RewardFormModal({ reward, currency, onClose, onSaved }: Props) {
  const ranks = useRanks()
  const fileInput = useRef<HTMLInputElement>(null)

  const [name, setName] = useState(reward?.name ?? '')
  const [description, setDescription] = useState(reward?.description ?? '')
  const [cost, setCost] = useState(reward ? String(reward.cost) : '')
  const [category, setCategory] = useState<RewardCategory>(
    reward?.category ?? (currency === 'CO' ? 'ascenso_rango' : 'pokemon'),
  )
  const [grantsRankId, setGrantsRankId] = useState(
    reward?.grants_rank?.id ? String(reward.grants_rank.id) : '',
  )
  const [unlimited, setUnlimited] = useState(reward ? reward.stock === null : true)
  const [stock, setStock] = useState(reward?.stock != null ? String(reward.stock) : '1')
  const [isActive, setIsActive] = useState(reward?.is_active ?? true)
  const [isFeatured, setIsFeatured] = useState(reward?.is_featured ?? false)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [preview, setPreview] = useState(reward)

  // Los ascensos de rango son la recompensa de los organizadores.
  const isRankReward = category === 'ascenso_rango'

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)

    const payload: RewardPayload = {
      name: name.trim(),
      description: description.trim() || null,
      currency,
      cost: Number(cost || 0),
      category,
      grants_rank_id: isRankReward && grantsRankId ? Number(grantsRankId) : null,
      stock: unlimited ? null : Number(stock || 0),
      is_active: isActive,
      is_featured: isFeatured,
    }

    setSaving(true)
    try {
      if (reward) {
        await rewardsApi.update(reward.id, payload)
      } else {
        await rewardsApi.create(payload)
      }
      onSaved()
      onClose()
    } catch (err) {
      setError(
        err instanceof ApiError
          ? Object.values(err.errors)[0]?.[0] ?? err.message
          : 'No se pudo guardar el premio.',
      )
    } finally {
      setSaving(false)
    }
  }

  async function handleImage(file: File) {
    if (!reward) {
      return
    }
    setError(null)
    try {
      const result = await rewardsApi.uploadImage(reward.id, file)
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

  async function handleRemoveImage() {
    if (!reward) {
      return
    }
    const result = await rewardsApi.removeImage(reward.id)
    setPreview(result.data)
    onSaved()
  }

  return (
    <Modal
      title={reward ? `Editar ${reward.name}` : `Nuevo premio de la tienda ${currency}`}
      onClose={onClose}
      width={540}
    >
      <form onSubmit={handleSubmit}>
        {error && <div className="form-error">{error}</div>}

        {reward && preview && (
          <div className="avatar-editor">
            <RewardImage
              rewardId={preview.id}
              category={preview.category}
              hasImage={preview.has_image}
              version={preview.image_version}
              name={preview.name}
              size={72}
            />
            <div className="avatar-editor__actions">
              <button
                className="btn btn--ghost"
                type="button"
                onClick={() => fileInput.current?.click()}
              >
                Subir PNG
              </button>
              {preview.has_image && (
                <button className="btn btn--ghost" type="button" onClick={handleRemoveImage}>
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
                  void handleImage(file)
                }
                e.target.value = ''
              }}
            />
          </div>
        )}

        {!reward && (
          <p className="field__hint" style={{ marginBottom: 14 }}>
            Guarda el premio primero y luego podrás subirle el PNG.
          </p>
        )}

        <div className="field">
          <label className="field__label" htmlFor="rw-name">
            Nombre
          </label>
          <input
            id="rw-name"
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
            <label className="field__label" htmlFor="rw-cost">
              Precio en {currency}
            </label>
            <input
              id="rw-cost"
              className="field__input"
              type="number"
              min={0}
              value={cost}
              onChange={(e) => setCost(e.target.value)}
              required
            />
          </div>

          <div className="field">
            <label className="field__label" htmlFor="rw-cat">
              Categoría
            </label>
            <select
              id="rw-cat"
              className="field__input"
              value={category}
              onChange={(e) => setCategory(e.target.value as RewardCategory)}
            >
              {CATEGORIES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {isRankReward && (
          <div className="field">
            <label className="field__label" htmlFor="rw-rank">
              Rango que otorga
            </label>
            <select
              id="rw-rank"
              className="field__input"
              value={grantsRankId}
              onChange={(e) => setGrantsRankId(e.target.value)}
              required
            >
              <option value="">Elige un rango</option>
              {ranks.map((rank) => (
                <option key={rank.id} value={rank.id}>
                  {rank.name}
                </option>
              ))}
            </select>
            <p className="field__hint">
              {currency === 'CO'
                ? 'Al canjearlo, el organizador sube a este rango automáticamente.'
                : 'Los ascensos solo se pueden vender en la tienda CO.'}
            </p>
          </div>
        )}

        <div className="field">
          <label className="field__label" htmlFor="rw-desc">
            Descripción <span style={{ color: 'var(--text-dim)' }}>(opcional)</span>
          </label>
          <textarea
            id="rw-desc"
            className="field__input"
            rows={2}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={500}
          />
        </div>

        <div className="field">
          <label className="check">
            <input
              type="checkbox"
              checked={unlimited}
              onChange={(e) => setUnlimited(e.target.checked)}
            />
            Stock ilimitado
          </label>
          {!unlimited && (
            <input
              className="field__input"
              type="number"
              min={0}
              value={stock}
              onChange={(e) => setStock(e.target.value)}
              style={{ marginTop: 10 }}
              aria-label="Unidades disponibles"
            />
          )}
        </div>

        <div className="field field--checks">
          <label className="check">
            <input
              type="checkbox"
              checked={isActive}
              onChange={(e) => setIsActive(e.target.checked)}
            />
            Visible en la tienda
          </label>
          <label className="check">
            <input
              type="checkbox"
              checked={isFeatured}
              onChange={(e) => setIsFeatured(e.target.checked)}
            />
            Destacado
          </label>
        </div>

        <div className="modal__actions">
          <button className="btn btn--ghost" type="button" onClick={onClose}>
            Cancelar
          </button>
          <button className="btn" type="submit" disabled={saving}>
            {saving ? 'Guardando...' : 'Guardar premio'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
