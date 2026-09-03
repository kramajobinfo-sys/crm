import http from './http';

const base = '/reports';

export default {
  datasets: ()          => http.get(`${base}/datasets`),
  run:      (payload)   => http.post(`${base}/run`, payload),
  list:     ()          => http.get(base),
  show:     (id)        => http.get(`${base}/${id}`),
  create:   (payload)   => http.post(base, payload),
  update:   (id, p)     => http.put(`${base}/${id}`, p),
  remove:   (id)        => http.delete(`${base}/${id}`),
  export:   (id)        => http.post(`${base}/${id}/export`),
  exports:  (id)        => http.get(`${base}/${id}/exports`),

  // Exports live on the private disk, so there is no public link to open — the endpoint
  // needs the Bearer header. Fetch as a blob and trigger a save, same as documents.js.
  async downloadExport(exportId, filename) {
    const res = await http.get(`${base}/exports/${exportId}/download`, { responseType: 'blob', timeout: 120000 });
    const url = window.URL.createObjectURL(res.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || `report-export-${exportId}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  },
  dashboards: ()        => http.get(`${base}/dashboards`),
  createDashboard: (p)  => http.post(`${base}/dashboards`, p),
  updateDashboard: (id, p) => http.put(`${base}/dashboards/${id}`, p),
  deleteDashboard: (id) => http.delete(`${base}/dashboards/${id}`),
};
