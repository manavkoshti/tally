import api from './axios'

export const tallyApi = {
  testConnection: () => api.get('/tally/test-connection'),
}
