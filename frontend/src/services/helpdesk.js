import http from './http';

const base = '/tickets';

export default {
  list:     (params = {})  => http.get(base, { params }),
  stats:    ()            => http.get(`${base}/stats`),
  meta:     ()            => http.get(`${base}/meta`),
  show:     (id)          => http.get(`${base}/${id}`),
  create:   (payload)     => http.post(base, payload),
  update:   (id, payload) => http.put(`${base}/${id}`, payload),
  remove:   (id)          => http.delete(`${base}/${id}`),
  reply:    (id, body, internal) => http.post(`${base}/${id}/replies`, { body, internal }),
  assign:   (id, userId)  => http.post(`${base}/${id}/assign`, { user_id: userId }),
  setStatus:(id, status)  => http.post(`${base}/${id}/status`, { status }),
  escalate: (id, payload) => http.post(`${base}/${id}/escalate`, payload),
};
