<script lang="ts">
  interface Item {
    label: string;
    value: number;
    secondary?: number;
  }

  interface Props {
    items: Item[];
    height?: number;
  }

  let { items, height = 180 }: Props = $props();

  let max = $derived(Math.max(1, ...items.map((item) => item.value)));
</script>

<div class="w-full overflow-x-auto">
  <div class="flex items-end gap-3 min-w-full" style="height: {height}px">
    {#each items as item (item.label)}
      <div class="flex-1 min-w-[52px] h-full flex flex-col items-center justify-end gap-2 group">
        <div class="w-full flex items-end justify-center gap-1 h-full">
          <div
            class="w-full max-w-[34px] rounded-t-lg bg-[#7b2d43]/85 group-hover:bg-[#7b2d43] transition-all relative"
            style="height: {Math.max(4, (item.value / max) * 100)}%"
            title="{item.label}: {item.value} tamu"
          >
            <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-medium text-stone-600 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
              {item.value}
            </span>
          </div>
          {#if item.secondary !== undefined}
            <div
              class="w-full max-w-[34px] rounded-t-lg bg-emerald-400/80 group-hover:bg-emerald-500 transition-all"
              style="height: {Math.max(2, (item.secondary / max) * 100)}%"
              title="{item.label}: {item.secondary} hadir"
            ></div>
          {/if}
        </div>
        <p class="text-[10px] text-stone-500 text-center leading-tight line-clamp-2 w-full" title={item.label}>
          {item.label}
        </p>
      </div>
    {/each}
  </div>
</div>
