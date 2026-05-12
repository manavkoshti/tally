import api from './axios'

export const ledgersApi = {
  list: (params) => api.get('/ledgers', { params }),
  get: (id) => api.get(`/ledgers/${id}`),
  create: (data) => api.post('/ledgers', data),
  update: (id, data) => api.put(`/ledgers/${id}`, data),
  delete: (id) => api.delete(`/ledgers/${id}`),
  syncToTally: (id) => api.post(`/ledgers/${id}/sync-tally`),
}
