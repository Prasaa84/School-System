import type { AuthUser } from '../types/auth'

const TOKEN_KEY = 'sds_mobile_auth_token'
const USER_KEY = 'sds_mobile_auth_user'

export const getToken = (): string | null => window.localStorage.getItem(TOKEN_KEY)

export const getUser = (): AuthUser | null => {
  const raw = window.localStorage.getItem(USER_KEY)

  if (!raw) return null

  try {
    return JSON.parse(raw) as AuthUser
  } catch {
    clearAuthSession()
    return null
  }
}

export const setAuthSession = (token: string, user: AuthUser): void => {
  window.localStorage.setItem(TOKEN_KEY, token)
  window.localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export const clearAuthSession = (): void => {
  window.localStorage.removeItem(TOKEN_KEY)
  window.localStorage.removeItem(USER_KEY)
}

export const isAuthenticated = (): boolean => {
  const token = getToken()
  return typeof token === 'string' && token.trim().length > 0
}
