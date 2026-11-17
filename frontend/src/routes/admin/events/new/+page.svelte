<script lang="ts">
  import EventForm from '$lib/components/EventForm.svelte';
</script>

<svelte:head><title>Buat Acara — Invitaku</title></svelte:head>

<div class="space-y-6">
  <div>
    <h2 class="text-xl font-semibold text-stone-900">Buat Acara Baru</h2>
    <p class="text-sm text-stone-500 mt-1">
      Isi data acara, pilih desain undangan, lalu tambahkan tamu setelah acara tersimpan.
    </p>
  </div>

  <EventForm />
</div>
