import { reactive } from 'vue';
import duplicateApi from '@/services/duplicates';

export function useDuplicateGuard() {
  const state = reactive({ open: false, checking: false, candidates: [] });
  let pending = null;

  async function check(type, payload, excludeId, onProceed) {
    state.checking = true;
    try {
      const { data } = await duplicateApi.check(type, payload, excludeId);
      const candidates = data.data || [];
      if (candidates.length) {
        state.candidates = candidates;
        state.open = true;
        pending = onProceed;
        return false;
      }
    } catch {
      // Duplicate checking is advisory. Validation and tenant rules still run on save.
    } finally {
      state.checking = false;
    }
    return true;
  }

  function cancel() {
    state.open = false;
    state.candidates = [];
    pending = null;
  }

  async function proceed() {
    const callback = pending;
    cancel();
    if (callback) await callback();
  }

  return { state, check, cancel, proceed };
}
