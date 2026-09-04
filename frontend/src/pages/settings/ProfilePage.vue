<template>
  <div class="page">
    <div class="mb-4">
      <h1 class="page-title">{{ $t('profile.title') }}</h1>
      <p class="page-sub">{{ $t('profile.subtitle') }}</p>
    </div>

    <!-- Profile card -->
    <div class="card p-4 mb-4">
      <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('profile.my_profile') }}</div>
      <div class="flex items-center gap-4 mb-4">
        <div class="w-16 h-16 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 flex items-center justify-center text-lg font-medium overflow-hidden shrink-0">
          <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" alt="" />
          <span v-else>{{ auth.initials }}</span>
        </div>
        <div>
          <button class="btn-secondary btn-sm" :disabled="uploading" @click="fileInput?.click()">
            <Upload :size="12" /> {{ uploading ? $t('profile.uploading') : $t('profile.change_photo') }}
          </button>
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="uploadAvatar" />
          <div class="text-[11px] text-ink-subtle mt-1">{{ $t('profile.photo_hint') }}</div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="label">{{ $t('profile.name') }}</label>
          <input v-model="profile.name" class="input text-sm" />
          <p v-if="pErrors.name" class="text-[11px] text-red-500">{{ pErrors.name[0] }}</p></div>
        <div><label class="label">{{ $t('profile.email') }}</label>
          <input :value="auth.user?.email" class="input text-sm bg-slate-50 dark:bg-surface-dark-subtle" disabled /></div>
        <div><label class="label">{{ $t('profile.phone') }}</label><input v-model="profile.phone" class="input text-sm" /></div>
        <div><label class="label">{{ $t('profile.language') }}</label>
          <select v-model="profile.language" class="input text-sm"><option value="en">English</option><option value="ar">العربية</option></select></div>
        <div><label class="label">{{ $t('profile.company') }}</label>
          <input :value="auth.company?.name" class="input text-sm bg-slate-50 dark:bg-surface-dark-subtle" disabled /></div>
      </div>
      <div v-if="auth.roles.length" class="mt-3">
        <span class="text-[11px] text-ink-subtle">{{ $t('profile.roles') }}:</span>
        <span v-for="r in auth.roles" :key="r" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 ml-1">{{ r }}</span>
      </div>
      <div class="flex justify-end mt-4">
        <button class="btn-primary btn-sm" :disabled="savingProfile" @click="saveProfile">{{ savingProfile ? $t('profile.saving') : $t('profile.save') }}</button>
      </div>
    </div>

    <!-- Change password card -->
    <div id="password" class="card p-4">
      <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('profile.change_password') }}</div>
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="label">{{ $t('profile.current_password') }}</label>
          <input v-model="pwd.current_password" type="password" autocomplete="current-password" class="input text-sm" />
          <p v-if="pwErrors.current_password" class="text-[11px] text-red-500">{{ pwErrors.current_password[0] }}</p></div>
        <div><label class="label">{{ $t('profile.new_password') }}</label>
          <input v-model="pwd.new_password" type="password" autocomplete="new-password" class="input text-sm" />
          <p v-if="pwErrors.new_password" class="text-[11px] text-red-500">{{ pwErrors.new_password[0] }}</p></div>
        <div><label class="label">{{ $t('profile.confirm_password') }}</label>
          <input v-model="pwd.new_password_confirmation" type="password" autocomplete="new-password" class="input text-sm" /></div>
      </div>
      <p class="text-[11px] text-ink-subtle mt-2">{{ $t('profile.password_hint') }}</p>
      <div class="flex justify-end mt-4">
        <button class="btn-primary btn-sm" :disabled="savingPwd || !pwd.current_password || !pwd.new_password" @click="savePassword">{{ savingPwd ? $t('profile.saving') : $t('profile.update_password') }}</button>
      </div>
    </div>

    <!-- Two-factor authentication -->
    <div id="two-factor" class="card p-4 mt-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('profile.two_factor') }}</div>
          <div class="text-[11px] text-ink-subtle mt-0.5">{{ $t('profile.two_factor_hint') }}</div>
        </div>
        <span class="badge shrink-0" :class="is2faOn
          ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
          : 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300'">
          {{ is2faOn ? $t('profile.status_enabled') : $t('profile.status_disabled') }}
        </span>
      </div>

      <!-- ON: offer disable (password required) -->
      <div v-if="is2faOn && !tfa.setup" class="mt-3">
        <div v-if="!tfa.disabling">
          <button class="btn-secondary btn-sm" @click="tfa.disabling = true">{{ $t('profile.disable_2fa') }}</button>
        </div>
        <div v-else class="space-y-2">
          <label class="label">{{ $t('profile.current_password') }} *</label>
          <input v-model="tfa.password" type="password" autocomplete="current-password" class="input text-sm max-w-xs" />
          <p class="text-[11px] text-ink-subtle">{{ $t('profile.disable_prompt') }}</p>
          <p v-if="tfa.error" class="text-[11px] text-red-500">{{ tfa.error }}</p>
          <div class="flex gap-2">
            <button class="btn-secondary btn-sm" @click="resetTfa">{{ $t('profile.cancel') }}</button>
            <button class="btn-primary btn-sm" :disabled="!tfa.password || tfa.busy" @click="doDisable">
              {{ tfa.busy ? $t('profile.saving') : $t('profile.disable_2fa') }}
            </button>
          </div>
        </div>
      </div>

      <!-- OFF: start setup -->
      <div v-else-if="!is2faOn && !tfa.setup" class="mt-3">
        <button class="btn-primary btn-sm" :disabled="tfa.busy" @click="startSetup">
          {{ tfa.busy ? $t('profile.saving') : $t('profile.enable_2fa') }}
        </button>
      </div>

      <!-- Setup panel -->
      <div v-if="tfa.setup" class="mt-3 space-y-3 border-t border-slate-100 dark:border-slate-700/60 pt-3">
        <div class="text-[11px] text-ink-muted">{{ $t('profile.setup_step1') }}</div>

        <div>
          <label class="label">{{ $t('profile.secret_label') }}</label>
          <div class="flex items-center gap-2 flex-wrap">
            <code class="text-sm font-mono tracking-widest bg-slate-50 dark:bg-surface-dark-subtle px-2 py-1 rounded select-all">{{ groupedSecret }}</code>
            <button class="btn-secondary btn-xs" @click="copySecret">
              {{ tfa.copied ? $t('profile.copied') : $t('profile.copy') }}
            </button>
          </div>
          <a v-if="tfa.setup.qr_url" :href="tfa.setup.qr_url" class="text-[11px] text-primary-600 hover:underline inline-block mt-1.5">
            {{ $t('profile.open_in_app') }}
          </a>
          <div class="text-[11px] text-ink-subtle mt-1">{{ $t('profile.manual_hint') }}</div>
        </div>

        <div>
          <div class="text-[11px] text-ink-muted mb-1">{{ $t('profile.setup_step2') }}</div>
          <label class="label">{{ $t('profile.code_label') }} *</label>
          <input v-model="tfa.code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                 class="input text-sm max-w-[10rem] text-center tracking-[0.4em]" placeholder="••••••" />
          <p v-if="tfa.error" class="text-[11px] text-red-500 mt-1">{{ tfa.error }}</p>
        </div>

        <div class="flex gap-2">
          <button class="btn-secondary btn-sm" @click="resetTfa">{{ $t('profile.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="tfa.code.length !== 6 || tfa.busy" @click="doConfirm">
            {{ tfa.busy ? $t('profile.confirming') : $t('profile.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/auth';
import { Upload } from 'lucide-vue-next';

const toast = useToast();
const { locale } = useI18n();
const { t } = useI18n();
const auth = useAuthStore();

const fileInput = ref(null);
const uploading = ref(false);
const savingProfile = ref(false);
const savingPwd = ref(false);
const pErrors = ref({});
const pwErrors = ref({});
const profile = reactive({ name: '', phone: '', language: 'en' });
const pwd = reactive({ current_password: '', new_password: '', new_password_confirmation: '' });

function syncFromUser() {
  profile.name = auth.user?.name || '';
  profile.phone = auth.user?.phone || '';
  profile.language = auth.user?.language || 'en';
}

async function saveProfile() {
  savingProfile.value = true; pErrors.value = {};
  try {
    const { data } = await api.updateProfile({ name: profile.name, phone: profile.phone || null, language: profile.language });
    auth.setUser(data.data);
    if (profile.language && locale.value !== profile.language) locale.value = profile.language;
    toast.success(t('profile.saved_ok'));
  } catch (e) { if (e.response?.status === 422) pErrors.value = e.response.data?.errors || {}; }
  finally { savingProfile.value = false; }
}

async function uploadAvatar(e) {
  const file = e.target.files?.[0];
  e.target.value = '';
  if (!file) return;
  uploading.value = true;
  try {
    const fd = new FormData();
    fd.append('avatar', file);
    const { data } = await api.uploadAvatar(fd);
    auth.setUser(data.data);
    toast.success(t('profile.photo_updated'));
  } catch (err) { if (err.response?.status === 422) toast.error(t('profile.photo_rejected')); }
  finally { uploading.value = false; }
}

async function savePassword() {
  savingPwd.value = true; pwErrors.value = {};
  try {
    await api.changePassword({ ...pwd });
    pwd.current_password = ''; pwd.new_password = ''; pwd.new_password_confirmation = '';
    toast.success(t('profile.password_updated'));
  } catch (e) { if (e.response?.status === 422) pwErrors.value = e.response.data?.errors || {}; }
  finally { savingPwd.value = false; }
}

// ---- two-factor authentication ------------------------------------------------
// Enforcement is real (RequireTwoFactor gates the whole authenticated route group on a
// `twofa` JWT claim), but until now the enable/confirm/disable endpoints had no UI at all,
// so 2FA could only be switched on through the API.
const tfa = reactive({ setup: null, code: '', password: '', error: '', busy: false, copied: false, disabling: false });

const is2faOn = computed(() => !!auth.user?.two_factor_enabled);

// Base32 in groups of four — a 16-char secret is going to be typed by hand on a phone.
const groupedSecret = computed(() =>
  (tfa.setup?.secret || '').replace(/(.{4})/g, '$1 ').trim());

function resetTfa() {
  Object.assign(tfa, { setup: null, code: '', password: '', error: '', busy: false, copied: false, disabling: false });
}

async function startSetup() {
  tfa.busy = true; tfa.error = '';
  try {
    const { data } = await api.twoFactor.enable();
    tfa.setup = data.data;   // { secret, qr_url, issuer }
  } catch (e) {
    tfa.error = e.response?.data?.message || t('profile.two_factor_failed');
  } finally { tfa.busy = false; }
}

async function copySecret() {
  try {
    await navigator.clipboard.writeText(tfa.setup?.secret || '');
    tfa.copied = true;
    setTimeout(() => { tfa.copied = false; }, 1500);
  } catch { /* clipboard blocked (http origin / permissions) — the code is select-all anyway */ }
}

async function doConfirm() {
  tfa.busy = true; tfa.error = '';
  try {
    // The store swaps in the verified token the server returns; without that the token we
    // hold predates two_factor_enabled becoming true and every gated route would 403.
    await auth.confirmTwoFactor(tfa.code);
    resetTfa();
    syncFromUser();
    toast.success(t('profile.two_factor_enabled'));
  } catch (e) {
    tfa.error = e.response?.data?.message || t('profile.invalid_code');
  } finally { tfa.busy = false; }
}

async function doDisable() {
  tfa.busy = true; tfa.error = '';
  try {
    await auth.disableTwoFactor(tfa.password);
    resetTfa();
    syncFromUser();
    toast.success(t('profile.two_factor_disabled'));
  } catch (e) {
    tfa.error = e.response?.data?.message || e.response?.data?.errors?.password?.[0] || t('profile.two_factor_failed');
  } finally { tfa.busy = false; }
}

onMounted(() => {
  syncFromUser();
  // Refresh from the server in case persisted user is stale.
  auth.loadMe().then(syncFromUser).catch(() => {});
});
</script>
