import http from './http';
export default {
  login: (payload) => http.post('/auth/login', payload),
  register: (payload) => http.post('/auth/register', payload),
  logout: () => http.post('/auth/logout'),
  refresh: () => http.post('/auth/refresh'),
  me: () => http.get('/auth/me'),
  updateProfile: (payload) => http.put('/auth/profile', payload),
  uploadAvatar: (formData) => http.post('/auth/avatar', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  changePassword: (payload) => http.post('/auth/change-password', payload),
  forgotPassword: (email) => http.post('/auth/forgot-password', { email }),
  resetPassword: (payload) => http.post('/auth/reset-password', payload),
  twoFactor: {
    enable: () => http.post('/auth/2fa/enable'),
    confirm: (code) => http.post('/auth/2fa/confirm', { code }),
    verify: (code) => http.post('/auth/2fa/verify', { code }),
    disable: (password) => http.post('/auth/2fa/disable', { password }),
  },
};
