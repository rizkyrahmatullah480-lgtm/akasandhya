<script lang="ts">
  import { Loader2 } from '@lucide/svelte';

  interface Props {
    label?: string;
    full?: boolean;
  }

  let { label = 'Memuat data...', full = false }: Props = $props();
</script>

<div class={full ? 'flex flex-col items-center justify-center py-20 text-stone-500' : 'flex items-center justify-center gap-2 text-stone-500 text-sm py-6'}>
  <Loader2 class="w-5 h-5 animate-spin" />
  <span>{label}</span>
</div>
