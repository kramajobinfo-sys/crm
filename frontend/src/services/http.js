import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from 'vue-toastification';
import router from '@/router';

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 30000,
  headers: { Accept: 'application/json' },
});

http.interceptors.request.use((config) => {
  const auth = useAuthStore();
  if (auth.token) config.headers.Authorization = `Bearer ${auth.token}`;
  return config;
});

let isRefreshing = false;
let queued = [];
const flush = (token, err) => { queued.forEach((p) => (err ? p.reject(err) : p.resolve(token))); queued = []; };

http.interceptors.response.use(
  (res) => res,
  async (err) => {
    const toast = useToast();
    const auth = useAuthStore();
    const original = err.config;
    if (!err.response) { toast.error('Network error'); return Promise.reject(err); }
    const { status, data } = err.response;

    if (status === 401 && data?.token_error === 'expired' && !original._retry) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => queued.push({ resolve, reject }))
          .then((token) => { original.headers.Authorization = `Bearer ${token}`; return http(original); });
      }
      original._retry = true;
      isRefreshing = true;
      try {
        const refreshed = await auth.refresh();
        flush(refreshed, null);
        original.headers.Authorization = `Bearer ${refreshed}`;
        return http(original);
      } catch (e) {
        flush(null, e); auth.clear(); router.push('/login'); return Promise.reject(e);
      } finally { isRefreshing = false; }
    }

    if (status === 401) { auth.clear(); router.push('/login'); }
    else if (status === 403) toast.error(data?.message || 'You do not have permission to do that');
    else if (status >= 500) toast.error(data?.message || 'Server error');
    return Promise.reject(err);
  },
);

export default http;
