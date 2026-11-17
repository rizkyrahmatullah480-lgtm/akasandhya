<script lang="ts">
  import type { Snippet } from 'svelte';
  import { Inbox } from '@lucide/svelte';

  interface Props {
    title: string;
    message?: string;
    action?: Snippet;
    icon?: any;
  }

  let { title, message, action, icon: Icon = Inbox }: Props = $props();
</script>

<div class="flex flex-col items-center justify-center text-center py-16 px-6">
  <div class="w-14 h-14 rounded-2xl bg-stone-100 flex items-center justify-center text-stone-400 mb-4">
    <Icon class="w-7 h-7" />
  </div>
  <h3 class="text-base font-semibold text-stone-800">{title}</h3>
  {#if message}
    <p class="text-sm text-stone-500 mt-1.5 max-w-sm">{message}</p>
  {/if}
  {#if action}
    <div class="mt-5">{@render action()}</div>
  {/if}
</div>
