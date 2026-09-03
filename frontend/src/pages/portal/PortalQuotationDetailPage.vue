<template>
  <div>
    <router-link :to="{ name: 'portal-quotations' }" class="text-sm text-primary-600">{{ $t('portal.back_to_quotations') }}</router-link>
    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.loading') }}</div>
    <div v-else-if="!quotation" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.quotation_not_found') }}</div>
    <div v-else class="mt-4 space-y-4">
      <div class="card p-6">
        <div class="flex items-center justify-between mb-2">
          <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">{{ quotation.quote_no }}</h1>
          <span class="text-xs capitalize px-2 py-1 rounded-full"
                :class="quotation.status === 'accepted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700'">
            {{ quotation.status }}
          </span>
        </div>

        <table class="w-full text-sm my-4">
          <thead>
            <tr class="text-left text-ink-muted dark:text-ink-dark-muted border-b border-slate-200 dark:border-slate-700">
              <th class="py-2">{{ $t('portal.item') }}</th><th class="py-2">{{ $t('portal.qty') }}</th><th class="py-2">{{ $t('portal.unit_price') }}</th><th class="py-2 text-right">{{ $t('portal.total') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in quotation.items" :key="i" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2">{{ item.name }}</td>
              <td class="py-2">{{ item.quantity }}</td>
              <td class="py-2">{{ item.unit_price }}</td>
              <td class="py-2 text-right">{{ item.line_total }}</td>
            </tr>
          </tbody>
        </table>
        <div class="flex justify-end text-sm font-medium">
          <span>{{ $t('portal.total') }}: {{ quotation.currency }} {{ quotation.grand_total }}</span>
        </div>
      </div>

      <div v-if="quotation.status === 'accepted'" class="card p-6">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-2">{{ $t('portal.sign.signed_heading') }}</div>
        <div class="text-sm text-ink-muted dark:text-ink-dark-muted mb-2">{{ quotation.signed_name }} · {{ quotation.signed_at?.slice(0, 16).replace('T', ' ') }}</div>
        <img v-if="quotation.signature_data" :src="quotation.signature_data" class="h-20 border border-slate-200 dark:border-slate-700 rounded bg-white" />
      </div>

      <div v-else-if="quotation.status === 'sent'" class="card p-6">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('portal.sign.heading') }}</div>
        <label class="label">{{ $t('portal.sign.name_label') }}</label>
        <input v-model="signedName" class="input text-sm mb-3" />
        <label class="label">{{ $t('portal.sign.signature_label') }}</label>
        <SignaturePad ref="pad" @change="onPadChange" />
        <p v-if="error" class="text-sm text-red-600 mt-2">{{ error }}</p>
        <button class="btn-primary mt-3" :disabled="!signedName || !hasSignature || signing" @click="submitSign">
          {{ signing ? $t('portal.sign.signing') : $t('portal.sign.submit') }}
        </button>
      </div>

      <div v-else class="card p-6 text-sm text-ink-muted dark:text-ink-dark-muted">
        {{ $t('portal.sign.not_signable') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import portalService from '@/services/portal';
import SignaturePad from '@/components/portal/SignaturePad.vue';

const route = useRoute();
const quotation = ref(null);
const loading = ref(true);
const signedName = ref('');
const hasSignature = ref(false);
const signing = ref(false);
const error = ref('');
const pad = ref(null);

function onPadChange(dataUrl) {
  hasSignature.value = !!dataUrl;
}

async function load() {
  try {
    const { data } = await portalService.quotation(route.params.id);
    quotation.value = data.data;
  } catch {
    quotation.value = null;
  } finally {
    loading.value = false;
  }
}

async function submitSign() {
  signing.value = true;
  error.value = '';
  try {
    await portalService.signQuotation(route.params.id, {
      signed_name: signedName.value,
      signature_data: pad.value.dataUrl(),
    });
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not sign this quotation.';
  } finally {
    signing.value = false;
  }
}

onMounted(load);
</script>
