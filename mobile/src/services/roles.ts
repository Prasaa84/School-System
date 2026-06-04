import type { AuthUser } from '../types/auth'

const normalizeRoleName = (user: AuthUser | null): string =>
  String(user?.role_name ?? '').trim().toLowerCase()

export const isStudentRole = (user: AuthUser | null): boolean => {
  return (user?.role_id ?? 0) === 7 || normalizeRoleName(user) === 'student'
}

export const isClassTeacherRole = (user: AuthUser | null): boolean => {
  return ['class teacher', 'class_teacher', 'classteacher'].includes(normalizeRoleName(user))
}

export const isMobileEnabledRole = (user: AuthUser | null): boolean => {
  return isStudentRole(user) || isClassTeacherRole(user)
}

export const resolveHomeRoute = (user: AuthUser | null): string => {
  if (isStudentRole(user)) return '/student'
  if (isClassTeacherRole(user)) return '/teacher'
  return '/future-role'
}

export const resolveRoleLabel = (user: AuthUser | null): string => {
  if (!user) return 'Unknown role'
  return user.role_name?.trim() || `Role ${user.role_id ?? '-'}`
}
