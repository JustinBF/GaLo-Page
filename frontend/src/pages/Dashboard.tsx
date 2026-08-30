import { useEffect, useState } from 'react'
import { creditsApi, useEvents } from '../api/events'
import { useMembers } from '../api/members'
import { useAuth } from '../auth/AuthContext'
import { Avatar } from '../components/ui/Avatar'
import { formatDate, formatDateTime, formatPokeyen } from '../lib/format'
import type { CreditTransaction } from '../types'

const REASON_LABEL: Record<string, string> = {
  event_win: 'Podio',
  event_organized: 'Organización',
  redemption: 'Canje',
  manual_adjust: 'Ajuste',
  correction: 'Corrección',
}

export function Dashboard() {
  const { user, isAdmin } = useAuth()
  const { events } = useEvents()
  const { members: players } = useMembers('players')
  const { members: organizers } = useMembers('organizers')
  const [activity, setActivity] = useState<CreditTransaction[]>([])

  useEffect(() => {
    creditsApi
      .recent()
      .then((result) => setActivity(result.data))
      .catch(() => setActivity([]))
  }, [])

  const totalPrizes = events.reduce((sum, event) => sum + event.prize_value, 0)

  return (
    <>
      <h1 className="page-title">
        Bienvenido{user?.actor_label ? `, ${user.actor_label}` : ''}
      </h1>
      <p className="page-subtitle">
        {isAdmin
          ? 'Tienes permisos de escritura sobre tablas, eventos y créditos.'
          : 'Vista de solo lectura. Para canjear créditos, contacta con un administrador.'}
      </p>

      <div className="kpi-row">
        <div className="kpi">
          <span className="kpi__label">Eventos</span>
          <span className="kpi__value">{events.length}</span>
        </div>
        <div className="kpi">
          <span className="kpi__label">Jugadores</span>
          <span className="kpi__value">{players.length}</span>
        </div>
        <div className="kpi">
          <span className="kpi__label">Organizadores</span>
          <span className="kpi__value">{organizers.length}</span>
        </div>
        <div className="kpi">
          <span className="kpi__label">Premios repartidos</span>
          <span className="kpi__value kpi__value--gold">
            {formatPokeyen(totalPrizes)} ¥
          </span>
        </div>
      </div>

      <div className="dash-grid">
        <section className="panel">
          <h2 className="panel__title">Ultimos eventos</h2>
          {events.length === 0 && (
            <p className="panel__hint">Todavía no hay eventos registrados.</p>
          )}
          <ul className="mini-list">
            {events.slice(0, 5).map((event) => (
              <li className="mini-list__item" key={event.id}>
                <div>
                  <div className="mini-list__title">{event.name}</div>
                  <div className="mini-list__sub">{formatDate(event.held_at)}</div>
                </div>
                <span className="stat__value--gold">
                  {formatPokeyen(event.prize_value)} ¥
                </span>
              </li>
            ))}
          </ul>
        </section>

        <section className="panel">
          <h2 className="panel__title">Movimientos recientes</h2>
          {activity.length === 0 && (
            <p className="panel__hint">Sin movimientos de créditos todavía.</p>
          )}
          <ul className="mini-list">
            {activity.slice(0, 8).map((transaction) => (
              <li className="mini-list__item" key={transaction.id}>
                <div>
                  <div className="mini-list__title">
                    {transaction.member?.nick ?? '—'}
                  </div>
                  <div className="mini-list__sub">
                    {REASON_LABEL[transaction.reason] ?? transaction.reason}
                    {' · '}
                    {formatDateTime(transaction.created_at)}
                  </div>
                </div>
                <span
                  className={
                    transaction.amount >= 0
                      ? 'ledger__amount is-positive'
                      : 'ledger__amount is-negative'
                  }
                >
                  {transaction.amount >= 0 ? '+' : ''}
                  {transaction.amount} {transaction.currency}
                </span>
              </li>
            ))}
          </ul>
        </section>

        <section className="panel">
          <h2 className="panel__title">Top CE</h2>
          {players.length === 0 && (
            <p className="panel__hint">Todavía no hay jugadores.</p>
          )}
          <ul className="mini-list">
            {players.slice(0, 5).map((member, index) => (
              <li className="mini-list__item" key={member.id}>
                <div className="cell-member">
                  <span className="col-pos">{index + 1}</span>
                  <Avatar member={member} size={28} />
                  <span>{member.nick}</span>
                </div>
                <span className="balance balance--ce">{member.ce_balance}</span>
              </li>
            ))}
          </ul>
        </section>

        <section className="panel">
          <h2 className="panel__title">Top CO</h2>
          {organizers.length === 0 && (
            <p className="panel__hint">Todavía no hay organizadores.</p>
          )}
          <ul className="mini-list">
            {organizers.slice(0, 5).map((member, index) => (
              <li className="mini-list__item" key={member.id}>
                <div className="cell-member">
                  <span className="col-pos">{index + 1}</span>
                  <Avatar member={member} size={28} />
                  <span>{member.nick}</span>
                </div>
                <span className="balance balance--co">{member.co_balance}</span>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </>
  )
}
