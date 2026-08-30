import { useState } from 'react'
import { avatarUrl } from '../../api/client'
import type { Member } from '../../types'

interface Props {
  member: Pick<Member, 'id' | 'nick' | 'has_avatar' | 'avatar_version'>
  size?: number
}

/** Avatar del miembro, con inicial como respaldo si no hay PNG o si falla. */
export function Avatar({ member, size = 36 }: Props) {
  const [failed, setFailed] = useState(false)
  const showImage = member.has_avatar && !failed

  return (
    <span
      className="avatar"
      style={{ width: size, height: size, fontSize: size * 0.42 }}
      title={member.nick}
    >
      {showImage ? (
        <img
          src={avatarUrl(member.id, member.avatar_version)}
          alt={member.nick}
          onError={() => setFailed(true)}
        />
      ) : (
        member.nick.charAt(0).toUpperCase()
      )}
    </span>
  )
}
