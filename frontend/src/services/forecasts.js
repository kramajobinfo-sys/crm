import http from './http';

// Zoho gap #10 — Forecasts (quota vs. achieved per user/period).
export default {
  board:      (params = {}) => http.get('/forecasts', { params }),
  meta:       ()           => http.get('/forecasts/meta'),
  targets:    (params = {}) => http.get('/forecasts/targets', { params }),
  setTargets: (payload)    => http.put('/forecasts/targets', payload),
};
