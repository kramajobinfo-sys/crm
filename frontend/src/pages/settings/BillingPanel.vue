<template>
  <div class="space-y-5">
    <!-- Current subscription -->
    <div v-if="cat" class="card card-pad flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="section-label">Current subscription</div>
        <div class="mt-1 flex items-center gap-2">
          <span class="text-lg font-semibold text-ink dark:text-ink-dark">{{ currentPlanName }}</span>
          <span v-if="cat.subscription" class="badge-success">{{ cat.subscription.status }}</span>
          <span v-else class="badge-neutral">No active subscription</span>
        </div>
        <div v-if="cat.subscription?.current_period_end" class="text-xs text-ink-subtle mt-1">
          Renews on {{ cat.subscription.current_period_end }} · {{ money(cat.subscription.amount, cat.subscription.currency) }} / {{ cat.subscription.interval }}
        </div>
      </div>
      <div class="flex items-center gap-2">
        <div class="toolbar !p-0 gap-1">
          <button v-for="c in ['USD','KHR']" :key="c" class="btn-sm" :class="currency===c ? 'btn-primary' : 'btn-secondary'" @click="setCurrency(c)">{{ c }}</button>
        </div>
        <div class="toolbar !p-0 gap-1">
          <button v-for="i in ['monthly','yearly']" :key="i" class="btn-sm" :class="interval===i ? 'btn-primary' : 'btn-secondary'" @click="interval=i">
            {{ i === 'monthly' ? 'Monthly' : 'Yearly' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Plan cards -->
    <div v-if="cat" class="grid gap-3 md:grid-cols-3">
      <div v-for="p in cat.plans" :key="p.code" class="card card-pad flex flex-col"
           :class="p.code === cat.current_plan && 'ring-2 ring-primary-500/40 border-primary-300'">
        <div class="flex items-center justify-between">
          <div class="text-base font-semibold text-ink dark:text-ink-dark">{{ p.name }}</div>
          <span v-if="p.code === cat.current_plan" class="badge-info">Current</span>
        </div>
        <div class="mt-2">
          <span class="text-2xl font-semibold text-ink dark:text-ink-dark tabular-nums">{{ priceLabel(p) }}</span>
          <span class="text-xs text-ink-subtle"> / {{ interval === 'monthly' ? 'month' : 'year' }}</span>
        </div>
        <div class="text-xs text-ink-subtle mt-1">{{ p.modules_count }} modules included</div>
        <button class="btn-primary btn-sm mt-4 w-full justify-center"
                :disabled="p.code === cat.current_plan || priceOf(p) == null"
                @click="startCheckout(p)">
          {{ p.code === cat.current_plan ? 'Current plan' : (priceOf(p) == null ? 'Not priced yet' : ctaLabel(p)) }}
        </button>
      </div>
    </div>

    <div v-if="cat && !cat.methods.length" class="text-xs text-amber-600">
      No payment methods are enabled yet. A platform admin can enable COD / KHQR / ABA KHQR in the platform billing settings.
    </div>

    <!-- Checkout modal -->
    <div v-if="checkout" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="closeCheckout">
      <div class="card w-full max-w-md p-0 overflow-hidden">
        <div class="drawer-head justify-between">
          <div>
            <div class="font-semibold text-ink dark:text-ink-dark">Subscribe — {{ checkout.plan.name }}</div>
            <div class="text-xs text-ink-subtle">{{ money(priceOf(checkout.plan), currency) }} / {{ interval }}</div>
          </div>
          <button class="btn-ghost btn-icon btn-sm" @click="closeCheckout"><X :size="16" /></button>
        </div>

        <div class="p-4 space-y-4">
          <!-- Method selection -->
          <template v-if="!payment">
            <div class="section-label">Choose a payment method</div>
            <div class="space-y-2">
              <button v-for="m in cat.methods" :key="m.method" type="button"
                      class="w-full flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors"
                      :class="method===m.method ? 'border-primary-500 bg-primary-50/60 dark:bg-primary-900/15' : 'border-line dark:border-line-dark hover:bg-surface-subtle'"
                      @click="method=m.method">
                <component :is="methodMeta[m.method].icon" :size="18" class="text-primary-600 shrink-0" />
                <div class="flex-1">
                  <div class="text-[13px] font-medium text-ink dark:text-ink-dark">{{ methodMeta[m.method].label }}</div>
                  <div class="text-2xs text-ink-subtle">{{ methodMeta[m.method].hint }}</div>
                </div>
                <span v-if="m.method!=='cod' && !m.live" class="badge-warning">manual</span>
                <Check v-if="method===m.method" :size="16" class="text-primary-600" />
              </button>
            </div>
            <button class="btn-primary w-full justify-center" :disabled="!method || submitting" @click="submitCheckout">
              <Loader2 v-if="submitting" :size="15" class="animate-spin" /> Continue
            </button>
          </template>

          <!-- Payment step -->
          <template v-else>
            <div v-if="payment.status==='paid'" class="text-center py-6">
              <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3"><Check :size="24" /></div>
              <div class="font-semibold text-ink dark:text-ink-dark">Payment received</div>
              <div class="text-xs text-ink-subtle mt-1">Your {{ checkout.plan.name }} plan is now active.</div>
              <button class="btn-primary btn-sm mt-4" @click="finish">Done</button>
            </div>

            <div v-else-if="payment.method==='cod'" class="text-center py-2">
              <Truck :size="28" class="text-primary-600 mx-auto mb-2" />
              <div class="font-medium text-ink dark:text-ink-dark">Cash on delivery</div>
              <p class="text-xs text-ink-subtle mt-1 whitespace-pre-line">{{ payment.instructions }}</p>
              <div class="badge-warning mt-3">Awaiting confirmation</div>
              <div><button class="btn-secondary btn-sm mt-4" @click="closeCheckout">Close</button></div>
            </div>

            <div v-else class="text-center">
              <div v-if="payment.qr_payload">
                <div class="text-[13px] font-medium text-ink dark:text-ink-dark mb-1">Scan to pay {{ money(payment.amount, payment.currency) }}</div>
                <div class="text-2xs text-ink-subtle mb-3">{{ payment.method==='aba_khqr' ? 'Open the ABA Mobile app and scan, or tap the button below.' : 'Open any Bakong / KHQR-enabled bank app and scan.' }}</div>
                <div class="inline-block bg-white p-3 rounded-xl border border-line" v-html="qrSvg" />
                <a v-if="payment.deeplink" :href="payment.deeplink" class="btn-secondary btn-sm mt-3 w-full justify-center"><Smartphone :size="14" /> Open in app</a>
              </div>
              <div v-else class="py-4">
                <Hourglass :size="26" class="text-amber-500 mx-auto mb-2" />
                <div class="text-[13px] font-medium text-ink dark:text-ink-dark">Awaiting manual confirmation</div>
                <p class="text-2xs text-ink-subtle mt-1">This gateway isn't fully configured yet. Our team will confirm your payment shortly.</p>
              </div>
              <div class="flex items-center justify-center gap-1.5 text-xs text-ink-subtle mt-3">
                <Loader2 :size="13" class="animate-spin" /> Waiting for payment…
              </div>
              <button class="btn-ghost btn-sm mt-2" @click="closeCheckout">Cancel</button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import qrcode from 'qrcode-generator';
import http from '@/services/http';
import { useToast } from 'vue-toastification';
import { X, Check, Loader2, Truck, Smartphone, Hourglass, QrCode, Banknote } from 'lucide-vue-next';

const toast = useToast();
const cat = ref(null);
const currency = ref('USD');
const interval = ref('monthly');
const checkout = ref(null);   // { plan }
const method = ref(null);
const payment = ref(null);
const submitting = ref(false);
let pollTimer = null;

const methodMeta = {
  cod: { label: 'Cash on delivery (COD)', hint: 'Pay cash; we activate once confirmed', icon: Banknote },
  khqr: { label: 'KHQR (Bakong)', hint: 'Scan with any KHQR bank app', icon: QrCode },
  aba_khqr: { label: 'ABA KHQR', hint: 'Scan or open in the ABA Mobile app', icon: QrCode },
};

const currentPlanName = computed(() => {
  const p = cat.value?.plans.find((x) => x.code === cat.value.current_plan);
  return p?.name || 'None';
});

const priceOf = (p) => p.prices?.[interval.value] ?? null;
const priceLabel = (p) => { const v = priceOf(p); return v == null ? '—' : money(v, currency.value); };
const ctaLabel = (p) => {
  const order = { starter: 1, professional: 2, enterprise: 3 };
  const cur = cat.value?.current_plan;
  if (cur && order[p.code] < order[cur]) return 'Downgrade';
  if (cur) return 'Upgrade';
  return 'Subscribe';
};

function money(v, cur) {
  if (v == null) return '—';
  const n = Number(v);
  return cur === 'KHR' ? `${Math.round(n).toLocaleString()} ៛` : `$${n.toFixed(2)}`;
}

const qrSvg = computed(() => {
  if (!payment.value?.qr_payload) return '';
  const qr = qrcode(0, 'M');
  qr.addData(payment.value.qr_payload);
  qr.make();
  return qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
});

async function load() {
  const { data } = await http.get('/billing/catalogue', { params: { currency: currency.value } });
  cat.value = data.data;
}
function setCurrency(c) { if (currency.value !== c) { currency.value = c; load(); } }

function startCheckout(plan) {
  checkout.value = { plan };
  method.value = cat.value.methods[0]?.method || null;
  payment.value = null;
}
function closeCheckout() { stopPoll(); checkout.value = null; payment.value = null; }
async function finish() { closeCheckout(); await load(); }

async function submitCheckout() {
  submitting.value = true;
  try {
    const { data } = await http.post('/billing/checkout', {
      plan: checkout.value.plan.code, interval: interval.value, currency: currency.value, method: method.value,
    });
    payment.value = data.data;
    if (payment.value.method !== 'cod' && payment.value.status === 'pending') startPoll();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Checkout failed');
  } finally { submitting.value = false; }
}

function startPoll() {
  stopPoll();
  pollTimer = setInterval(async () => {
    if (!payment.value) return;
    try {
      const { data } = await http.get(`/billing/payments/${payment.value.id}`);
      payment.value = data.data;
      if (['paid', 'canceled', 'failed'].includes(payment.value.status)) {
        stopPoll();
        if (payment.value.status === 'paid') toast.success('Payment received — plan activated');
      }
    } catch { /* keep polling */ }
  }, 4000);
}
function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

watch(interval, () => {});
onMounted(load);
onUnmounted(stopPoll);
</script>
