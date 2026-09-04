import http from './http';

const base = '/customers';

export default {
  list:          (params = {})     => http.get(base, { params }),
  stats:         ()                => http.get(`${base}/stats`),
  meta:          ()                => http.get(`${base}/meta`),
  show:          (id)              => http.get(`${base}/${id}`),
  timeline:      (id, params = {}) => http.get(`${base}/${id}/timeline`, { params }),
  create:        (payload)         => http.post(base, payload),
  update:        (id, payload)     => http.put(`${base}/${id}`, payload),
  remove:        (id)              => http.delete(`${base}/${id}`),
  addNote:       (id, body, type)  => http.post(`${base}/${id}/notes`, { body, type }),
  addContact:    (id, payload)     => http.post(`${base}/${id}/contacts`, payload),
  updateContact: (id, cId, payload)=> http.put(`${base}/${id}/contacts/${cId}`, payload),
  removeContact: (id, cId)         => http.delete(`${base}/${id}/contacts/${cId}`),
  updateContactPortal: (id, cId, payload) => http.put(`${base}/${id}/contacts/${cId}/portal`, payload),
};
