import http from './http';

export default {
  check: (type, data, excludeId = null) => http.post('/duplicates/check', {
    type,
    ...data,
    ...(excludeId ? { exclude_id: excludeId } : {}),
  }),
  previewMerge: (type, primaryId, duplicateId) => http.post('/duplicates/merge-preview', {
    type, primary_id: primaryId, duplicate_id: duplicateId,
  }),
  merge: (type, primaryId, duplicateId, fieldSources) => http.post('/duplicates/merge', {
    type, primary_id: primaryId, duplicate_id: duplicateId, field_sources: fieldSources,
  }),
};
