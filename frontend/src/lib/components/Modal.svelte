<script lang="ts">
  import { X } from '@lucide/svelte';
  import type { Snippet } from 'svelte';

  interface Props {
    open?: boolean;
    title: string;
    description?: string;
    onclose: () => void;
    children?: Snippet;
    footer?: Snippet;
    size?: 'sm' | 'md' | 'lg' | 'xl';
  }

  let { open = false, title, description, onclose, children, footer, size = 'md' }: Props = $props();

  const sizes = {
    sm: 'max-w-md',
    md: 'max-w-xl',
    lg: 'max-w-3xl',
    xl: 'max-w-5xl'
  };
</script>

{#if open}
  <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <button
      type="button"
      class="absolute inset-0 bg-black/45 backdrop-blur-[2px] animate-fade-in"
      onclick={onclose}
      aria-label="Tutup modal"
    ></button>

    <div
      class="relative w-full {sizes[size]} bg-white rounded-2xl shadow-2xl border border-stone-200 max-h-[92vh] flex flex-col animate-scale-in"
      role="dialog"
      aria-modal="true"
      tabindex="-1"
    >
      <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-stone-100 shrink-0">
        <div>
          <h3 class="text-lg font-semibold text-stone-900">{title}</h3>
          {#if description}
            <p class="text-sm text-stone-500 mt-0.5">{description}</p>
          {/if}
        </div>
        <button
          onclick={onclose}
          class="p-2 -mr-1 rounded-lg text-stone-400 hover:text-stone-700 hover:bg-stone-100 transition-colors"
          aria-label="Tutup"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="px-6 py-5 overflow-y-auto">
        {@render children?.()}
      </div>

      {#if footer}
        <div class="px-6 py-4 border-t border-stone-100 bg-stone-50/70 rounded-b-2xl flex items-center justify-end gap-3 shrink-0">
          {@render footer()}
        </div>
      {/if}
    </div>
  </div>
{/if}
