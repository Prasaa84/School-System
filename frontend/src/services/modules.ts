import api from './api'

export interface ModuleCatalogItem {
  key: string
  label: string
  path?: string | null
}

interface ModuleCatalogResponse {
  modules?: ModuleCatalogItem[]
}

const fallbackModules: ModuleCatalogItem[] = [
  { key: 'students', label: 'Students', path: '/students' },
  { key: 'grades', label: 'Grades' },
  { key: 'classes', label: 'Classes' },
  { key: 'staff', label: 'Teachers' },
  { key: 'marks', label: 'Marks', path: '/module/marks' },
  { key: 'payments', label: 'SDS Payments' },
  { key: 'subjects', label: 'Subjects' },
  { key: 'reports', label: 'Reports' },
]

const keyAliases: Record<string, string> = {
  grade: 'grades',
  grades: 'grades',
  class: 'classes',
  classes: 'classes',
  teacher: 'staff',
  teachers: 'staff',
  staff: 'staff',
  marks: 'marks',
  mark: 'marks',
  payments: 'payments',
  payment: 'payments',
  subjects: 'subjects',
  subject: 'subjects',
  reports: 'reports',
  report: 'reports',
  student: 'students',
  students: 'students',
}

const requiredModules: ModuleCatalogItem[] = [
  { key: 'grades', label: 'Grades' },
  { key: 'classes', label: 'Classes' },
  { key: 'staff', label: 'Staff' },
  { key: 'marks', label: 'Marks', path: '/module/marks' },
]

const routeSafePath = (key: string, rawPath: string | null): string | null => {
  if (rawPath && (rawPath === '/students' || rawPath.startsWith('/module/'))) {
    return rawPath
  }

  if (key === 'students') {
    return '/students'
  }

  return `/module/${key}`
}

const normalizeKey = (rawKey: string, rawLabel: string): string => {
  const keyCandidate = rawKey.trim().toLowerCase()
  if (keyAliases[keyCandidate]) {
    return keyAliases[keyCandidate]
  }

  const labelCandidate = rawLabel.trim().toLowerCase()
  if (keyAliases[labelCandidate]) {
    return keyAliases[labelCandidate]
  }

  return keyCandidate
}

const dedupeByKey = (items: ModuleCatalogItem[]): ModuleCatalogItem[] => {
  const map = new Map<string, ModuleCatalogItem>()

  for (const item of items) {
    if (!item.key || !item.label) continue

    if (!map.has(item.key)) {
      map.set(item.key, item)
      continue
    }

    const existing = map.get(item.key)!
    if (!existing.path && item.path) {
      map.set(item.key, item)
    }
  }

  return Array.from(map.values())
}

const normalizeModules = (items: ModuleCatalogItem[]): ModuleCatalogItem[] => {
  const normalized: ModuleCatalogItem[] = []

  for (const item of items) {
    const rawKey = String(item?.key ?? '')
    const rawLabel = String(item?.label ?? '')
    const rawPath = typeof item?.path === 'string' ? item.path.trim() : null

    const key = normalizeKey(rawKey, rawLabel)
    const label = rawLabel.trim()

    if (!key || !label) {
      continue
    }

    normalized.push({
      key,
      label,
      path: routeSafePath(key, rawPath),
    })
  }

  return dedupeByKey([...normalized, ...requiredModules])
}

export const resolveModulePath = (module: ModuleCatalogItem): string => {
  return routeSafePath(module.key, module.path ?? null) ?? '/'
}

export const loadModuleCatalog = async (): Promise<ModuleCatalogItem[]> => {
  try {
    const { data } = await api.get<ModuleCatalogResponse>('/modules')
    const modules = Array.isArray(data.modules) ? data.modules : []

    const normalized = normalizeModules(modules)
    if (normalized.length > 0) {
      return normalized
    }
  } catch {
    // Silent fallback keeps shell usable when module endpoint is unavailable.
  }

  return normalizeModules(fallbackModules)
}
