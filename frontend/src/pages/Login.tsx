import { useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { ThemeToggle } from '../components/ui/ThemeToggle'
import logoGalo from '../assets/brand/logo-galo.png'
import meowth from '../assets/brand/meowth.png'

export function Login() {
  const { user, login } = useAuth()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [actorLabel, setActorLabel] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to="/" replace />
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      await login(username.trim(), password, actorLabel.trim())
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'No se pudo conectar con el servidor.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="login-screen">
      <div className="login-screen__theme">
        <ThemeToggle />
      </div>

      <img className="login-mascot" src={meowth} alt="" />

      <form className="login-card" onSubmit={handleSubmit}>
        <img className="login-card__logo" src={logoGalo} alt="GaLo" />

        <h1 className="login-card__title">Iniciar sesión</h1>
        <p className="login-card__subtitle">Eventos y créditos del team</p>

        {error && <div className="form-error">{error}</div>}

        <div className="field field--underline">
          <label className="field__label" htmlFor="username">
            Usuario
          </label>
          <input
            id="username"
            className="field__input"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            autoComplete="username"
            required
          />
        </div>

        <div className="field field--underline">
          <label className="field__label" htmlFor="password">
            Contraseña
          </label>
          <input
            id="password"
            className="field__input"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
          />
        </div>

        <div className="field field--underline">
          <label className="field__label" htmlFor="actor">
            Tu nombre <span style={{ color: 'var(--text-dim)' }}>(opcional)</span>
          </label>
          <input
            id="actor"
            className="field__input"
            value={actorLabel}
            onChange={(e) => setActorLabel(e.target.value)}
            placeholder="Ej: Justin"
          />
        </div>

        <button className="btn btn--block" type="submit" disabled={submitting}>
          {submitting ? 'Entrando...' : 'Entrar'}
        </button>

        <p className="login-card__note">
          La cuenta es compartida: tu nombre queda en el registro de cambios.
        </p>
      </form>
    </div>
  )
}
