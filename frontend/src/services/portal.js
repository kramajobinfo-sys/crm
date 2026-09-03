import portalHttp from './portalHttp';

export default {
  login: (payload) => portalHttp.post('/portal/login', payload),
  logout: () => portalHttp.post('/portal/logout'),
  me: () => portalHttp.get('/portal/me'),
  invoices: (params) => portalHttp.get('/portal/invoices', { params }),
  invoice: (id) => portalHttp.get(`/portal/invoices/${id}`),
  tickets: (params) => portalHttp.get('/portal/tickets', { params }),
  ticket: (id) => portalHttp.get(`/portal/tickets/${id}`),
  createTicket: (payload) => portalHttp.post('/portal/tickets', payload),
  reply: (id, body) => portalHttp.post(`/portal/tickets/${id}/replies`, { body }),
  quotations: (params) => portalHttp.get('/portal/quotations', { params }),
  quotation: (id) => portalHttp.get(`/portal/quotations/${id}`),
  signQuotation: (id, payload) => portalHttp.post(`/portal/quotations/${id}/sign`, payload),
  kbArticles: (params) => portalHttp.get('/portal/kb/articles', { params }),
  kbArticle: (id) => portalHttp.get(`/portal/kb/articles/${id}`),
};
