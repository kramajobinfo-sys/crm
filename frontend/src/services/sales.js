import http from './http';

// Module 7 — Sales: catalog + quote/order/invoice/payment document chain.
export default {
  // Products & catalog
  products:        (params = {}) => http.get('/products', { params }),
  productStats:    ()           => http.get('/products/stats'),
  productMeta:     ()           => http.get('/products/meta'),
  createProduct:   (payload)    => http.post('/products', payload),
  updateProduct:   (id, p)      => http.put(`/products/${id}`, p),
  removeProduct:   (id)         => http.delete(`/products/${id}`),
  createCategory:  (payload)    => http.post('/products/categories', payload),
  createTaxRate:   (payload)    => http.post('/products/tax-rates', payload),

  // Price Books (Zoho gap #7)
  priceBooks:       (params = {}) => http.get('/price-books', { params }),
  priceBook:        (id)         => http.get(`/price-books/${id}`),
  priceBookMeta:    ()           => http.get('/price-books/meta'),
  createPriceBook:  (payload)    => http.post('/price-books', payload),
  updatePriceBook:  (id, p)      => http.put(`/price-books/${id}`, p),
  removePriceBook:  (id)         => http.delete(`/price-books/${id}`),
  syncPriceBookEntries: (id, entries) => http.put(`/price-books/${id}/entries`, { entries }),
  resolvePrices:    (payload)    => http.post('/price-books/resolve', payload),

  // Quotations
  quotations:      (params = {}) => http.get('/quotations', { params }),
  quotation:       (id)         => http.get(`/quotations/${id}`),
  createQuotation: (payload)    => http.post('/quotations', payload),
  updateQuotation: (id, p)      => http.put(`/quotations/${id}`, p),
  removeQuotation: (id)         => http.delete(`/quotations/${id}`),
  quotationStatus: (id, status) => http.post(`/quotations/${id}/status`, { status }),
  sendQuotation:   (id)         => http.post(`/quotations/${id}/send`),
  convertQuotation:(id)         => http.post(`/quotations/${id}/convert`),

  // Sales orders
  orders:       (params = {})   => http.get('/sales-orders', { params }),
  order:        (id)            => http.get(`/sales-orders/${id}`),
  createOrder:  (payload)       => http.post('/sales-orders', payload),
  updateOrder:  (id, p)         => http.put(`/sales-orders/${id}`, p),
  removeOrder:  (id)            => http.delete(`/sales-orders/${id}`),
  orderStatus:  (id, status)    => http.post(`/sales-orders/${id}/status`, { status }),
  convertOrder: (id)            => http.post(`/sales-orders/${id}/convert`),

  // Invoices
  invoices:      (params = {})  => http.get('/invoices', { params }),
  invoiceStats:  ()             => http.get('/invoices/stats'),
  invoice:       (id)           => http.get(`/invoices/${id}`),
  createInvoice: (payload)      => http.post('/invoices', payload),
  updateInvoice: (id, p)        => http.put(`/invoices/${id}`, p),
  removeInvoice: (id)           => http.delete(`/invoices/${id}`),
  invoiceStatus: (id, status)   => http.post(`/invoices/${id}/status`, { status }),
  pay:           (id, payload)  => http.post(`/invoices/${id}/pay`, payload),

  // Payments
  payments:      (params = {})  => http.get('/payments', { params }),
  removePayment: (id)           => http.delete(`/payments/${id}`),
};
