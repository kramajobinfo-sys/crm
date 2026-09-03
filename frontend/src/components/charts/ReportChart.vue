<template>
  <div :style="{ height: height + 'px' }">
    <Bar v-if="type === 'bar'" :data="chartData" :options="chartOptions" />
    <Line v-else-if="type === 'line'" :data="chartData" :options="chartOptions" />
    <Pie v-else-if="type === 'pie'" :data="pieData" :options="pieOptions" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Bar, Line, Pie } from 'vue-chartjs';
import {
  Chart as ChartJS, BarElement, LineElement, PointElement, ArcElement,
  LinearScale, CategoryScale, Tooltip, Legend, Filler,
} from 'chart.js';

ChartJS.register(BarElement, LineElement, PointElement, ArcElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

const props = defineProps({
  type: { type: String, default: 'bar' }, // bar | line | pie
  result: { type: Object, required: true }, // { columns, rows }
  height: { type: Number, default: 220 },
});

const PALETTE = ['#378ADD', '#1D9E75', '#F59E0B', '#EF4444', '#8B5CF6', '#0EA5E9'];

const measureColumns = computed(() => (props.result.columns || []).filter((c) => c.key !== 'dimension'));
const labels = computed(() => (props.result.rows || []).map((r) => String(r.dimension ?? '—')));

const chartData = computed(() => ({
  labels: labels.value,
  datasets: measureColumns.value.map((c, i) => ({
    label: c.label,
    data: (props.result.rows || []).map((r) => Number(r[c.key]) || 0),
    backgroundColor: props.type === 'bar' ? PALETTE[i % PALETTE.length] : `${PALETTE[i % PALETTE.length]}22`,
    borderColor: PALETTE[i % PALETTE.length],
    borderWidth: 2,
    fill: props.type === 'line',
    tension: 0.35,
    pointRadius: props.type === 'line' ? 3 : 0,
  })),
}));

// Pie only makes sense for a single measure — parts of one whole.
const pieData = computed(() => {
  const c = measureColumns.value[0];
  return {
    labels: labels.value,
    datasets: [{
      data: (props.result.rows || []).map((r) => Number(r[c?.key]) || 0),
      backgroundColor: labels.value.map((_, i) => PALETTE[i % PALETTE.length]),
    }],
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#94A3B8', font: { size: 10 } } },
    y: {
      grid: { color: 'rgba(148,163,184,0.15)' },
      ticks: { color: '#94A3B8', font: { size: 10 } },
      beginAtZero: true,
    },
  },
};

const pieOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } },
};
</script>
