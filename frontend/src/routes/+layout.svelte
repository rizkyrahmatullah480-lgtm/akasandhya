<script lang="ts">
  import './layout.css';
  import { onMount } from 'svelte';
  import { auth } from '$lib/stores/auth';
  import Toast from '$lib/components/Toast.svelte';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();

  onMount(() => {
    auth.init();
  });
</script>

<svelte:head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</svelte:head>

<Toast />

{@render children()}
