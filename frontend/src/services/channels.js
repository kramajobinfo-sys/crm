import http from './http';

const base = '/chat/settings/channels';

export default {
  list:   ()          => http.get(base),
  meta:   ()          => http.get(`${base}/meta`),
  show:   (id)        => http.get(`${base}/${id}`),
  create: (payload)   => http.post(base, payload),
  update: (id, p)     => http.put(`${base}/${id}`, p),
  test:   (id)        => http.post(`${base}/${id}/test`),
  remove: (id)        => http.delete(`${base}/${id}`),
};
