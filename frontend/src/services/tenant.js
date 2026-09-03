import http from './http';

// Public branding lookup for the login/register screens — resolved from the request's subdomain.
export default {
  info: () => http.get('/tenant-info'),
};
