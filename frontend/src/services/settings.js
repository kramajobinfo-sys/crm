import http from './http';

// Settings admin surface: organization, users, roles.
export default {
  company:       ()        => http.get('/settings/company'),
  updateCompany: (payload) => http.put('/settings/company', payload),

  branches:       ()        => http.get('/settings/branches'),
  createBranch:   (p)       => http.post('/settings/branches', p),
  updateBranch:   (id, p)   => http.put(`/settings/branches/${id}`, p),
  removeBranch:   (id)      => http.delete(`/settings/branches/${id}`),

  departments:      ()      => http.get('/settings/departments'),
  createDepartment: (p)     => http.post('/settings/departments', p),
  updateDepartment: (id, p) => http.put(`/settings/departments/${id}`, p),
  removeDepartment: (id)    => http.delete(`/settings/departments/${id}`),

  users:      (params = {}) => http.get('/users', { params }),
  usersMeta:  ()           => http.get('/users/meta'),
  createUser: (p)          => http.post('/users', p),
  updateUser: (id, p)      => http.put(`/users/${id}`, p),
  removeUser: (id)         => http.delete(`/users/${id}`),

  roles:       ()          => http.get('/roles'),
  role:        (id)        => http.get(`/roles/${id}`),
  permissions: ()          => http.get('/roles/permissions'),
  createRole:  (p)         => http.post('/roles', p),
  updateRole:  (id, p)     => http.put(`/roles/${id}`, p),
  removeRole:  (id)        => http.delete(`/roles/${id}`),
  cloneRole:   (id, p)     => http.post(`/roles/${id}/clone`, p),

  apiKeys:       (params = {}) => http.get('/api-keys', { params }),
  createApiKey:  (p)           => http.post('/api-keys', p),
  updateApiKey:  (id, p)       => http.put(`/api-keys/${id}`, p),
  removeApiKey:  (id)          => http.delete(`/api-keys/${id}`),

  webhooks:       (params = {}) => http.get('/webhook-endpoints', { params }),
  webhook:        (id)          => http.get(`/webhook-endpoints/${id}`),
  createWebhook:  (p)           => http.post('/webhook-endpoints', p),
  updateWebhook:  (id, p)       => http.put(`/webhook-endpoints/${id}`, p),
  removeWebhook:  (id)          => http.delete(`/webhook-endpoints/${id}`),

  routingRules:   ()      => http.get('/tickets/routing-rules'),
  createRouting:  (p)     => http.post('/tickets/routing-rules', p),
  updateRouting:  (id, p) => http.put(`/tickets/routing-rules/${id}`, p),
  removeRouting:  (id)    => http.delete(`/tickets/routing-rules/${id}`),
};
