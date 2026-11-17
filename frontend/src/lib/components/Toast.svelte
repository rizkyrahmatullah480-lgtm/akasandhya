<script lang="ts">
  import { toast } from '$lib/stores/toast';
  import { CheckCircle2, AlertTriangle, AlertCircle, Info, X } from '@lucide/svelte';

  const toasts = toast;
</script>

<div class="fixed top-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none">
  {#each $toasts as item (item.id)}
    <div
      class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-lg border backdrop-blur-md transition-all duration-300 animate-in fade-in slide-in-from-top-4"
      class:bg-emerald-50={item.type === 'success'}
      class:border-emerald-200={item.type === 'success'}
      class:text-emerald-900={item.type === 'success'}
      class:bg-rose-50={item.type === 'error'}
      class:border-rose-200={item.type === 'error'}
      class:text-rose-900={item.type === 'error'}
      class:bg-amber-50={item.type === 'warning'}
      class:border-amber-200={item.type === 'warning'}
      class:text-amber-900={item.type === 'warning'}
      class:bg-white={item.type === 'info'}
      class:border-stone-200={item.type === 'info'}
      class:text-stone-800={item.type === 'info'}
    >
      <div class="mt-0.5 shrink-0">
        {#if item.type === 'success'}
          <CheckCircle2 class="w-5 h-5 text-emerald-600" />
        {:else if item.type === 'error'}
          <AlertCircle class="w-5 h-5 text-rose-600" />
        {:else if item.type === 'warning'}
          <AlertTriangle class="w-5 h-5 text-amber-600" />
        {:else}
          <Info class="w-5 h-5 text-[#7B2D43]" />
        {/if}
      </div>

      <div class="flex-1 min-w-0">
        {#if item.title}
          <h4 class="font-semibold text-sm leading-snug">{item.title}</h4>
        {/if}
        <p class="text-xs leading-relaxed opacity-90 mt-0.5 break-words">{item.message}</p>
      </div>

      <button
        onclick={() => toast.dismiss(item.id)}
        class="shrink-0 p-1 rounded-lg opacity-60 hover:opacity-100 hover:bg-black/5 transition-colors"
        aria-label="Tutup"
      >
        <X class="w-4 h-4" />
      </button>
    </div>
  {/each}
</div>
