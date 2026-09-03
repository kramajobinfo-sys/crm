import http from './http';
export default {
  summary: () => http.get('/dashboard/summary'),
  kpis: () => http.get('/dashboard/kpis'),
  salesChart: (months = 7) => http.get('/dashboard/sales-chart', { params: { months } }),
  purchaseChart: (months = 7) => http.get('/dashboard/purchase-chart', { params: { months } }),
  revenueChart: (months = 12) => http.get('/dashboard/revenue-chart', { params: { months } }),
  pipeline: () => http.get('/dashboard/pipeline'),
  topPerformers: (limit = 5) => http.get('/dashboard/top-performers', { params: { limit } }),
  tasks: () => http.get('/dashboard/tasks-summary'),
  recent: (limit = 10) => http.get('/dashboard/recent-activity', { params: { limit } }),
  aiInsights: () => http.get('/dashboard/ai-insights'),
};
