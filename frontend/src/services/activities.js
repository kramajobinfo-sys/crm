import http from './http';

const base = '/activities';

export default {
  feed:  (params = {}) => http.get(`${base}/feed`, { params }),
  stats: ()           => http.get(`${base}/stats`),
  meta:  ()           => http.get(`${base}/meta`),

  tasks:        (params = {})  => http.get(`${base}/tasks`, { params }),
  createTask:   (payload)      => http.post(`${base}/tasks`, payload),
  updateTask:   (id, payload)  => http.put(`${base}/tasks/${id}`, payload),
  completeTask: (id)           => http.post(`${base}/tasks/${id}/complete`),
  removeTask:   (id)           => http.delete(`${base}/tasks/${id}`),

  meetings:      (params = {}) => http.get(`${base}/meetings`, { params }),
  createMeeting: (payload)     => http.post(`${base}/meetings`, payload),
  updateMeeting: (id, payload) => http.put(`${base}/meetings/${id}`, payload),
  removeMeeting: (id)          => http.delete(`${base}/meetings/${id}`),

  calls:      (params = {})    => http.get(`${base}/calls`, { params }),
  createCall: (payload)        => http.post(`${base}/calls`, payload),
  updateCall: (id, payload)    => http.put(`${base}/calls/${id}`, payload),
  removeCall: (id)             => http.delete(`${base}/calls/${id}`),

  reminders:        ()         => http.get(`${base}/reminders`),
  createReminder:   (payload)  => http.post(`${base}/reminders`, payload),
  completeReminder: (id)       => http.post(`${base}/reminders/${id}/complete`),
  removeReminder:   (id)       => http.delete(`${base}/reminders/${id}`),
};
