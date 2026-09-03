import http from './http';

const base = '/ai';

export default {
  conversations:      ()        => http.get(`${base}/conversations`),
  conversation:       (id)      => http.get(`${base}/conversations/${id}`),
  createConversation: ()        => http.post(`${base}/conversations`),
  send:               (id, content) => http.post(`${base}/conversations/${id}/messages`, { content }),
  removeConversation: (id)      => http.delete(`${base}/conversations/${id}`),

  insights:          ()  => http.get(`${base}/insights`),
  generateInsights:  ()  => http.post(`${base}/insights/generate`),
  dismissInsight:    (id) => http.post(`${base}/insights/${id}/dismiss`),

  predictions:         () => http.get(`${base}/predictions`),
  generatePredictions: () => http.post(`${base}/predictions/generate`),
};
