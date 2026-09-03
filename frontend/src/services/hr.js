import http from './http';

// Module 12 — HR: employees, attendance, leave.
export default {
  employees:      (params = {}) => http.get('/employees', { params }),
  employeeStats:  ()           => http.get('/employees/stats'),
  employeeMeta:   ()           => http.get('/employees/meta'),
  employee:       (id)         => http.get(`/employees/${id}`),
  createEmployee: (p)          => http.post('/employees', p),
  updateEmployee: (id, p)      => http.put(`/employees/${id}`, p),
  removeEmployee: (id)         => http.delete(`/employees/${id}`),

  attendance:     (params = {}) => http.get('/attendance', { params }),
  logAttendance:  (p)          => http.post('/attendance', p),

  leaveTypes:     ()           => http.get('/leave/types'),
  createLeaveType:(p)          => http.post('/leave/types', p),
  leaveRequests:  (params = {}) => http.get('/leave/requests', { params }),
  createLeave:    (p)          => http.post('/leave/requests', p),
  decideLeave:    (id, action, note) => http.post(`/leave/requests/${id}/decide`, { action, note }),
};
