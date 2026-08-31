const BASE_URL = import.meta.env.VITE_API_URL ?? '/api'
const TOKEN_KEY = 'galo.token'

export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(
    message: string,
    status: number,
    errors: Record<string, string[]> = {},
  ) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

export const tokenStore = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
): Promise<T> {
  const token = tokenStore.get()

  const response = await fetch(`${BASE_URL}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })

  if (response.status === 204) {
    return undefined as T
  }

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    // El token caducó o fue revocado: obliga a volver al login.
    if (response.status === 401) {
      tokenStore.clear()
    }
    throw new ApiError(
      payload.message ?? 'Error de conexión con el servidor.',
      response.status,
      payload.errors ?? {},
    )
  }

  return payload as T
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  // Con cuerpo opcional: las cuotas identifican el cobro por jugador y semana.
  delete: <T>(path: string, body?: unknown) => request<T>('DELETE', path, body),
}

/**
 * Subida de archivos. No fija Content-Type a propósito: el navegador debe
 * generar el boundary del multipart por su cuenta.
 */
export async function uploadFile<T>(path: string, body: FormData): Promise<T> {
  const token = tokenStore.get()

  const response = await fetch(`${BASE_URL}${path}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body,
  })

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    if (response.status === 401) {
      tokenStore.clear()
    }
    throw new ApiError(
      payload.message ?? 'No se pudo subir la imagen.',
      response.status,
      payload.errors ?? {},
    )
  }

  return payload as T
}

/** URL absoluta del avatar, con version para invalidar la cache del navegador. */
export function avatarUrl(
  memberId: number,
  version: number | null,
): string {
  return `${BASE_URL}/members/${memberId}/avatar?v=${version ?? 0}`
}

/** URL absoluta de la imagen de un premio. */
export function rewardImageUrl(
  rewardId: number,
  version: number | null,
): string {
  return `${BASE_URL}/rewards/${rewardId}/image?v=${version ?? 0}`
}

/**
 * URL absoluta de la insignia de un evento. `position` null pide la general.
 */
export function eventBadgeUrl(
  eventId: number,
  position: number | null,
  version: number | null,
): string {
  const slot = position ?? 'general'
  return `${BASE_URL}/events/${eventId}/badge/${slot}?v=${version ?? 0}`
}

/** URL absoluta del icono de un rango. */
export function rankIconUrl(rankId: number, version: number | null): string {
  return `${BASE_URL}/ranks/${rankId}/icon?v=${version ?? 0}`
}
