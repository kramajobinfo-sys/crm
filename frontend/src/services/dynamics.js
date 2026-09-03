import http from './http';

const base = '/integrations/dynamics';

export default {
  index:         ()                 => http.get(base),
  create:        (payload)          => http.post(base, payload),
  update:        (id, payload)      => http.put(`${base}/${id}`, payload),
  remove:        (id)               => http.delete(`${base}/${id}`),
  // Reaches Microsoft Entra ID for real; allow longer than the 30s default.
  test:          (id)               => http.post(`${base}/${id}/test`, {}, { timeout: 60000 }),
  runs:          (id, params = {})  => http.get(`${base}/${id}/runs`, { params }),
  run:           (id, runId)        => http.get(`${base}/${id}/runs/${runId}`),
  addMapping:    (id, payload)      => http.post(`${base}/${id}/mappings`, payload),
  updateMapping: (id, mId, payload) => http.put(`${base}/${id}/mappings/${mId}`, payload),
  removeMapping: (id, mId)          => http.delete(`${base}/${id}/mappings/${mId}`),
  sync:          (id, mId)          => http.post(`${base}/${id}/mappings/${mId}/sync`),
};
