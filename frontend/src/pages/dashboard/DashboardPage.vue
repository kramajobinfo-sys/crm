<template>
  <div class="p-4 md:p-5 max-w-[1400px] mx-auto">

    <!-- Greeting header -->
    <div class="flex items-end justify-between mb-4">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">
          {{ greeting }}, {{ auth.firstName }}
        </div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">
          {{ $t('dashboard.subtitle', { company: auth.company?.name || 'Krama' }) }}
        </div>
      </div>
      <div class="flex gap-2">
        <span class="btn-secondary text-xs px-2.5 py-1">{{ $t('dashboard.this_month') }}</span>
        <button class="btn-secondary text-xs px-2.5 py-1">
          <Download :size="12" /> {{ $t('dashboard.export') }}
        </button>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">
      {{ $t('app.loading') }}
    </div>

    <div v-else class="space-y-3">

      <!-- KPI row -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
        <KpiCard
          :label="$t('dashboard.kpi.revenue')"
          :value="data.kpis?.revenue?.value ?? 0"
          format="currency"
          :currency="data.kpis?.revenue?.currency || 'USD'"
          :subtitle="revenueSubtitle"
          :trend="revenueTrend"
          :icon="TrendingUp"
        />
        <KpiCard
          :label="$t('dashboard.kpi.new_leads')"
          :value="data.kpis?.new_leads?.value ?? 0"
          :subtitle="`+${data.kpis?.new_leads?.change_pct ?? 0}% ${$t('dashboard.kpi.growth')}`"
          trend="up"
          :icon="Users"
        />
        <KpiCard
          :label="$t('dashboard.kpi.open_deals')"
          :value="data.kpis?.open_deals?.value ?? 0"
          :subtitle="$t('dashboard.kpi.pipeline_value', { amount: formatK(data.kpis?.open_deals?.pipeline_value) })"
          :icon="Target"
        />
        <KpiCard
          :label="$t('dashboard.kpi.tickets')"
          :value="data.kpis?.tickets?.open ?? 0"
          :subtitle="ticketsSubtitle"
          :trend="data.kpis?.tickets?.breaching_sla ? 'down' : 'neutral'"
          :icon="Headphones"
        />
      </div>

      <!-- Middle row: charts + pipeline -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-2.5">
        <div class="lg:col-span-3">
          <SalesChart
            :title="$t('dashboard.charts.sales_vs_purchase')"
            :sales="data.sales_chart"
            :purchase="data.purchase_chart"
            :months="7"
          />
        </div>
        <div class="lg:col-span-2">
          <PipelineWidget :stages="data.pipeline" />
        </div>
      </div>

      <!-- Bottom row: performers + tasks + AI -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-2.5">
        <TopPerformers :items="data.top_performers" />
        <TasksWidget :summary="data.tasks" />
        <AiInsightCard :insights="data.ai_insights" />
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import dashboardService from '@/services/dashboard';

import KpiCard        from '@/components/dashboard/KpiCard.vue';
import SalesChart     from '@/components/dashboard/SalesChart.vue';
import PipelineWidget from '@/components/dashboard/PipelineWidget.vue';
import TopPerformers  from '@/components/dashboard/TopPerformers.vue';
import TasksWidget    from '@/components/dashboard/TasksWidget.vue';
import AiInsightCard  from '@/components/dashboard/AiInsightCard.vue';

import { TrendingUp, Users, Target, Headphones, Download } from 'lucide-vue-next';

const { t } = useI18n();
const auth = useAuthStore();

const loading = ref(true);
const data = ref({
  kpis: {}, sales_chart: [], purchase_chart: [], pipeline: [],
  top_performers: [], tasks: { items: [], overdue: 0 }, ai_insights: [],
});

const load = async () => {
  loading.value = true;
  try {
    // Kick everything in parallel — the backend caches so this is cheap.
    const [summary, purchase] = await Promise.all([
      dashboardService.summary(),
      dashboardService.purchaseChart(7),
    ]);
    data.value = {
      ...summary.data.data,
      purchase_chart: purchase.data.data,
    };
  } finally {
    loading.value = false;
  }
};

onMounted(load);
if (!auth.user) auth.loadMe();

// Greeting
const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return t('dashboard.greeting_morning');
  if (h < 18) return t('dashboard.greeting_afternoon');
  return t('dashboard.greeting_evening');
});

const revenueTrend = computed(() => {
  const c = data.value.kpis?.revenue?.change_pct;
  if (c == null) return 'neutral';
  return c >= 0 ? 'up' : 'down';
});

const revenueSubtitle = computed(() => {
  const c = data.value.kpis?.revenue?.change_pct;
  if (c == null) return t('dashboard.this_month');
  const sign = c >= 0 ? '+' : '';
  return `${sign}${c}% ${t('dashboard.kpi.vs_last_month')}`;
});

const ticketsSubtitle = computed(() => {
  const n = data.value.kpis?.tickets?.breaching_sla ?? 0;
  return n ? t('dashboard.kpi.breaching_sla', { n }) : 'All within SLA';
});

const formatK = (n) => {
  const v = Number(n || 0);
  if (v >= 1000000) return `$${(v / 1000000).toFixed(1)}M`;
  if (v >= 1000)    return `$${(v / 1000).toFixed(0)}K`;
  return `$${v.toFixed(0)}`;
};
</script>
