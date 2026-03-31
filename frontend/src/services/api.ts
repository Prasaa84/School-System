import axios from 'axios'
import { clearAuthSession, getSchoolContextCensusId, getToken } from './auth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  timeout: 10000,
})

api.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  const explicitSchoolHeader = (config.headers as Record<string, unknown> | undefined)?.['X-School-Census-Id']
  const schoolContextCensusId = getSchoolContextCensusId()
  if (explicitSchoolHeader === undefined && schoolContextCensusId !== null) {
    config.headers['X-School-Census-Id'] = String(schoolContextCensusId)
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error?.response?.status === 401) {
      clearAuthSession()

      if (window.location.pathname !== '/login') {
        await window.location.assign('/login')
      }
    }

    return Promise.reject(error)
  },
)

export default api


