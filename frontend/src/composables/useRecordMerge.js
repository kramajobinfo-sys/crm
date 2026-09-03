import { reactive } from 'vue';
import duplicateApi from '@/services/duplicates';

export function useRecordMerge(onMerged) {
  const state = reactive({ open: false, loading: false, saving: false, type: '', preview: null, error: '' });

  async function open(type, primaryId, candidate) {
    state.open = true; state.loading = true; state.error = ''; state.preview = null; state.type = type;
    try {
      const { data } = await duplicateApi.previewMerge(type, primaryId, candidate.id);
      state.preview = data.data;
    } catch (error) {
      state.error = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Unable to preview merge.';
    } finally { state.loading = false; }
  }

  function close() { if (!state.saving) { state.open = false; state.preview = null; state.error = ''; } }

  async function confirm(fieldSources) {
    if (!state.preview) return;
    state.saving = true; state.error = '';
    try {
      const { data } = await duplicateApi.merge(state.type, state.preview.primary.id, state.preview.duplicate.id, fieldSources);
      state.open = false;
      if (onMerged) await onMerged(data.data);
    } catch (error) {
      state.error = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Merge failed.';
    } finally { state.saving = false; }
  }

  return { state, open, close, confirm };
}
