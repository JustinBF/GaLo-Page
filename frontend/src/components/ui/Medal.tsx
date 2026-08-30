import bronce from '../../assets/brand/medalla-bronce.png'
import oro from '../../assets/brand/medalla-oro.png'
import plata from '../../assets/brand/medalla-plata.png'

const MEDALS = [oro, plata, bronce]
const LABELS = ['Oro', 'Plata', 'Bronce']

/** Medalla del podio. `position` va de 1 a 3. */
export function Medal({ position, size = 26 }: { position: number; size?: number }) {
  const index = position - 1
  const src = MEDALS[index]

  if (!src) {
    return null
  }

  return (
    <img
      className="medal"
      src={src}
      alt={LABELS[index]}
      title={`Top ${position}`}
      style={{ width: size, height: size }}
    />
  )
}
