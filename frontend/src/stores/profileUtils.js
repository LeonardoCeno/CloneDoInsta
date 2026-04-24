const AUTHOR_PALETTES = [
  ['#f05a28', '#ff9f59'],
  ['#3bb4c1', '#1f7a8c'],
  ['#7c6ee6', '#b4a6ff'],
  ['#ff6f61', '#ffb88c'],
  ['#35a66f', '#8fd694'],
  ['#2f6fed', '#7cb8ff'],
]

export function hashString(value) {
  let hash = 0

  for (const character of String(value ?? '')) {
    hash = (hash * 31 + character.charCodeAt(0)) >>> 0
  }

  return hash
}

export function getProfileColors(seed) {
  return AUTHOR_PALETTES[hashString(seed) % AUTHOR_PALETTES.length]
}

export function normalizeUser(user) {
  if (!user || typeof user !== 'object') {
    return null
  }

  const seed = user.username || user.email || String(user.id ?? '')

  return {
    id: user.id,
    name: user.name ?? '',
    username: user.username ?? '',
    email: user.email ?? '',
    bio: user.bio ?? '',
    avatarUrl: user.avatar_url ?? user.avatarUrl ?? '',
    colors: getProfileColors(seed),
    createdAt: user.created_at ?? user.createdAt ?? null,
  }
}
