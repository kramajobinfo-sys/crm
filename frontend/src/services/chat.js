import http from './http';

export default {
  counts:          ()            => http.get('/chat/conversations/counts'),
  channels:        ()            => http.get('/chat/channels'),
  cannedResponses: ()            => http.get('/chat/canned-responses'),
  conversations:   (params = {}) => http.get('/chat/conversations', { params }),
  thread:          (id)          => http.get(`/chat/conversations/${id}`),
  assign:          (id, userId)  => http.post(`/chat/conversations/${id}/assign`, { user_id: userId }),
  setStatus:       (id, status)  => http.post(`/chat/conversations/${id}/status`, { status }),
  crmContext:      (id, params = {}) => http.get(`/chat/conversations/${id}/crm-context`, { params }),
  linkCrm:         (id, type, recordId) => http.post(`/chat/conversations/${id}/crm-link`, { type, id: recordId }),
  unlinkCrm:       (id) => http.delete(`/chat/conversations/${id}/crm-link`),
  createLead:      (id, payload) => http.post(`/chat/conversations/${id}/create-lead`, payload),

  /** Multipart when files are attached, so a caption-less image still posts. */
  reply(id, body, direction = 'outbound', files = []) {
    if (!files.length) {
      return http.post(`/chat/conversations/${id}/reply`, { body, direction });
    }
    const form = new FormData();
    if (body) form.append('body', body);
    form.append('direction', direction);
    files.forEach((f) => form.append('attachments[]', f));
    return http.post(`/chat/conversations/${id}/reply`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,   // uploads need far longer than the 30s default
    });
  },
};
