/** Formatea Pokeyenes crudos al estilo del juego: 1500000 -> 1.5m */
export function formatPokeyen(amount: number): string {
  if (amount >= 1_000_000) {
    return `${(amount / 1_000_000).toFixed(amount % 1_000_000 === 0 ? 0 : 1)}m`
  }
  if (amount >= 1_000) {
    return `${(amount / 1_000).toFixed(amount % 1_000 === 0 ? 0 : 1)}k`
  }
  return String(amount)
}

/**
 * Convierte lo que escribe el admin ("1.5m", "500k", "750000") a Pokeyenes
 * crudos. Devuelve null si no se entiende.
 */
export function parsePokeyen(input: string): number | null {
  const clean = input.trim().toLowerCase().replace(/\s|,/g, '')
  if (!clean) {
    return null
  }

  const match = /^(\d+(?:\.\d+)?)(k|m)?$/.exec(clean)
  if (!match) {
    return null
  }

  const value = Number(match[1])
  const factor = match[2] === 'm' ? 1_000_000 : match[2] === 'k' ? 1_000 : 1

  return Math.round(value * factor)
}

const DATE_FORMAT = new Intl.DateTimeFormat('es-ES', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
})

export function formatDate(iso: string | null): string {
  return iso ? DATE_FORMAT.format(new Date(`${iso}T00:00:00`)) : '—'
}

export function formatDateTime(value: string | null): string {
  return value ? value.slice(0, 16).replace('T', ' ') : '—'
}
