import http from './http';

// Platform console: cross-tenant company management (platform admins only).
export default {
  companies:    (params = {}) => http.get('/platform/companies', { params }),
  company:      (id)          => http.get(`/platform/companies/${id}`),
  updatePlan:   (id, p)       => http.put(`/platform/companies/${id}/plan`, p),
  grantAccess:  (id, p)       => http.post(`/platform/companies/${id}/access-grants`, p),
  grants:       (params = {}) => http.get('/platform/access-grants', { params }),
  revokeGrant:  (id)          => http.post(`/platform/access-grants/${id}/revoke`),
};
