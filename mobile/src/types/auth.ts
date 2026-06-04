export interface ClassTeacherAssignmentStatus {
  is_assigned: boolean
  year: number
  message: string
  grade_id?: number
  grade?: string
  class_id?: number
  class?: string
  grade_class?: string
}

export interface AuthUser {
  user_id: number
  username: string
  role_id: number | null
  role_name: string | null
  school_census_id?: string | null
  class_teacher_assignment_status?: ClassTeacherAssignmentStatus | null
  class_teacher_assignment_message?: string | null
  feature_permissions?: Record<string, boolean> | null
}

export interface LoginResponse {
  access_token: string
  token_type: string
  user: AuthUser
}
