<script lang="ts">
  import { AlertTriangle, X } from '@lucide/svelte';

  interface Props {
    open: boolean;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    type?: 'danger' | 'primary' | 'warning';
    onConfirm: () => void;
    onCancel: () => void;
  }

  let {
    open = false,
    title,
    message,
    confirmText = 'Konfirmasi',
    cancelText = 'Batal',
    type = 'primary',
    onConfirm,
    onCancel
  }: Props = $props();
</script>

{#if open}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <button
      type="button"
      class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity cursor-default"
      onclick={onCancel}
      aria-label="Tutup modal"
    ></button>

    <!-- Modal Content -->
    <div class="relative bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-100 z-10 animate-in fade-in zoom-in-95">
      <div class="flex items-start gap-4">
        <div
          class={`p-3 rounded-xl shrink-0 ${
            type === 'danger'
              ? 'bg-rose-50 text-rose-600'
              : type === 'warning'
              ? 'bg-amber-50 text-amber-600'
              : 'bg-[#7B2D43]/10 text-[#7B2D43]'
          }`}
        >
          <AlertTriangle class="w-6 h-6" />
        </div>

        <div class="flex-1 min-w-0">
          <h3 class="text-lg font-semibold text-stone-900">{title}</h3>
          <p class="text-sm text-stone-600 mt-1.5 leading-relaxed">{message}</p>
        </div>

        <button
          onclick={onCancel}
          class="p-1 rounded-lg text-stone-400 hover:text-stone-700 hover:bg-stone-100 transition-colors"
          aria-label="Tutup"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-stone-100">
        <button
          onclick={onCancel}
          class="px-4 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 rounded-xl transition-colors cursor-pointer"
        >
          {cancelText}
        </button>

        <button
          onclick={onConfirm}
          class={`px-5 py-2 text-sm font-medium text-white rounded-xl shadow-sm transition-colors cursor-pointer ${
            type === 'danger'
              ? 'bg-rose-600 hover:bg-rose-700'
              : type === 'warning'
              ? 'bg-amber-600 hover:bg-amber-700'
              : 'bg-[#7B2D43] hover:bg-[#662436]'
          }`}
        >
          {confirmText}
        </button>
      </div>
    </div>
  </div>
{/if}
