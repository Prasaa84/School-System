export interface AuthUser {
  user_id: number
  username: string
  role_id: number | null
  role_name: string | null
  school_census_id?: string | null
  class_teacher_assignment_status?: {
    is_assigned: boolean
    year: number
    message: string
    grade_id?: number | null
    class_id?: number | null
  } | null
  class_teacher_assignment_message?: string | null
  feature_permissions?: Record<string, boolean> | null
}

const TOKEN_KEY = 'sds_auth_token'
const USER_KEY = 'sds_auth_user'
const SCHOOL_CONTEXT_KEY = 'sds_school_context_census_id'

export const getToken = (): string | null => {
  return window.localStorage.getItem(TOKEN_KEY)
}

export const getUser = (): AuthUser | null => {
  const raw = window.localStorage.getItem(USER_KEY)
  if (!raw) return null

  try {
    return JSON.parse(raw) as AuthUser
  } catch {
    return null
  }
}

export const getSchoolContextCensusValue = (): string | null => {
  const raw = window.localStorage.getItem(SCHOOL_CONTEXT_KEY)
  return raw && raw.trim().length > 0 ? raw : null
}

export const getSchoolContextCensusId = (): number | null => {
  const raw = getSchoolContextCensusValue()
  if (!raw) return null

  const value = Number(raw)
  if (!Number.isFinite(value) || value <= 0) {
    return null
  }

  return value
}

export const setSchoolContextCensusId = (censusId: string | number | null): void => {
  const normalized = censusId === null ? '' : String(censusId).trim()
  if (normalized === '') {
    window.localStorage.removeItem(SCHOOL_CONTEXT_KEY)
    return
  }

  window.localStorage.setItem(SCHOOL_CONTEXT_KEY, normalized)
}

export const setAuthSession = (token: string, user: AuthUser): void => {
  window.localStorage.setItem(TOKEN_KEY, token)
  window.localStorage.setItem(USER_KEY, JSON.stringify(user))
  setSchoolContextCensusId(user.school_census_id ?? null)
}

export const clearAuthSession = (): void => {
  window.localStorage.removeItem(TOKEN_KEY)
  window.localStorage.removeItem(USER_KEY)
  window.localStorage.removeItem(SCHOOL_CONTEXT_KEY)
}

export const isAuthenticated = (): boolean => {
  const token = getToken()
  return typeof token === 'string' && token.trim().length > 0
}
