import http from './http';

const base = '/leads';

export default {
  list:      (params = {})    => http.get(base, { params }),
  stats:     ()              => http.get(`${base}/stats`),
  meta:      ()              => http.get(`${base}/meta`),
  show:      (id)            => http.get(`${base}/${id}`),
  create:    (payload)       => http.post(base, payload),
  update:    (id, payload)   => http.put(`${base}/${id}`, payload),
  remove:    (id)            => http.delete(`${base}/${id}`),
  score:     (id)            => http.get(`${base}/${id}/score`),
  addNote:   (id, body, type)=> http.post(`${base}/${id}/notes`, { body, type }),
  // Omit user_id entirely to let the assignment rules decide.
  assign:    (id, userId)    => http.post(`${base}/${id}/assign`, userId === undefined ? {} : { user_id: userId }),
  convert:   (id, overrides = {}) => http.post(`${base}/${id}/convert`, overrides),

  upload(id, file) {
    const form = new FormData();
    form.append('file', file);
    return http.post(`${base}/${id}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    });
  },
  removeAttachment: (id, attId) => http.delete(`${base}/${id}/attachments/${attId}`),
};
