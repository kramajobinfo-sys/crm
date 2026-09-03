import http from './http';

const base = '/customer-credits';

// Customer credits (credit notes). Overpayments and reduced invoices create these on the
// server; only `create` here issues one by hand.
export default {
  list:  (params = {}) => http.get(base, { params }),
  stats: ()            => http.get(`${base}/stats`),
  meta:  ()            => http.get(`${base}/meta`),
  get:   (id)          => http.get(`${base}/${id}`),
  create:(payload)     => http.post(base, payload),
  void:  (id, reason)  => http.post(`${base}/${id}/void`, { reason }),

  // Invoice-side: which credits can be applied here, and applying one.
  availableForInvoice: (invoiceId) => http.get(`/invoices/${invoiceId}/available-credits`),
  applyToInvoice: (invoiceId, creditId, amount) =>
    http.post(`/invoices/${invoiceId}/apply-credit`, { credit_id: creditId, amount: amount || undefined }),
};
