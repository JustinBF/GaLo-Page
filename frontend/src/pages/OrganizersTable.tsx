import { useState } from 'react'
import { membersApi, useMembers } from '../api/members'
import { CreditHistoryModal } from '../components/CreditHistoryModal'
import { MemberFormModal } from '../components/MemberFormModal'
import { MemberProfileModal } from '../components/MemberProfileModal'
import { Avatar } from '../components/ui/Avatar'
import { RankBadge } from '../components/ui/RankBadge'
import { formatPokeyen } from '../lib/format'
import { useAuth } from '../auth/AuthContext'
import { RoleGate } from '../auth/RoleGate'
import type { Member } from '../types'

export function OrganizersTable() {
  const { isAdmin } = useAuth()
  const [showInactive, setShowInactive] = useState(false)
  const { members, loading, error, reload } = useMembers('organizers', showInactive)
  const [editing, setEditing] = useState<Member | null>(null)
  const [creating, setCreating] = useState(false)
  const [viewing, setViewing] = useState<Member | null>(null)
  const [profiling, setProfiling] = useState<Member | null>(null)

  async function handleDeactivate(member: Member) {
    if (!confirm(`Desactivar a ${member.nick}? Su historial se conserva.`)) {
      return
    }
    await membersApi.deactivate(member.id)
    void reload()
  }

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Créditos de Organizador (CO)</h1>
          <p className="page-subtitle">
            Organizadores, eventos montados y valor total de premios repartidos.
          </p>
        </div>
        <div className="page-head__actions">
          <RoleGate>
            <label className="check">
              <input
                type="checkbox"
                checked={showInactive}
                onChange={(e) => setShowInactive(e.target.checked)}
              />
              Ver inactivos
            </label>
            <button className="btn" type="button" onClick={() => setCreating(true)}>
              + Nuevo organizador
            </button>
          </RoleGate>
        </div>
      </div>

      {error && <div className="form-error">{error}</div>}

      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th className="col-pos">#</th>
              <th>Organizador</th>
              <th>Rango</th>
              <th className="num">Eventos</th>
              <th className="num">Premios repartidos</th>
              <th className="num">Saldo CO</th>
              {isAdmin && <th className="col-actions" />}
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={isAdmin ? 7 : 6} className="table-empty">
                  Cargando...
                </td>
              </tr>
            )}

            {!loading && members.length === 0 && (
              <tr>
                <td colSpan={isAdmin ? 7 : 6} className="table-empty">
                  Todavía no hay organizadores en la tabla.
                </td>
              </tr>
            )}

            {members.map((member, index) => (
              <tr key={member.id} className={member.is_active ? '' : 'row--inactive'}>
                <td className="col-pos">{index + 1}</td>
                <td>
                  <div className="cell-member">
                    <Avatar member={member} />
                    <button
                      className="nick-btn"
                      type="button"
                      title="Ver perfil"
                      onClick={() => setProfiling(member)}
                    >
                      {member.nick}
                    </button>
                    {!member.is_active && <span className="tag-off">inactivo</span>}
                  </div>
                </td>
                <td>
                  {/* El rango de organizador se gana canjeando CO. */}
                  <RankBadge rank={member.organizer_rank} />
                </td>
                <td className="num">{member.events_organized ?? 0}</td>
                <td className="num">{formatPokeyen(member.prizes_total ?? 0)} ¥</td>
                <td className="num">
                  <button
                    className="balance-btn balance balance--co"
                    type="button"
                    title="Ver historial"
                    onClick={() => setViewing(member)}
                  >
                    {member.co_balance}
                  </button>
                </td>
                {isAdmin && (
                  <td className="col-actions">
                    <button
                      className="icon-btn"
                      type="button"
                      title="Editar"
                      onClick={() => setEditing(member)}
                    >
                      ✏️
                    </button>
                    {member.is_active && (
                      <button
                        className="icon-btn"
                        type="button"
                        title="Desactivar"
                        onClick={() => handleDeactivate(member)}
                      >
                        🚫
                      </button>
                    )}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {profiling && (
        <MemberProfileModal
          member={profiling}
          onClose={() => setProfiling(null)}
        />
      )}

      {viewing && (
        <CreditHistoryModal
          member={viewing}
          currency="CO"
          onClose={() => setViewing(null)}
          onChanged={reload}
        />
      )}

      {(creating || editing) && (
        <MemberFormModal
          member={editing}
          defaultRole="organizer"
          onClose={() => {
            setCreating(false)
            setEditing(null)
          }}
          onSaved={reload}
        />
      )}
    </>
  )
}
