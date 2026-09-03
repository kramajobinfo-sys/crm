import http from './http';

// Module 8 — Purchase: vendors, requests, orders, approvals.
export default {
  poStats: () => http.get('/purchase-orders/stats'),

  vendors:      (params = {}) => http.get('/vendors', { params }),
  createVendor: (payload)    => http.post('/vendors', payload),
  updateVendor: (id, p)      => http.put(`/vendors/${id}`, p),
  removeVendor: (id)         => http.delete(`/vendors/${id}`),

  requests:      (params = {}) => http.get('/purchase-requests', { params }),
  request:       (id)         => http.get(`/purchase-requests/${id}`),
  createRequest: (payload)    => http.post('/purchase-requests', payload),
  updateRequest: (id, p)      => http.put(`/purchase-requests/${id}`, p),
  removeRequest: (id)         => http.delete(`/purchase-requests/${id}`),
  submitRequest: (id)         => http.post(`/purchase-requests/${id}/submit`),
  convertRequest:(id, vendorId) => http.post(`/purchase-requests/${id}/convert`, { vendor_id: vendorId }),

  orders:       (params = {}) => http.get('/purchase-orders', { params }),
  order:        (id)         => http.get(`/purchase-orders/${id}`),
  createOrder:  (payload)    => http.post('/purchase-orders', payload),
  updateOrder:  (id, p)      => http.put(`/purchase-orders/${id}`, p),
  removeOrder:  (id)         => http.delete(`/purchase-orders/${id}`),
  confirmOrder: (id)         => http.post(`/purchase-orders/${id}/confirm`),
  receiveOrder: (id, lines)  => http.post(`/purchase-orders/${id}/receive`, lines ? { lines } : {}),
  closeOrder:   (id)         => http.post(`/purchase-orders/${id}/close`),
  cancelOrder:  (id)         => http.post(`/purchase-orders/${id}/cancel`),

  myApprovals: ()            => http.get('/approvals/mine'),
  actApproval: (id, action, comment) => http.post(`/approvals/${id}/act`, { action, comment }),
  workflows:   ()            => http.get('/approvals/workflows'),
};
