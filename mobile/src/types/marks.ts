export interface TermOption {
  id: number
  label: string
}

export interface GradeOption {
  grade_id: number
  label: string
}

export interface ClassOption {
  class_id: number
  label: string
}

export interface SubjectRow {
  subject_id: number
  subject: string
  order_id: number
  sub_cat_id?: number
}

export interface MarkRow {
  std_id: number
  index_no: string
  name_with_initials: string
  marks: Record<string, string>
  total: number | null
  average: number | null
  position?: number | null
}

export interface ScopeResponse {
  role?: string
  locked_year?: number | null
  locked_grade_id?: number | null
  locked_class_id?: number | null
}

export interface MarksOptionsResponse {
  years?: number[]
  terms?: TermOption[]
  grades?: GradeOption[]
  classes?: ClassOption[]
  can_manage?: boolean
  scope?: ScopeResponse | null
  message?: string
}

export interface MarksResponse {
  class?: {
    label?: string
  }
  subjects?: SubjectRow[]
  students?: MarkRow[]
  can_manage?: boolean
  can_confirm?: boolean
  confirmation?: {
    is_completed?: boolean
  }
  summary?: {
    total_students?: number
    total_subjects?: number
  }
}
