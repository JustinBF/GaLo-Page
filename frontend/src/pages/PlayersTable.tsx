import { useState } from 'react'
import { membersApi, useMembers } from '../api/members'
import { CreditHistoryModal } from '../components/CreditHistoryModal'
import { MemberFormModal } from '../components/MemberFormModal'
import { MemberProfileModal } from '../components/MemberProfileModal'
import { Avatar } from '../components/ui/Avatar'
import { Medal } from '../components/ui/Medal'
import { RankBadge } from '../components/ui/RankBadge'
import { useAuth } from '../auth/AuthContext'
import { RoleGate } from '../auth/RoleGate'
import type { Member } from '../types'

export function PlayersTable() {
  const { isAdmin } = useAuth()
  const [showInactive, setShowInactive] = useState(false)
  const { members, loading, error, reload } = useMembers('players', showInactive)
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
          <h1 className="page-title">Créditos de Evento (CE)</h1>
          <p className="page-subtitle">
            Clasificacion de jugadores por saldo de CE.
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
              + Nuevo jugador
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
              <th>Jugador</th>
              <th>Rango</th>
              <th className="num"><Medal position={1} size={20} /></th>
              <th className="num"><Medal position={2} size={20} /></th>
              <th className="num"><Medal position={3} size={20} /></th>
              <th className="num">Saldo CE</th>
              {isAdmin && <th className="col-actions" />}
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={isAdmin ? 8 : 7} className="table-empty">
                  Cargando...
                </td>
              </tr>
            )}

            {!loading && members.length === 0 && (
              <tr>
                <td colSpan={isAdmin ? 8 : 7} className="table-empty">
                  Todavía no hay jugadores en la tabla.
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
                  <RankBadge rank={member.rank} />
                </td>
                <td className="num">{member.top1 ?? 0}</td>
                <td className="num">{member.top2 ?? 0}</td>
                <td className="num">{member.top3 ?? 0}</td>
                <td className="num">
                  <button
                    className="balance-btn balance balance--ce"
                    type="button"
                    title="Ver historial"
                    onClick={() => setViewing(member)}
                  >
                    {member.ce_balance}
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
          currency="CE"
          onClose={() => setViewing(null)}
          onChanged={reload}
        />
      )}

      {(creating || editing) && (
        <MemberFormModal
          member={editing}
          defaultRole="player"
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
