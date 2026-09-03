<template>
  <div class="p-4 md:p-5 max-w-5xl mx-auto">
    <div class="flex items-end justify-between mb-4 gap-3"><div><h1 class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('my_work.title') }}</h1><p class="text-xs text-ink-muted mt-0.5">{{ $t('my_work.subtitle') }}</p></div><button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="load"><RefreshCw :size="12" :class="loading&&'animate-spin'" /> {{ $t('my_work.refresh') }}</button></div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-3"><div v-for="s in summaryItems" :key="s.key" class="card p-3"><div class="text-[11px] text-ink-muted">{{ $t(s.label) }}</div><div class="text-lg font-semibold mt-0.5" :class="s.key==='overdue'&&summary[s.key]?'text-red-600':'text-ink dark:text-ink-dark'">{{ summary[s.key]||0 }}</div></div></div>
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2"><input v-model="filters.q" class="input text-sm flex-1 min-w-48" :placeholder="$t('my_work.search')" @keyup.enter="load" /><select v-model="filters.source" class="input text-sm w-auto" @change="load"><option value="all">{{ $t('my_work.all_sources') }}</option><option value="crm">{{ $t('my_work.crm') }}</option><option value="project">{{ $t('my_work.projects') }}</option></select><select v-model="filters.state" class="input text-sm w-auto" @change="load"><option value="open">{{ $t('my_work.open') }}</option><option value="completed">{{ $t('my_work.completed') }}</option><option value="all">{{ $t('my_work.all') }}</option></select></div>
    <div class="card overflow-hidden">
      <div v-if="loading" class="py-16 text-center text-sm text-ink-subtle">{{ $t('app.loading') }}</div>
      <div v-else-if="!items.length" class="py-16 text-center"><CheckCircle2 :size="30" class="mx-auto text-emerald-500 mb-2" /><div class="text-sm text-ink-muted">{{ $t('my_work.empty') }}</div></div>
      <div v-else><div v-for="item in items" :key="item.key" class="px-3 py-3 border-b last:border-0 border-slate-100 dark:border-slate-700/60 flex gap-3 items-start hover:bg-slate-50 dark:hover:bg-surface-dark-subtle">
        <button class="mt-0.5 w-5 h-5 rounded border flex items-center justify-center shrink-0" :class="item.status==='done'?'bg-emerald-500 border-emerald-500 text-white':'border-slate-300'" :disabled="updating===item.key||!canUpdate(item)" @click="toggle(item)"><Check v-if="item.status==='done'" :size="12" /></button>
        <div class="min-w-0 flex-1"><div class="flex items-center gap-2"><span class="text-sm text-ink dark:text-ink-dark" :class="item.status==='done'&&'line-through text-ink-subtle'">{{ item.title }}</span><span class="text-[9px] uppercase px-1.5 py-0.5 rounded" :class="item.source==='project'?'bg-violet-100 text-violet-700':'bg-sky-100 text-sky-700'">{{ item.source==='project'?$t('my_work.project'):$t('my_work.crm') }}</span><span v-if="item.priority==='urgent'||item.priority==='high'" class="text-[9px] text-red-600 uppercase">{{ item.priority }}</span></div><div class="text-[10px] text-ink-subtle mt-1">{{ item.context }}</div></div>
        <div class="text-[11px] tabular-nums shrink-0" :class="dueClass(item)">{{ dueText(item) }}</div>
        <button v-if="item.source==='project'" class="text-primary-600 p-1" :title="$t('my_work.open_project')" @click="openProject(item)"><ArrowUpRight :size="13" /></button>
      </div></div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import workApi from '@/services/myWork';
import activityApi from '@/services/activities';
import projectApi from '@/services/projects';
import { ArrowUpRight, Check, CheckCircle2, RefreshCw } from 'lucide-vue-next';

const auth=useAuthStore(), router=useRouter();
const items=ref([]), loading=ref(false), updating=ref(null), summary=reactive({});
const filters=reactive({q:'',source:'all',state:'open'});
const summaryItems=[{key:'open',label:'my_work.stats.open'},{key:'overdue',label:'my_work.stats.overdue'},{key:'due_today',label:'my_work.stats.today'},{key:'completed',label:'my_work.stats.completed'}];
async function load(){loading.value=true;try{const {data}=await workApi.list({...filters,q:filters.q||undefined});items.value=data.data?.items||[];Object.assign(summary,data.data?.summary||{});}finally{loading.value=false;}}
const canUpdate=(i)=>i.source==='project'?auth.can('project_tasks.update'):auth.can('activities.update');
async function toggle(item){updating.value=item.key;try{if(item.source==='project')await projectApi.updateTask(item.project_id,item.id,{status:item.status==='done'?'todo':'done'});else if(item.status==='done')await activityApi.updateTask(item.id,{status:'open'});else await activityApi.completeTask(item.id);await load();}finally{updating.value=null;}}
function openProject(item){router.push({name:'projects',query:{project:item.project_id}});}
function dueClass(i){if(!i.due_at)return 'text-ink-subtle';const d=i.due_at.slice(0,10),today=new Date().toISOString().slice(0,10);return i.status!=='done'&&d<today?'text-red-600 font-medium':d===today?'text-amber-600':'text-ink-muted';}
function dueText(i){if(!i.due_at)return '—';const d=i.due_at.slice(0,10),today=new Date().toISOString().slice(0,10);return d===today?'Today':d;}
onMounted(load);
</script>
