<script lang="ts">
  interface Props {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: any;
    tone?: 'primary' | 'emerald' | 'amber' | 'sky' | 'rose' | 'stone';
  }

  let { title, value, subtitle, icon: Icon, tone = 'primary' }: Props = $props();

  const tones: Record<string, string> = {
    primary: 'bg-[#7b2d43]/10 text-[#7b2d43]',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    sky: 'bg-sky-50 text-sky-600',
    rose: 'bg-rose-50 text-rose-600',
    stone: 'bg-stone-100 text-stone-600'
  };
</script>

<div class="card p-5 hover:shadow-md transition-shadow">
  <div class="flex items-start justify-between gap-3">
    <div class="min-w-0">
      <p class="text-xs font-medium uppercase tracking-wider text-stone-500">{title}</p>
      <p class="mt-2 text-3xl font-semibold text-stone-900 leading-none">{value}</p>
      {#if subtitle}
        <p class="mt-2 text-xs text-stone-500">{subtitle}</p>
      {/if}
    </div>
    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 {tones[tone]}">
      <Icon class="w-5 h-5" />
    </div>
  </div>
</div>
