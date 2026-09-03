import http from './http';

// Manufacturing / BOM (Phase B) — Enterprise-only.
export default {
  boms:    (params = {}) => http.get('/manufacturing/boms', { params }),
  meta:    ()           => http.get('/manufacturing/meta'),
  bom:     (productId)  => http.get(`/manufacturing/products/${productId}/bom`),
  setBom:  (productId, lines) => http.put(`/manufacturing/products/${productId}/bom`, { lines }),
  availability: (productId, warehouseId, quantity) =>
    http.get(`/manufacturing/products/${productId}/availability`, { params: { warehouse_id: warehouseId, quantity } }),
  builds:  (params = {}) => http.get('/manufacturing/builds', { params }),
  build:   (id)         => http.get(`/manufacturing/builds/${id}`),
  runBuild:(payload)    => http.post('/manufacturing/builds', payload),
};
