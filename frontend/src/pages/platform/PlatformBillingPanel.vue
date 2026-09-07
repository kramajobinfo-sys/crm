<template>
  <div class="space-y-5">
    <!-- Pending payments -->
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Pending payments</span>
        <button class="btn-ghost btn-sm" @click="loadPayments"><RefreshCw :size="13" /> Refresh</button>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr>
            <th>Company</th><th>Plan</th><th>Method</th><th class="th-num">Amount</th><th>Created</th><th></th>
          </tr></thead>
          <tbody>
            <tr v-if="!payments.length"><td colspan="6"><div class="empty py-8 text-ink-subtle text-xs">No pending payments.</div></td></tr>
            <tr v-for="p in payments" :key="p.id">
              <td class="text-ink dark:text-ink-dark">{{ p.company?.name || '—' }}</td>
              <td>{{ p.plan?.name || '—' }}</td>
              <td><span class="badge-neutral">{{ methodLabel(p.method) }}</span></td>
              <td class="td-num">{{ money(p.amount, p.currency) }}</td>
              <td class="text-ink-subtle text-xs">{{ (p.created_at || '').slice(0,10) }}</td>
              <td class="text-right">
                <button class="btn-primary btn-xs" :disabled="confirming===p.id" @click="confirmPayment(p)">
                  <Check :size="12" /> Confirm & activate
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payment methods & credentials -->
    <div class="card card-pad space-y-4" v-if="settings">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold text-ink dark:text-ink-dark">Payment methods</div>
        <button class="btn-primary btn-sm" :disabled="savingSettings" @click="saveSettings">
          <Loader2 v-if="savingSettings" :size="14" class="animate-spin" /> Save settings
        </button>
      </div>

      <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="settings.enabled.cod" /> Cash on delivery (COD)</label>
      <textarea v-model="settings.cod_instructions" class="input" rows="2" placeholder="COD instructions shown to members"></textarea>

      <div class="divider" />
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" v-model="settings.enabled.khqr" /> KHQR (Bakong)</label>
        <span :class="settings.status.khqr ? 'badge-success' : 'badge-warning'">{{ settings.status.khqr ? 'live' : 'manual until configured' }}</span>
      </div>
      <div class="grid sm:grid-cols-2 gap-2">
        <div><label class="label">Merchant name</label><input v-model="settings.config.khqr.merchant_name" class="input input-sm" placeholder="NPCRM" /></div>
        <div><label class="label">Merchant city</label><input v-model="settings.config.khqr.merchant_city" class="input input-sm" placeholder="Phnom Penh" /></div>
        <div><label class="label">Bakong account ID</label><input v-model="settings.config.khqr.bakong_id" class="input input-sm" placeholder="name@bank" /></div>
        <div><label class="label">Open API base URL</label><input v-model="settings.config.khqr.api_base" class="input input-sm" placeholder="https://api-bakong.nbc.gov.kh" /></div>
        <div class="sm:col-span-2"><label class="label">Open API token {{ settings.config.khqr.has_api_token ? '(set — leave blank to keep)' : '' }}</label>
          <input v-model="secrets.khqr_token" type="password" class="input input-sm" :placeholder="settings.config.khqr.has_api_token ? '••••••••' : 'paste token'" /></div>
      </div>

      <div class="divider" />
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" v-model="settings.enabled.aba_khqr" /> ABA KHQR (PayWay)</label>
        <span :class="settings.status.aba_khqr ? 'badge-success' : 'badge-warning'">{{ settings.status.aba_khqr ? 'live' : 'manual until configured' }}</span>
      </div>
      <div class="grid sm:grid-cols-2 gap-2">
        <div><label class="label">Merchant ID</label><input v-model="settings.config.aba.merchant_id" class="input input-sm" placeholder="ec412345" /></div>
        <div><label class="label">PayWay base URL</label><input v-model="settings.config.aba.base_url" class="input input-sm" placeholder="https://checkout.payway.com.kh" /></div>
        <div class="sm:col-span-2"><label class="label">API key {{ settings.config.aba.has_api_key ? '(set — leave blank to keep)' : '' }}</label>
          <input v-model="secrets.aba_key" type="password" class="input input-sm" :placeholder="settings.config.aba.has_api_key ? '••••••••' : 'paste API key'" /></div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" v-model="settings.config.aba.sandbox" /> Sandbox mode</label>
      </div>
    </div>

    <!-- Plan pricing -->
    <div class="card card-pad space-y-3" v-if="plans.length">
      <div class="text-sm font-semibold text-ink dark:text-ink-dark">Plan pricing</div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr>
            <th>Plan</th><th class="th-num">Monthly USD</th><th class="th-num">Yearly USD</th><th class="th-num">Monthly KHR</th><th class="th-num">Yearly KHR</th><th></th>
          </tr></thead>
          <tbody>
            <tr v-for="p in plans" :key="p.code">
              <td class="text-ink dark:text-ink-dark font-medium">{{ p.name }}</td>
              <td><input v-model.number="p.prices.monthly.USD" type="number" min="0" step="0.01" class="input input-sm w-24 text-right" /></td>
              <td><input v-model.number="p.prices.yearly.USD" type="number" min="0" step="0.01" class="input input-sm w-24 text-right" /></td>
              <td><input v-model.number="p.prices.monthly.KHR" type="number" min="0" step="100" class="input input-sm w-28 text-right" /></td>
              <td><input v-model.number="p.prices.yearly.KHR" type="number" min="0" step="100" class="input input-sm w-28 text-right" /></td>
              <td class="text-right"><button class="btn-secondary btn-xs" :disabled="savingPlan===p.code" @click="savePrices(p)">Save</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import http from '@/services/http';
import { useToast } from 'vue-toastification';
import { Check, Loader2, RefreshCw } from 'lucide-vue-next';

const toast = useToast();
const payments = ref([]);
const settings = ref(null);
const plans = ref([]);
const secrets = ref({ khqr_token: '', aba_key: '' });
const savingSettings = ref(false);
const savingPlan = ref(null);
const confirming = ref(null);

const methodLabel = (m) => ({ cod: 'COD', khqr: 'KHQR', aba_khqr: 'ABA KHQR' }[m] || m);
const money = (v, c) => v == null ? '—' : (c === 'KHR' ? `${Math.round(Number(v)).toLocaleString()} ៛` : `$${Number(v).toFixed(2)}`);

async function loadPayments() {
  const { data } = await http.get('/platform/billing/payments', { params: { status: 'pending' } });
  payments.value = data.data;
}
async function loadSettings() {
  const { data } = await http.get('/platform/billing/settings');
  const s = data.data;
  s.enabled = { cod: false, khqr: false, aba_khqr: false, ...(s.enabled || {}) };
  settings.value = s;
}
async function loadPlans() {
  const { data } = await http.get('/platform/billing/plans');
  plans.value = data.data.map((p) => ({ ...p, prices: {
    monthly: { USD: p.prices.monthly?.USD ?? null, KHR: p.prices.monthly?.KHR ?? null },
    yearly: { USD: p.prices.yearly?.USD ?? null, KHR: p.prices.yearly?.KHR ?? null },
  } }));
}

async function saveSettings() {
  savingSettings.value = true;
  try {
    const c = settings.value.config;
    const body = {
      enabled: settings.value.enabled,
      cod_instructions: settings.value.cod_instructions,
      khqr: { merchant_name: c.khqr.merchant_name, merchant_city: c.khqr.merchant_city, bakong_id: c.khqr.bakong_id, api_base: c.khqr.api_base },
      aba: { merchant_id: c.aba.merchant_id, base_url: c.aba.base_url, sandbox: c.aba.sandbox },
    };
    if (secrets.value.khqr_token) body.khqr.api_token = secrets.value.khqr_token;
    if (secrets.value.aba_key) body.aba.api_key = secrets.value.aba_key;
    await http.put('/platform/billing/settings', body);
    secrets.value = { khqr_token: '', aba_key: '' };
    toast.success('Billing settings saved');
    await loadSettings();
  } catch (e) { toast.error(e.response?.data?.message || 'Save failed'); }
  finally { savingSettings.value = false; }
}

async function savePrices(p) {
  savingPlan.value = p.code;
  try {
    const rows = [];
    for (const interval of ['monthly', 'yearly']) for (const cur of ['USD', 'KHR']) {
      const amt = p.prices[interval][cur];
      if (amt != null && amt !== '') rows.push({ interval, currency: cur, amount: amt });
    }
    await http.put(`/platform/billing/plans/${p.code}/prices`, { prices: rows });
    toast.success(`${p.name} prices saved`);
  } catch (e) { toast.error(e.response?.data?.message || 'Save failed'); }
  finally { savingPlan.value = null; }
}

async function confirmPayment(p) {
  confirming.value = p.id;
  try {
    await http.post(`/platform/billing/payments/${p.id}/confirm`);
    toast.success('Payment confirmed — plan activated');
    await loadPayments();
  } catch (e) { toast.error(e.response?.data?.message || 'Confirm failed'); }
  finally { confirming.value = null; }
}

onMounted(() => { loadPayments(); loadSettings(); loadPlans(); });
</script>
