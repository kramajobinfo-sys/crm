/// <reference types="vite/client" />

// Lets TypeScript understand .vue single-file components.
declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
  export default component;
}
