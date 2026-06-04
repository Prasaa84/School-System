export interface PaymentStudent {
  std_id: number
  index_no: string
  fullname: string
  name_with_initials: string
  census_id: string
  school_name: string
}

export interface PaymentRow {
  id: number
  invoice_no: string
  year: number
  include_member_fee: boolean
  total: number
  paid_date: string
  annual_fee: number
  member_fee: number
}

export interface PaymentStudentResponse {
  student?: PaymentStudent | null
  payments?: PaymentRow[]
}
