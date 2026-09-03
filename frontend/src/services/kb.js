import http from './http';

// Zoho gap #8 — Knowledge Base ("Solutions"), staff authoring side.
export default {
  articles:       (params = {}) => http.get('/kb/articles', { params }),
  article:        (id)          => http.get(`/kb/articles/${id}`),
  meta:           ()           => http.get('/kb/articles/meta'),
  createArticle:  (payload)    => http.post('/kb/articles', payload),
  updateArticle:  (id, p)      => http.put(`/kb/articles/${id}`, p),
  removeArticle:  (id)         => http.delete(`/kb/articles/${id}`),
  createCategory: (payload)    => http.post('/kb/categories', payload),
};
