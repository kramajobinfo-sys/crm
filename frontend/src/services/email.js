import http from './http';

const base = '/emails';

export default {
  list:    (params = {}) => http.get(base, { params }),
  stats:   ()           => http.get(`${base}/stats`),
  meta:    ()           => http.get(`${base}/meta`),
  show:    (id)         => http.get(`${base}/${id}`),
  compose: (payload)    => http.post(base, payload),
  update:  (id, p)      => http.put(`${base}/${id}`, p),
  send:    (id)         => http.post(`${base}/${id}/send`),
  remove:  (id)         => http.delete(`${base}/${id}`),

  accounts:       ()        => http.get('/email-accounts'),
  account:        (id)      => http.get(`/email-accounts/${id}`),
  createAccount:  (payload) => http.post('/email-accounts', payload),
  updateAccount:  (id, p)   => http.put(`/email-accounts/${id}`, p),
  testAccount:    (id, to)  => http.post(`/email-accounts/${id}/test`, to ? { to } : {}),
  fetchAccount:   (id)      => http.post(`/email-accounts/${id}/fetch`),
  createTemplate: (payload) => http.post('/email-templates', payload),
  updateTemplate: (id, p)   => http.put(`/email-templates/${id}`, p),
  removeTemplate: (id)      => http.delete(`/email-templates/${id}`),
};
