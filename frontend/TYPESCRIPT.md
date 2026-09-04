# NPCRM Frontend — TypeScript adoption

TypeScript is set up for **incremental** adoption: JavaScript and TypeScript coexist, so you
convert files one at a time as you touch them. Nothing needs a big-bang rewrite.

## What's in place
- `tsconfig.json` — `allowJs: true` (JS keeps working), `checkJs: false` (only real TS is
  type-checked), `strict: true`, `@/*` path alias. `src/env.d.ts` shims `.vue` + Vite env.
- `typescript` + `vue-tsc` dev deps; `npm run type-check` (= `vue-tsc --noEmit`).
- **CI enforces types**: `ci.sh` runs `type-check` — a bad type in any converted `.ts`/`lang="ts"`
  file fails the build. Plain `.js` is not checked, so unconverted files never block you.
- Reference conversion: **`src/services/leads.ts`** + domain types in **`src/types/api.ts`**.

## Convert a service (easiest — do these first)
1. Rename `src/services/foo.js` → `foo.ts` (imports elsewhere resolve automatically).
2. Add param types + response generics, mirroring `leads.ts`:
   ```ts
   import http from './http';
   import type { ApiResponse, Id, Foo } from '@/types/api';
   export default {
     show: (id: Id) => http.get<ApiResponse<Foo>>(`/foos/${id}`),
     create: (payload: Partial<Foo>) => http.post<ApiResponse<Foo>>('/foos', payload),
   };
   ```
3. Add the entity's interface to `src/types/api.ts`.

## Convert a component
Add `lang="ts"` to the block: `<script setup lang="ts">`. Then type `defineProps`/`defineEmits`
and any `ref<T>()`. Vue's `<script setup>` (which all but one of your components already use) makes
this clean. Convert page-by-page; each converted file gets type-checked from then on.

## Suggested order (highest value first)
`services/*` → `stores/*` (Pinia) → `composables/*` → `components/*` and `pages/*` per feature.
Expand `src/types/api.ts` as you go. Run `npm run type-check` locally before committing.
