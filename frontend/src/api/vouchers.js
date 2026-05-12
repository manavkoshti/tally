import api from './axios'

export const vouchersApi = {
  list: (params) => api.get('/vouchers', { params }),
  get: (id) => api.get(`/vouchers/${id}`),
  syncToTally: (id) => api.post(`/vouchers/${id}/sync-tally`),
  bulkSync: (ids) => api.post('/vouchers/bulk-sync', { ids }),
  downloadXml: (id) => api.get(`/vouchers/${id}/download-xml`, { responseType: 'blob' }),
}
