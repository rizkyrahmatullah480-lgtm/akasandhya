<script lang="ts">
  import type { DeliveryStatus } from '$lib/types';

  interface Props {
    status: DeliveryStatus;
  }

  let { status }: Props = $props();

  const config: Record<DeliveryStatus, { label: string; cls: string; dot: string }> = {
    not_sent: {
      label: 'Belum dikirim',
      cls: 'bg-stone-100 text-stone-600 border-stone-200',
      dot: 'bg-stone-400'
    },
    queued: {
      label: 'Menunggu',
      cls: 'bg-amber-50 text-amber-700 border-amber-200',
      dot: 'bg-amber-500'
    },
    sent: {
      label: 'Terkirim',
      cls: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      dot: 'bg-emerald-500'
    },
    failed: {
      label: 'Gagal',
      cls: 'bg-rose-50 text-rose-700 border-rose-200',
      dot: 'bg-rose-500'
    }
  };

  let item = $derived(config[status] ?? config.not_sent);
</script>

<span class="badge border {item.cls}">
  <span class="w-1.5 h-1.5 rounded-full {item.dot}"></span>
  {item.label}
</span>
