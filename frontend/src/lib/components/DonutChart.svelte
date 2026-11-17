<script lang="ts">
  interface Segment {
    label: string;
    value: number;
    color: string;
  }

  interface Props {
    segments: Segment[];
    total?: number;
  }

  let { segments, total }: Props = $props();

  let sum = $derived(total ?? segments.reduce((acc, segment) => acc + segment.value, 0));
  let radius = 44;
  let circumference = 2 * Math.PI * radius;

  let offset = $derived.by(() => {
    let acc = 0;
    return segments.map((segment) => {
      const length = sum > 0 ? (segment.value / sum) * circumference : 0;
      const item = { ...segment, length, offset: acc };
      acc += length;
      return item;
    });
  });
</script>

<div class="flex flex-col sm:flex-row items-center gap-6">
  <div class="relative w-36 h-36 shrink-0">
    <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
      <circle cx="60" cy="60" r={radius} fill="none" stroke="#f1efec" stroke-width="14" />
      {#each offset as segment (segment.label)}
        {#if segment.length > 0}
          <circle
            cx="60"
            cy="60"
            r={radius}
            fill="none"
            stroke={segment.color}
            stroke-width="14"
            stroke-linecap="round"
            stroke-dasharray="{Math.max(0, segment.length - 2)} {circumference}"
            stroke-dashoffset={-segment.offset}
          />
        {/if}
      {/each}
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
      <span class="text-2xl font-semibold text-stone-900">{sum}</span>
      <span class="text-[10px] uppercase tracking-wider text-stone-500">Total</span>
    </div>
  </div>

  <div class="flex-1 w-full space-y-2.5">
    {#each segments as segment (segment.label)}
      <div class="flex items-center gap-2 text-sm">
        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {segment.color}"></span>
        <span class="text-stone-600 flex-1">{segment.label}</span>
        <span class="font-medium text-stone-900">{segment.value}</span>
        <span class="text-xs text-stone-400 w-10 text-right">
          {sum > 0 ? Math.round((segment.value / sum) * 100) : 0}%
        </span>
      </div>
    {/each}
  </div>
</div>
