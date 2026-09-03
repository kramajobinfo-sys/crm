import http from './http';

// Module 9 — Inventory: stock levels, movements, warehouses, transfers, barcodes.
export default {
  stats:      ()           => http.get('/inventory/stats'),
  meta:       ()           => http.get('/inventory/meta'),
  stock:      (params = {}) => http.get('/inventory/stock', { params }),
  movements:  (params = {}) => http.get('/inventory/movements', { params }),
  adjust:     (payload)    => http.post('/inventory/adjust', payload),
  move:       (payload)    => http.post('/inventory/move', payload),

  warehouses:      ()          => http.get('/warehouses'),
  createWarehouse: (payload)   => http.post('/warehouses', payload),
  updateWarehouse: (id, p)     => http.put(`/warehouses/${id}`, p),
  removeWarehouse: (id)        => http.delete(`/warehouses/${id}`),

  transfers:      (params = {}) => http.get('/inventory/transfers', { params }),
  transfer:       (id)         => http.get(`/inventory/transfers/${id}`),
  createTransfer: (payload)    => http.post('/inventory/transfers', payload),
  transferAction: (id, action) => http.post(`/inventory/transfers/${id}/action`, { action }),

  barcodes:      (productId)  => http.get(`/inventory/barcodes/${productId}`),
  createBarcode: (payload)    => http.post('/inventory/barcodes', payload),
  removeBarcode: (id)         => http.delete(`/inventory/barcodes/${id}`),
};
