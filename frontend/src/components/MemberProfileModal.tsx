import { useEffect, useState } from 'react'
import { membersApi } from '../api/members'
import { redemptionsApi } from '../api/rewards'
import { formatDate, formatDateTime } from '../lib/format'
import type { Member, MemberResult, Redemption } from '../types'
import { Avatar } from './ui/Avatar'
import { Medal } from './ui/Medal'
import { Modal } from './ui/Modal'
import { RankBadge } from './ui/RankBadge'
import { RewardImage } from './ui/RewardImage'

interface Props {
  member: Member
  onClose: () => void
}

export function MemberProfileModal({ member, onClose }: Props) {
  const [redemptions, setRedemptions] = useState<Redemption[]>([])
  const [results, setResults] = useState<MemberResult[]>([])
  const [loading, setLoading] = useState(true)
  const [loadingResults, setLoadingResults] = useState(true)

  useEffect(() => {
    redemptionsApi
      .forMember(member.id)
      .then((result) => setRedemptions(result.data))
      .catch(() => setRedemptions([]))
      .finally(() => setLoading(false))
  }, [member.id])

  useEffect(() => {
    membersApi
      .results(member.id)
      .then((result) => setResults(result.data))
      .catch(() => setResults([]))
      .finally(() => setLoadingResults(false))
  }, [member.id])

  // Los cancelados no cuentan como premios obtenidos.
  const obtained = redemptions.filter((r) => r.status !== 'cancelado')

  return (
    <Modal title={`Perfil de ${member.nick}`} onClose={onClose} width={540}>
      <div className="profile-head">
        <Avatar member={member} size={72} />
        <div className="profile-head__info">
          <div className="profile-head__nick">{member.nick}</div>
          <div className="profile-head__ranks">
            {member.is_player && <RankBadge rank={member.rank} />}
            {member.is_organizer && <RankBadge rank={member.organizer_rank} />}
          </div>
        </div>
      </div>

      <div className="profile-stats">
        {member.is_player && (
          <>
            <div className="profile-stat">
              <span className="stat__label">Saldo CE</span>
              <span className="stat__value balance--ce">{member.ce_balance}</span>
            </div>
            <div className="profile-stat">
              <span className="stat__label">Podios</span>
              <span className="stat__value podium-counts">
                <Medal position={1} size={18} /> {member.top1 ?? 0}
                <Medal position={2} size={18} /> {member.top2 ?? 0}
                <Medal position={3} size={18} /> {member.top3 ?? 0}
              </span>
            </div>
          </>
        )}
        {member.is_organizer && (
          <>
            <div className="profile-stat">
              <span className="stat__label">Saldo CO</span>
              <span className="stat__value balance--co">{member.co_balance}</span>
            </div>
            <div className="profile-stat">
              <span className="stat__label">Eventos organizados</span>
              <span className="stat__value">{member.events_organized ?? 0}</span>
            </div>
          </>
        )}
      </div>

      {member.is_player && (
        <>
          <h3 className="section-title section-title--sm">
            Eventos ganados {results.length > 0 && `(${results.length})`}
          </h3>

          {loadingResults && <p className="panel__hint">Cargando...</p>}

          {!loadingResults && results.length === 0 && (
            <p className="panel__hint">Todavía no ha ganado ningún evento.</p>
          )}

          <div className="prize-list">
            {results.map((result) => (
              <div className="prize-item" key={result.event.id}>
                {result.position <= 3 ? (
                  <Medal position={result.position} size={34} />
                ) : (
                  <span className="position-chip">#{result.position}</span>
                )}

                <div className="prize-item__body">
                  <div className="prize-item__name">{result.event.name}</div>
                  <div className="prize-item__meta">
                    {formatDate(result.event.held_at)} · {result.event.type}
                  </div>
                </div>

                <div className="prize-item__right">
                  <span className="balance balance--ce">+{result.ce_awarded} CE</span>
                  <span className="prize-item__meta">Puesto {result.position}</span>
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      <h3 className="section-title section-title--sm">
        Premios canjeados {obtained.length > 0 && `(${obtained.length})`}
      </h3>

      {loading && <p className="panel__hint">Cargando...</p>}

      {!loading && obtained.length === 0 && (
        <p className="panel__hint">Todavía no ha canjeado ningún premio.</p>
      )}

      <div className="prize-list">
        {obtained.map((redemption) => (
          <div className="prize-item" key={redemption.id}>
            {redemption.reward ? (
              <RewardImage
                rewardId={redemption.reward.id}
                category={redemption.reward.category}
                hasImage={redemption.reward.has_image}
                version={redemption.reward.image_version}
                name={redemption.reward_name}
                size={48}
              />
            ) : (
              // El premio se borró del catalogo, pero el canje sigue siendo real.
              <span className="reward-img" style={{ width: 48, height: 48 }}>
                🎁
              </span>
            )}

            <div className="prize-item__body">
              <div className="prize-item__name">{redemption.reward_name}</div>
              <div className="prize-item__meta">
                {formatDateTime(redemption.created_at)}
                {redemption.note && ` · ${redemption.note}`}
              </div>
            </div>

            <div className="prize-item__right">
              <span className={`balance balance--${redemption.currency.toLowerCase()}`}>
                {redemption.cost_paid} {redemption.currency}
              </span>
              <span className={`status status--${redemption.status}`}>
                {redemption.status}
              </span>
            </div>
          </div>
        ))}
      </div>
    </Modal>
  )
}
