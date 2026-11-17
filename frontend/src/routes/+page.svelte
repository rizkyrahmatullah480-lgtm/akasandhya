<script lang="ts">
  import { goto } from '$app/navigation';
  import { auth } from '$lib/stores/auth';
  import Spinner from '$lib/components/Spinner.svelte';

  $effect(() => {
    if ($auth.initialized) {
      goto($auth.user ? ($auth.user.role === 'admin' ? '/admin' : '/admin/scanner') : '/login');
    }
  });
</script>

<svelte:head>
  <title>Invitaku — Manajemen Undangan Digital</title>
</svelte:head>

<div class="min-h-screen flex items-center justify-center">
  <Spinner label="Membuka Invitaku..." full />
</div>
