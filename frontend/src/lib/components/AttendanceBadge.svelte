<script lang="ts">
  interface Props {
    checkedIn: boolean;
    compact?: boolean;
  }

  let { checkedIn, compact = false }: Props = $props();
</script>

{#if checkedIn}
  <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200 {compact ? '' : 'px-3'}">
    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Hadir
  </span>
{:else}
  <span class="badge bg-stone-100 text-stone-600 border border-stone-200 {compact ? '' : 'px-3'}">
    <span class="w-1.5 h-1.5 rounded-full bg-stone-400"></span> Belum Hadir
  </span>
{/if}
