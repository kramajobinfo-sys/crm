import http from './http';

const base = '/projects';

export default {
  list: (params = {}) => http.get(base, { params }),
  stats: () => http.get(`${base}/stats`),
  meta: () => http.get(`${base}/meta`),
  show: (id) => http.get(`${base}/${id}`),
  create: (payload) => http.post(base, payload),
  fromDeal: (dealId) => http.post(`${base}/from-deal/${dealId}`),
  update: (id, payload) => http.put(`${base}/${id}`, payload),
  remove: (id) => http.delete(`${base}/${id}`),
  addMember: (id, payload) => http.post(`${base}/${id}/members`, payload),
  removeMember: (id, userId) => http.delete(`${base}/${id}/members/${userId}`),
  addMilestone: (id, payload) => http.post(`${base}/${id}/milestones`, payload),
  updateMilestone: (id, milestoneId, payload) => http.put(`${base}/${id}/milestones/${milestoneId}`, payload),
  removeMilestone: (id, milestoneId) => http.delete(`${base}/${id}/milestones/${milestoneId}`),
  addTask: (id, payload) => http.post(`${base}/${id}/tasks`, payload),
  showTask: (id, taskId) => http.get(`${base}/${id}/tasks/${taskId}`),
  updateTask: (id, taskId, payload) => http.put(`${base}/${id}/tasks/${taskId}`, payload),
  removeTask: (id, taskId) => http.delete(`${base}/${id}/tasks/${taskId}`),
  addComment: (id, taskId, body) => http.post(`${base}/${id}/tasks/${taskId}/comments`, { body }),
  addDependency: (id, taskId, dependsOnTaskId) => http.post(`${base}/${id}/tasks/${taskId}/dependencies`, { depends_on_task_id: dependsOnTaskId }),
  removeDependency: (id, taskId, dependsOnTaskId) => http.delete(`${base}/${id}/tasks/${taskId}/dependencies/${dependsOnTaskId}`),
  uploadTaskFile(id, taskId, file) {
    const form = new FormData(); form.append('file', file);
    return http.post(`${base}/${id}/tasks/${taskId}/attachments`, form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 120000 });
  },
  removeTaskFile: (id, taskId, attachmentId) => http.delete(`${base}/${id}/tasks/${taskId}/attachments/${attachmentId}`),
  timeEntries: (id, params = {}) => http.get(`${base}/${id}/time-entries`, { params }),
  addTime: (id, payload) => http.post(`${base}/${id}/time-entries`, payload),
  updateTime: (id, entryId, payload) => http.put(`${base}/${id}/time-entries/${entryId}`, payload),
  removeTime: (id, entryId) => http.delete(`${base}/${id}/time-entries/${entryId}`),
  decideTime: (id, entryId, decision) => http.post(`${base}/${id}/time-entries/${entryId}/decision`, { decision }),
  workload: (params = {}) => http.get(`${base}/workload`, { params }),
  templates: () => http.get(`${base}/templates`),
  saveTemplate: (projectId, payload) => http.post(`${base}/templates/from-project/${projectId}`, payload),
  updateTemplate: (id, payload) => http.put(`${base}/templates/${id}`, payload),
  removeTemplate: (id) => http.delete(`${base}/templates/${id}`),
  createFromTemplate: (id, payload) => http.post(`${base}/templates/${id}/create-project`, payload),
  automationRules: () => http.get(`${base}/automation-rules`),
  createAutomationRule: (payload) => http.post(`${base}/automation-rules`, payload),
  updateAutomationRule: (id, payload) => http.put(`${base}/automation-rules/${id}`, payload),
  removeAutomationRule: (id) => http.delete(`${base}/automation-rules/${id}`),
  managementReport: (params = {}) => http.get(`${base}/management-report`, { params }),
};
