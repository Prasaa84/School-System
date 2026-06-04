export interface StudentProfileDetail {
  std_id: number
  is_active?: boolean
  census_id: string
  school_name?: string
  index_no: string
  full_name: string
  name_with_initials: string
  address1: string
  address2: string
  phone_no: string
  whatsapp_no: string
  phone_home: string
  email: string
  dob: string | null
  d_o_admission: string | null
  gender_id: number
  gender_label?: string
  ethnic_group_label?: string
  religion_label?: string
  grade_class?: string
  year?: number | null
  father_name: string
  father_job: string
  father_mobile: string
  mother_name: string
  mother_job: string
  mother_mobile: string
  guardian_name: string
  guardian_job: string
  guardian_mobile: string
  photo_url?: string
  login_username?: string
  login_is_enabled?: boolean | null
}
