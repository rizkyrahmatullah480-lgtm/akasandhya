<script lang="ts">
  interface Props {
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    onpage: (page: number) => void;
  }

  let { meta, onpage }: Props = $props();

  let pages = $derived.by(() => {
    const total = meta.last_page;
    const current = meta.current_page;
    const result: number[] = [];
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    for (let i = start; i <= end; i++) result.push(i);
    return result;
  });

  let from = $derived(meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1);
  let to = $derived(Math.min(meta.current_page * meta.per_page, meta.total));
</script>

<div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-stone-200 bg-stone-50/60 text-sm">
  <p class="text-stone-500 text-xs">
    Menampilkan <span class="font-medium text-stone-700">{from}–{to}</span> dari
    <span class="font-medium text-stone-700">{meta.total}</span> data
  </p>

  <div class="flex items-center gap-1">
    <button
      class="px-3 py-1.5 rounded-lg text-xs font-medium text-stone-600 hover:bg-stone-200 disabled:opacity-40 disabled:cursor-not-allowed"
      disabled={meta.current_page <= 1}
      onclick={() => onpage(meta.current_page - 1)}
    >
      Sebelumnya
    </button>

    {#each pages as page (page)}
      <button
        class="w-8 h-8 rounded-lg text-xs font-medium transition-colors {page === meta.current_page
          ? 'bg-[#7b2d43] text-white'
          : 'text-stone-600 hover:bg-stone-200'}"
        onclick={() => onpage(page)}
      >
        {page}
      </button>
    {/each}

    <button
      class="px-3 py-1.5 rounded-lg text-xs font-medium text-stone-600 hover:bg-stone-200 disabled:opacity-40 disabled:cursor-not-allowed"
      disabled={meta.current_page >= meta.last_page}
      onclick={() => onpage(meta.current_page + 1)}
    >
      Berikutnya
    </button>
  </div>
</div>
