<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import EventForm from '$lib/components/EventForm.svelte';
  import Spinner from '$lib/components/Spinner.svelte';
  import type { EventModel } from '$lib/types';

  let event = $state<EventModel | null>(null);
  let loading = $state(true);
  let error = $state('');

  let id = $derived(page.params.id);

  onMount(async () => {
    if (!id) {
      error = 'Acara tidak ditemukan.';
      loading = false;
      return;
    }
    try {
      const res = await api.getEvent(id);
      event = res.data.event;
    } catch (err: any) {
      error = err.message || 'Acara tidak ditemukan.';
    } finally {
      loading = false;
    }
  });
</script>

<svelte:head><title>Edit Acara — Invitaku</title></svelte:head>

<div class="space-y-6">
  <div>
    <h2 class="text-xl font-semibold text-stone-900">Edit Acara</h2>
    <p class="text-sm text-stone-500 mt-1">Perbarui informasi acara, desain, atau banner.</p>
  </div>

  {#if loading}
    <Spinner label="Memuat acara..." full />
  {:else if error}
    <div class="card p-8 text-center text-sm text-rose-600">{error}</div>
  {:else if event}
    <EventForm {event} />
  {/if}
</div>
