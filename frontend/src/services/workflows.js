import http from './http';

const base = '/workflows';

export default {
  list:   (params = {})  => http.get(base, { params }),
  stats:  ()            => http.get(`${base}/stats`),
  meta:   ()            => http.get(`${base}/meta`),
  show:   (id)          => http.get(`${base}/${id}`),
  create: (payload)     => http.post(base, payload),
  update: (id, p)       => http.put(`${base}/${id}`, p),
  remove: (id)          => http.delete(`${base}/${id}`),
  run:    (id, subjectId) => http.post(`${base}/${id}/run`, subjectId != null ? { subject_id: subjectId } : {}),
};
