import { useEffect, useState } from 'react'
import { NavLink } from 'react-router-dom'
import { api } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { formatPokeyen } from '../../lib/format'
import { BankPanel } from '../BankPanel'
import { PodiumModal } from '../PodiumModal'
import { ThemeToggle } from '../ui/ThemeToggle'
import iconAjustes from '../../assets/brand/icon-ajustes.png'
import iconEventos from '../../assets/brand/icon-eventos.png'
import iconJugador from '../../assets/brand/icon-jugador.png'
import iconOrganizador from '../../assets/brand/icon-organizador.png'
import iconTienda from '../../assets/brand/icon-tienda.png'
import logoGalo from '../../assets/brand/logo-galo.png'
import meowth from '../../assets/brand/meowth.png'
import type { TeamSettings } from '../../types'

const LINKS = [
  { to: '/', label: 'Inicio', icon: meowth, end: true },
  { to: '/jugadores', label: 'Créditos CE', icon: iconJugador },
  { to: '/organizadores', label: 'Créditos CO', icon: iconOrganizador },
  { to: '/eventos', label: 'Eventos', icon: iconEventos },
  { to: '/tienda', label: 'Tienda', icon: iconTienda },
]

export function AppHeader() {
  const { user, logout, isAdmin } = useAuth()
  const [settings, setSettings] = useState<TeamSettings | null>(null)
  const [showBank, setShowBank] = useState(false)
  const [showPodium, setShowPodium] = useState(false)

  function loadSettings() {
    api.get<TeamSettings>('/settings').then(setSettings).catch(() => undefined)
  }

  useEffect(loadSettings, [])

  return (
    <>
      <header className="topnav">
        <div className="topnav__brand">
          <img className="topnav__logo" src={logoGalo} alt="GaLo" />
          <span className="topnav__name">GaLo</span>
        </div>

        <nav className="topnav__links">
          {LINKS.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              end={link.end}
              className={({ isActive }) =>
                `nav-item${isActive ? ' nav-item--active' : ''}`
              }
            >
              <img className="nav-item__icon" src={link.icon} alt="" />
              <span>{link.label}</span>
            </NavLink>
          ))}

          {isAdmin && (
            <NavLink
              to="/ajustes"
              className={({ isActive }) =>
                `nav-item${isActive ? ' nav-item--active' : ''}`
              }
            >
              <img className="nav-item__icon" src={iconAjustes} alt="" />
              <span>Ajustes</span>
            </NavLink>
          )}
        </nav>

        <div className="topnav__side">
          <button
            className="bank-pill"
            type="button"
            onClick={() => setShowBank(true)}
            title="Banco del Equipo"
          >
            <span className="bank-pill__icon">🏦</span>
            <span className="bank-pill__body">
              <span className="bank-pill__label">Banco del Equipo</span>
              <span className="bank-pill__amount">
                {settings ? `${formatPokeyen(settings.bank_balance)} ¥` : '—'}
              </span>
            </span>
          </button>

          <button
            className="round-btn round-btn--gold"
            type="button"
            onClick={() => setShowPodium(true)}
            title="Ver el podio"
          >
            🏆
          </button>

          <ThemeToggle />

          <div className="topnav__user">
            <span className={`badge badge--${isAdmin ? 'admin' : 'player'}`}>
              {isAdmin ? 'Admin' : 'Miembro'}
            </span>
            {user?.actor_label && (
              <span className="topnav__actor">{user.actor_label}</span>
            )}
            <button
              className="round-btn"
              type="button"
              onClick={logout}
              title="Cerrar sesión"
            >
              ⏻
            </button>
          </div>
        </div>
      </header>

      {showBank && (
        <BankPanel onClose={() => setShowBank(false)} onChanged={loadSettings} />
      )}

      {showPodium && <PodiumModal onClose={() => setShowPodium(false)} />}
    </>
  )
}
