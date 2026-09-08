<template>
  <div class="space-y-4">
    <div class="flex items-center gap-2">
      <label class="label !mb-0">Entity</label>
      <select v-model="entity" class="input input-sm w-auto capitalize" @change="load">
        <option v-for="e in entities" :key="e" :value="e">{{ e }}</option>
      </select>
      <button class="btn-primary btn-sm ml-auto" @click="openNew"><Plus :size="13" /> New field</button>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Required</th><th>Active</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!fields.length"><td colspan="6"><div class="empty py-8 text-ink-subtle text-xs">No custom fields for {{ entity }} yet.</div></td></tr>
            <tr v-for="f in fields" :key="f.id">
              <td class="text-ink dark:text-ink-dark">{{ f.label }}</td>
              <td class="font-mono text-2xs text-ink-subtle">{{ f.key }}</td>
              <td><span class="badge-neutral">{{ f.type }}</span></td>
              <td>{{ f.required ? 'Yes' : '—' }}</td>
              <td><span :class="f.is_active ? 'badge-success' : 'badge-neutral'">{{ f.is_active ? 'active' : 'off' }}</span></td>
              <td class="text-right whitespace-nowrap">
                <button class="btn-ghost btn-xs" @click="openEdit(f)"><Pencil :size="12" /></button>
                <button class="btn-ghost btn-xs text-rose-600" @click="remove(f)"><Trash2 :size="12" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- editor modal -->
    <div v-if="form" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="form = null">
      <div class="card w-full max-w-md p-0 overflow-hidden">
        <div class="drawer-head justify-between">
          <div class="font-semibold text-ink dark:text-ink-dark">{{ form.id ? 'Edit field' : 'New field' }} · {{ entity }}</div>
          <button class="btn-ghost btn-icon btn-sm" @click="form = null"><X :size="16" /></button>
        </div>
        <div class="p-4 space-y-3">
          <div><label class="label">Label</label><input v-model="form.label" class="input" placeholder="e.g. Budget" /></div>
          <div>
            <label class="label">Type</label>
            <select v-model="form.type" class="input capitalize">
              <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div v-if="form.type === 'select'">
            <label class="label">Options (one per line)</label>
            <textarea v-model="optionsText" class="input" rows="3" placeholder="SMB&#10;Enterprise"></textarea>
          </div>
          <div><label class="label">Help text</label><input v-model="form.help" class="input" /></div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.required" /> Required</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_active" /> Active</label>
            <div class="ml-auto"><label class="label !mb-0 inline mr-1">Order</label><input v-model.number="form.sort_order" type="number" class="input input-sm w-16" /></div>
          </div>
          <div class="flex justify-end gap-2 pt-1">
            <button class="btn-secondary btn-sm" @click="form = null">Cancel</button>
            <button class="btn-primary btn-sm" :disabled="saving || !form.label" @click="save">
              <Loader2 v-if="saving" :size="14" class="animate-spin" /> Save
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import http from '@/services/http';
import { useToast } from 'vue-toastification';
import { Plus, Pencil, Trash2, X, Loader2 } from 'lucide-vue-next';

const toast = useToast();
const entities = ref(['lead', 'customer', 'deal', 'contact']);
const types = ref([]);
const entity = ref('lead');
const fields = ref([]);
const form = ref(null);
const saving = ref(false);

const optionsText = computed({
  get: () => (form.value?.options || []).join('\n'),
  set: (v) => { if (form.value) form.value.options = String(v).split('\n').map((s) => s.trim()).filter(Boolean); },
});

async function load() {
  try {
    const { data } = await http.get('/settings/custom-fields', { params: { entity: entity.value } });
    fields.value = data.data.fields; types.value = data.data.types; entities.value = data.data.entities;
  } catch { fields.value = []; }
}
function openNew() {
  form.value = { entity: entity.value, label: '', type: 'text', options: [], help: '', required: false, is_active: true, sort_order: fields.value.length + 1 };
}
function openEdit(f) { form.value = { ...f, options: f.options || [] }; }

async function save() {
  saving.value = true;
  try {
    const body = { entity: entity.value, label: form.value.label, type: form.value.type,
      options: form.value.type === 'select' ? form.value.options : null,
      help: form.value.help || null, required: !!form.value.required, is_active: !!form.value.is_active,
      sort_order: form.value.sort_order || 0 };
    if (form.value.id) await http.put(`/settings/custom-fields/${form.value.id}`, body);
    else await http.post('/settings/custom-fields', body);
    toast.success('Field saved'); form.value = null; await load();
  } catch (e) { toast.error(e.response?.data?.message || 'Save failed'); }
  finally { saving.value = false; }
}
async function remove(f) {
  if (!confirm(`Delete custom field "${f.label}"? Stored values remain but stop showing.`)) return;
  try { await http.delete(`/settings/custom-fields/${f.id}`); await load(); } catch { toast.error('Delete failed'); }
}

onMounted(load);
</script>
