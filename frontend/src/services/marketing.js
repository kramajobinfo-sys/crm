import http from './http';

const base = '/campaigns';

export default {
  list:    (params = {})  => http.get(base, { params }),
  stats:   ()            => http.get(`${base}/stats`),
  meta:    ()            => http.get(`${base}/meta`),
  show:    (id)          => http.get(`${base}/${id}`),
  create:  (payload)     => http.post(base, payload),
  update:  (id, payload) => http.put(`${base}/${id}`, payload),
  remove:  (id)          => http.delete(`${base}/${id}`),
  preview: (payload)     => http.post(`${base}/preview-audience`, payload),
  recipients: (id)       => http.get(`${base}/${id}/recipients`),
  launch:  (id)          => http.post(`${base}/${id}/launch`),
  createSmsProvider: (payload) => http.post('/sms-providers', payload),
};
