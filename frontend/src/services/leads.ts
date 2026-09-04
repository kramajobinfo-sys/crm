import http from './http';
import type { ApiResponse, Id, Lead, Attachment } from '@/types/api';

const base = '/leads';

// Reference TypeScript service. Mirrors the JS services exactly, but the API
// responses are now typed — hover `.data.data` in a component to see `Lead`.
export default {
  list:    (params: Record<string, unknown> = {}) => http.get<ApiResponse<Lead[]>>(base, { params }),
  stats:   () => http.get(`${base}/stats`),
  meta:    () => http.get(`${base}/meta`),
  show:    (id: Id) => http.get<ApiResponse<Lead>>(`${base}/${id}`),
  create:  (payload: Partial<Lead>) => http.post<ApiResponse<Lead>>(base, payload),
  update:  (id: Id, payload: Partial<Lead>) => http.put<ApiResponse<Lead>>(`${base}/${id}`, payload),
  remove:  (id: Id) => http.delete(`${base}/${id}`),
  score:   (id: Id) => http.get(`${base}/${id}/score`),
  addNote: (id: Id, body: string, type?: string) => http.post(`${base}/${id}/notes`, { body, type }),
  // Omit user_id entirely to let the assignment rules decide.
  assign:  (id: Id, userId?: Id) =>
    http.post(`${base}/${id}/assign`, userId === undefined ? {} : { user_id: userId }),
  convert: (id: Id, overrides: Record<string, unknown> = {}) => http.post(`${base}/${id}/convert`, overrides),

  upload(id: Id, file: File) {
    const form = new FormData();
    form.append('file', file);
    return http.post<ApiResponse<{ attachments: Attachment[] }>>(`${base}/${id}/attachments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    });
  },
  removeAttachment: (id: Id, attId: Id) => http.delete(`${base}/${id}/attachments/${attId}`),
};
