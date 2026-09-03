<template>
  <div>
    <canvas
      ref="canvasEl"
      class="border border-slate-300 dark:border-slate-600 rounded bg-white w-full touch-none"
      style="height: 160px"
      @pointerdown="start"
      @pointermove="draw"
      @pointerup="stop"
      @pointerleave="stop"
    ></canvas>
    <div class="flex justify-between mt-1">
      <span class="text-[11px] text-ink-subtle">{{ $t('portal.sign.draw_hint') }}</span>
      <button type="button" class="text-[11px] text-primary-600 hover:underline" @click="clear">{{ $t('portal.sign.clear') }}</button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const emit = defineEmits(['change']);
const canvasEl = ref(null);
let ctx = null;
let drawing = false;
let hasInk = false;

function resize() {
  const canvas = canvasEl.value;
  const rect = canvas.getBoundingClientRect();
  const dpr = window.devicePixelRatio || 1;
  canvas.width = rect.width * dpr;
  canvas.height = rect.height * dpr;
  ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#1e293b';
}

function pos(e) {
  const rect = canvasEl.value.getBoundingClientRect();
  return { x: e.clientX - rect.left, y: e.clientY - rect.top };
}

function start(e) {
  drawing = true;
  const p = pos(e);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
}

function draw(e) {
  if (!drawing) return;
  const p = pos(e);
  ctx.lineTo(p.x, p.y);
  ctx.stroke();
  hasInk = true;
  emit('change', dataUrl());
}

function stop() { drawing = false; }

function clear() {
  const canvas = canvasEl.value;
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  hasInk = false;
  emit('change', null);
}

function dataUrl() {
  return hasInk ? canvasEl.value.toDataURL('image/png') : null;
}

onMounted(resize);

defineExpose({ dataUrl, isEmpty: () => !hasInk });
</script>
