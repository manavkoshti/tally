import api from './axios'

export const reportsApi = {
  dashboard: (params) => api.get('/dashboard', { params }),
  sales: (params) => api.get('/reports/sales', { params }),
  purchase: (params) => api.get('/reports/purchase', { params }),
  gst: (params) => api.get('/reports/gst', { params }),
  tallyFailed: (params) => api.get('/reports/tally-failed', { params }),
  audit: (params) => api.get('/reports/audit', { params }),
}
