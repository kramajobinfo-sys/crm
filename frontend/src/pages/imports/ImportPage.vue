<template>
  <div class="page max-w-4xl">
    <div class="page-header">
      <div>
        <h1 class="page-title">Data Import</h1>
        <p class="page-sub">Bulk-import leads, contacts and customers from a CSV file.</p>
      </div>
      <button v-if="step !== 'upload'" class="btn-secondary btn-sm" @click="reset"><RotateCcw :size="14" /> Start over</button>
    </div>

    <!-- Step 1: upload -->
    <div v-if="step === 'upload'" class="card card-pad space-y-4 max-w-lg">
      <div>
        <label class="label">What are you importing?</label>
        <select v-model="entity" class="input">
          <option v-for="e in entities" :key="e.key" :value="e.key">{{ e.label }}</option>
        </select>
        <p v-if="!entities.length" class="text-xs text-amber-600 mt-1">You don't have permission to import any entity.</p>
      </div>
      <div>
        <label class="label">CSV file</label>
        <input ref="fileInput" type="file" accept=".csv,text/csv" class="input" @change="onFile" />
        <p class="hint">First row must be column headers. Max 10 MB.</p>
      </div>
      <button class="btn-primary" :disabled="!file || !entity || busy" @click="upload">
        <Loader2 v-if="busy" :size="15" class="animate-spin" /> Upload &amp; map
      </button>
    </div>

    <!-- Step 2: map + preview -->
    <div v-else-if="step === 'map'" class="space-y-4">
      <div class="card card-pad">
        <div class="text-sm font-semibold text-ink dark:text-ink-dark mb-1">Map your columns</div>
        <p class="text-[11px] text-ink-subtle mb-3">Match each CSV column to a field. Required fields are marked *.</p>
        <div class="grid sm:grid-cols-2 gap-2.5">
          <div v-for="col in columns" :key="col" class="flex items-center gap-2">
            <span class="text-xs font-mono text-ink-muted w-40 truncate" :title="col">{{ col }}</span>
            <span class="text-ink-subtle">→</span>
            <select v-model="mapping[col]" class="input input-sm flex-1">
              <option value="">— skip —</option>
              <option v-for="f in fields" :key="f.key" :value="f.key">{{ f.label }}{{ f.required ? ' *' : '' }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card card-pad">
        <div class="flex items-center gap-2 mb-3">
          <button class="btn-secondary btn-sm" :disabled="busy" @click="runPreview"><Eye :size="14" /> Preview</button>
          <div v-if="preview" class="flex items-center gap-2 text-xs">
            <span class="badge-success">{{ preview.valid }} valid</span>
            <span v-if="preview.errors" class="badge-danger">{{ preview.errors }} errors</span>
            <span class="text-ink-subtle">of {{ preview.total }} rows</span>
          </div>
          <span v-if="previewMsg" class="text-xs text-amber-600">{{ previewMsg }}</span>
        </div>
        <div v-if="preview" class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Row</th><th v-for="f in mappedFields" :key="f">{{ f }}</th><th>Issues</th></tr></thead>
            <tbody>
              <tr v-for="r in preview.preview" :key="r.row" :class="r.errors.length && 'bg-rose-50/40 dark:bg-rose-900/10'">
                <td class="text-ink-subtle">{{ r.row }}</td>
                <td v-for="f in mappedFields" :key="f" class="text-ink dark:text-ink-dark">{{ r.data[f] }}</td>
                <td class="text-2xs text-rose-600">{{ r.errors.join('; ') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="preview && preview.valid" class="card card-pad flex flex-wrap items-center gap-4">
        <div>
          <label class="label">On duplicate</label>
          <select v-model="dedupe" class="input input-sm w-auto">
            <option value="skip">Skip duplicates</option>
            <option value="update">Update duplicates</option>
          </select>
        </div>
        <button class="btn-primary btn-sm ml-auto" :disabled="busy" @click="startImport">
          <Loader2 v-if="busy" :size="14" class="animate-spin" /> Import {{ preview.valid }} rows
        </button>
      </div>
    </div>

    <!-- Step 3: result -->
    <div v-else-if="step === 'result'" class="card card-pad max-w-lg text-center">
      <div v-if="result.status === 'processing'" class="py-6">
        <Loader2 :size="28" class="animate-spin text-primary-600 mx-auto mb-2" />
        <div class="font-medium text-ink dark:text-ink-dark">Importing…</div>
        <div class="text-xs text-ink-subtle mt-1">This runs in the background — you can wait or leave.</div>
      </div>
      <div v-else-if="result.status === 'completed'" class="py-4">
        <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3"><Check :size="24" /></div>
        <div class="font-semibold text-ink dark:text-ink-dark">Import complete</div>
        <div class="flex items-center justify-center gap-2 mt-3 text-xs">
          <span class="badge-success">{{ result.created_rows }} created</span>
          <span v-if="result.updated_rows" class="badge-info">{{ result.updated_rows }} updated</span>
          <span v-if="result.skipped_rows" class="badge-neutral">{{ result.skipped_rows }} skipped</span>
          <span v-if="result.error_rows" class="badge-danger">{{ result.error_rows }} errors</span>
        </div>
        <div class="flex items-center justify-center gap-2 mt-4">
          <button v-if="result.error_rows" class="btn-secondary btn-sm" @click="downloadErrors"><Download :size="14" /> Error report</button>
          <button class="btn-primary btn-sm" @click="reset">Import another</button>
        </div>
      </div>
      <div v-else class="py-6">
        <div class="font-medium text-rose-600">Import failed</div>
        <div class="text-xs text-ink-subtle mt-1">{{ result.error || 'Something went wrong.' }}</div>
        <button class="btn-secondary btn-sm mt-4" @click="reset">Try again</button>
      </div>
    </div>

    <!-- Recent imports -->
    <div v-if="step === 'upload' && history.length" class="card overflow-hidden mt-5">
      <div class="panel-head"><span class="panel-title">Recent imports</span></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>File</th><th>Entity</th><th>Status</th><th class="th-num">Created</th><th class="th-num">Errors</th><th>By</th></tr></thead>
          <tbody>
            <tr v-for="h in history" :key="h.id">
              <td class="text-ink dark:text-ink-dark">{{ h.filename }}</td>
              <td class="capitalize">{{ h.entity }}</td>
              <td><span :class="h.status === 'completed' ? 'badge-success' : (h.status === 'failed' ? 'badge-danger' : 'badge-neutral')">{{ h.status }}</span></td>
              <td class="td-num">{{ h.created_rows }}</td>
              <td class="td-num">{{ h.error_rows }}</td>
              <td class="text-ink-subtle">{{ h.user || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import http from '@/services/http';
import { useToast } from 'vue-toastification';
import { Loader2, Eye, Check, Download, RotateCcw } from 'lucide-vue-next';

const toast = useToast();
const step = ref('upload');       // upload | map | result
const entities = ref([]);
const entity = ref('lead');
const file = ref(null);
const fileInput = ref(null);
const busy = ref(false);
const history = ref([]);

const batchId = ref(null);
const columns = ref([]);
const fields = ref([]);
const mapping = reactive({});
const preview = ref(null);
const previewMsg = ref('');
const dedupe = ref('skip');
const result = reactive({});
let pollTimer = null;

const mappedFields = computed(() => [...new Set(Object.values(mapping).filter(Boolean))]);

function onFile(e) { file.value = e.target.files?.[0] || null; }

async function loadMeta() {
  try {
    const { data } = await http.get('/imports/meta');
    entities.value = data.data.entities;
    if (entities.value.length) entity.value = entities.value[0].key;
  } catch { /* surfaced by interceptor */ }
}
async function loadHistory() {
  try { const { data } = await http.get('/imports', { params: { per_page: 8 } }); history.value = data.data; }
  catch { history.value = []; }
}

async function upload() {
  busy.value = true;
  try {
    const fd = new FormData();
    fd.append('entity', entity.value);
    fd.append('file', file.value);
    const { data } = await http.post('/imports', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    const d = data.data;
    batchId.value = d.batch.id;
    columns.value = d.columns;
    fields.value = d.fields;
    Object.keys(mapping).forEach((k) => delete mapping[k]);
    Object.assign(mapping, d.suggested_mapping);
    preview.value = null; previewMsg.value = '';
    step.value = 'map';
  } catch (e) { toast.error(e.response?.data?.message || 'Upload failed'); }
  finally { busy.value = false; }
}

async function runPreview() {
  busy.value = true; previewMsg.value = '';
  try {
    const { data } = await http.post(`/imports/${batchId.value}/preview`, { mapping: { ...mapping } });
    if (data.data.ok === false) { previewMsg.value = data.data.message; preview.value = null; }
    else preview.value = data.data;
  } catch (e) { toast.error(e.response?.data?.message || 'Preview failed'); }
  finally { busy.value = false; }
}

async function startImport() {
  busy.value = true;
  try {
    const { data } = await http.post(`/imports/${batchId.value}/commit`, { mapping: { ...mapping }, dedupe: dedupe.value });
    Object.assign(result, data.data);
    step.value = 'result';
    poll();
  } catch (e) { toast.error(e.response?.data?.message || 'Import failed to start'); }
  finally { busy.value = false; }
}

function poll() {
  stopPoll();
  pollTimer = setInterval(async () => {
    try {
      const { data } = await http.get(`/imports/${batchId.value}`);
      Object.assign(result, data.data.batch);
      if (['completed', 'failed'].includes(result.status)) { stopPoll(); loadHistory(); }
    } catch { /* keep polling */ }
  }, 2500);
}
function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

async function downloadErrors() {
  try {
    const res = await http.get(`/imports/${batchId.value}/errors.csv`, { responseType: 'blob' });
    const url = URL.createObjectURL(res.data);
    const a = document.createElement('a');
    a.href = url; a.download = `import-${batchId.value}-errors.csv`;
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  } catch { toast.error('Could not download the error report'); }
}

function reset() {
  stopPoll();
  step.value = 'upload'; file.value = null; if (fileInput.value) fileInput.value.value = '';
  preview.value = null; previewMsg.value = ''; batchId.value = null; dedupe.value = 'skip';
  loadHistory();
}

onMounted(() => { loadMeta(); loadHistory(); });
onUnmounted(stopPoll);
</script>
