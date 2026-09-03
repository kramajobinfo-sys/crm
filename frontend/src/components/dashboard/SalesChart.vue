<template>
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm font-medium text-ink dark:text-ink-dark">{{ title }}</span>
      <span class="text-xs text-ink-muted dark:text-ink-dark-muted">
        {{ $t('dashboard.charts.last_n_months', { n: months }) }}
      </span>
    </div>
    <div class="h-40">
      <Line v-if="ready" :data="chartData" :options="chartOptions" />
      <div v-else class="h-full flex items-center justify-center text-xs text-ink-subtle">
        {{ $t('app.loading') }}
      </div>
    </div>
    <div class="flex gap-4 text-xs text-ink-muted dark:text-ink-dark-muted mt-2">
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-primary-500" />{{ $t('dashboard.charts.sales') }}
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-500" />{{ $t('dashboard.charts.purchase') }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS, LineElement, PointElement, LinearScale, CategoryScale,
  Tooltip, Filler,
} from 'chart.js';

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);

const props = defineProps({
  title:  { type: String, required: true },
  sales:  { type: Array,  default: () => [] },
  purchase: { type: Array, default: () => [] },
  months: { type: Number, default: 7 },
});

const ready = ref(false);
onMounted(() => (ready.value = true));

const chartData = computed(() => ({
  labels: props.sales.map((p) => p.label),
  datasets: [
    {
      label: 'Sales',
      data: props.sales.map((p) => p.value),
      borderColor: '#378ADD',
      backgroundColor: 'rgba(55, 138, 221, 0.08)',
      fill: true,
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 3,
    },
    {
      label: 'Purchase',
      data: props.purchase.map((p) => p.value),
      borderColor: '#1D9E75',
      backgroundColor: 'transparent',
      fill: false,
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 3,
    },
  ],
}));

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.dataset.label}: $${Number(ctx.raw).toLocaleString()}`,
      },
    },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#94A3B8', font: { size: 10 } } },
    y: {
      grid: { color: 'rgba(148,163,184,0.15)' },
      ticks: {
        color: '#94A3B8', font: { size: 10 },
        callback: (v) => v >= 1000 ? `${v / 1000}k` : v,
      },
      beginAtZero: true,
    },
  },
}));
</script>
