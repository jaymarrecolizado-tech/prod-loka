import apiClient from './client'

// Auth API
export const authApi = {
  login: credentials => apiClient.post('/auth/login', credentials),
  logout: () => apiClient.post('/auth/logout'),
  me: () => apiClient.get('/auth/me'),
  refresh: () => apiClient.post('/auth/refresh'),
  resetPassword: email => apiClient.post('/auth/reset-password', { email }),
  changePassword: data => apiClient.post('/auth/change-password', data),
}

export default {
  auth: authApi,
}
