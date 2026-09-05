import http from './http';

const base = '/contacts';

export default {
  list:   (params = {}) => http.get(base, { params }),
  meta:   ()            => http.get(`${base}/meta`),
  show:   (id)          => http.get(`${base}/${id}`),
  create: (payload)     => http.post(base, payload),
  update: (id, payload) => http.put(`${base}/${id}`, payload),
  remove: (id)          => http.delete(`${base}/${id}`),

  consents:   (id)          => http.get(`${base}/${id}/consents`),
  setConsent: (id, payload) => http.post(`${base}/${id}/consents`, payload),

  clickToCall: (payload) => http.post('/activities/calls/dial', payload),
};
