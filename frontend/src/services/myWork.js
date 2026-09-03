import http from './http';

export default { list: (params = {}) => http.get('/my-work', { params }) };
