import http from './http';

// Zoho gap #9 — Documents library (private disk, authenticated streaming download).
export default {
  list:   (params = {}) => http.get('/documents', { params }),
  meta:   ()           => http.get('/documents/meta'),
  get:    (id)         => http.get(`/documents/${id}`),
  update: (id, p)      => http.put(`/documents/${id}`, p),
  remove: (id)         => http.delete(`/documents/${id}`),

  upload(file, fields = {}) {
    const form = new FormData();
    form.append('file', file);
    Object.entries(fields).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') form.append(k, v); });
    return http.post('/documents', form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 120000 });
  },
  replaceFile(id, file) {
    const form = new FormData();
    form.append('file', file);
    return http.post(`/documents/${id}/file`, form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 120000 });
  },

  // Folders
  createFolder: (name) => http.post('/documents/folders', { name }),
  updateFolder: (id, name) => http.put(`/documents/folders/${id}`, { name }),
  removeFolder: (id) => http.delete(`/documents/folders/${id}`),

  // Authenticated download: fetch as a blob (the endpoint needs the Bearer header, so a plain
  // <a href> can't be used), then trigger a browser save via a temporary object URL.
  async download(id, filename) {
    const res = await http.get(`/documents/${id}/download`, { responseType: 'blob', timeout: 120000 });
    const url = window.URL.createObjectURL(res.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'document';
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  },
};
