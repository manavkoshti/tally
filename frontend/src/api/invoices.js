import api from './axios'

export const invoicesApi = {
  list: (params) => api.get('/invoices', { params }),
  get: (id) => api.get(`/invoices/${id}`),
  create: (data) => api.post('/invoices', data, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  delete: (id) => api.delete(`/invoices/${id}`),
  processAccounting: (id) => api.post(`/invoices/${id}/process-accounting`),
}
