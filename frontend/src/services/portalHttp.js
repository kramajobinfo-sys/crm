import axios from 'axios';
import { usePortalAuthStore } from '@/stores/portalAuth';
import { useToast } from 'vue-toastification';
import router from '@/router';

// Deliberately a separate axios instance from services/http.js: the staff and portal auth
// stores are different Pinia stores with different tokens, and a shared instance would risk
// a portal page picking up a staff token (or vice versa) via one interceptor's closure.
const portalHttp = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 30000,
  headers: { Accept: 'application/json' },
});

portalHttp.interceptors.request.use((config) => {
  const auth = usePortalAuthStore();
  if (auth.token) config.headers.Authorization = `Bearer ${auth.token}`;
  return config;
});

portalHttp.interceptors.response.use(
  (res) => res,
  (err) => {
    const toast = useToast();
    if (!err.response) { toast.error('Network error'); return Promise.reject(err); }
    const { status, data } = err.response;

    if (status === 401) {
      usePortalAuthStore().clear();
      router.push({ name: 'portal-login' });
    } else if (status >= 500) {
      toast.error(data?.message || 'Server error');
    }
    return Promise.reject(err);
  },
);

export default portalHttp;
