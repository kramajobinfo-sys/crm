import http from './http';

const base = '/deals';

export default {
  list:     (params = {})   => http.get(base, { params }),
  board:    (params = {})   => http.get(`${base}/board`, { params }),
  stats:    ()             => http.get(`${base}/stats`),
  meta:     ()             => http.get(`${base}/meta`),
  show:     (id)           => http.get(`${base}/${id}`),
  create:   (payload)      => http.post(base, payload),
  update:   (id, payload)  => http.put(`${base}/${id}`, payload),
  remove:   (id)           => http.delete(`${base}/${id}`),
  move:     (id, stageId)  => http.post(`${base}/${id}/move`, { stage_id: stageId }),
  markLost: (id, payload)  => http.post(`${base}/${id}/lost`, payload),
  addNote:  (id, body, type) => http.post(`${base}/${id}/notes`, { body, type }),

  upload(id, file) {
    const form = new FormData();
    form.append('file', file);
    return http.post(`${base}/${id}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    });
  },
  removeAttachment: (id, attId) => http.delete(`${base}/${id}/attachments/${attId}`),

  updateStageBlueprint: (pipelineId, stageId, payload) => http.put(`/pipelines/${pipelineId}/stages/${stageId}`, payload),
};
