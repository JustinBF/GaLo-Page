import { Component, type ErrorInfo, type ReactNode } from 'react'

interface Props {
  children: ReactNode
}

interface State {
  error: Error | null
}

/**
 * Evita que un fallo al pintar una pantalla tumbe la aplicación entera y
 * deje al usuario mirando una página en blanco.
 *
 * Pasó de verdad: el frontend se desplegó antes que la API y esperaba un
 * campo que la versión vieja no mandaba.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Fallo al pintar la pantalla:', error, info.componentStack)
  }

  render() {
    const { error } = this.state

    if (!error) {
      return this.props.children
    }

    return (
      <div className="crash">
        <h1 className="page-title">Algo se rompió al cargar esta pantalla</h1>
        <p className="page-subtitle">
          Suele pasar cuando la web se actualizó pero la API todavía no.
          Prueba a recargar; si sigue igual, avisa al administrador.
        </p>
        <p className="crash__detail">{error.message}</p>
        <button
          className="btn"
          type="button"
          onClick={() => {
            this.setState({ error: null })
            window.location.reload()
          }}
        >
          Recargar
        </button>
      </div>
    )
  }
}
