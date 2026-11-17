<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { goto } from '$app/navigation';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { EventModel, Template } from '$lib/types';
  import { Loader2, Upload, Image as ImageIcon, ArrowLeft } from '@lucide/svelte';

  interface Props {
    event?: EventModel | null;
  }

  let { event = null }: Props = $props();

  let isEdit = $derived(!!event?.id);

  // Capture the initial event once so form fields start with existing values.
  // The component is mounted after the parent has loaded the event.
  const initialEvent = untrack(() => event);

  let name = $state(initialEvent?.name ?? '');
  let eventType = $state(initialEvent?.event_type ?? 'pernikahan');
  let organizer = $state(initialEvent?.organizer ?? '');
  let eventDate = $state(initialEvent?.event_date?.slice(0, 10) ?? '');
  let eventTime = $state(initialEvent?.event_time?.slice(0, 5) ?? '');
  let location = $state(initialEvent?.location ?? '');
  let address = $state(initialEvent?.address ?? '');
  let description = $state(initialEvent?.description ?? '');
  let templateId = $state<string>(initialEvent?.template_id ? String(initialEvent.template_id) : '');
  let bannerFile = $state<File | null>(null);
  let bannerPreview = $state(initialEvent?.banner_url ?? '');
  let saving = $state(false);
  let error = $state('');
  let fieldErrors = $state<Record<string, string[]>>({});
  let templates = $state<Template[]>([]);

  const eventTypes = [
    { value: 'pernikahan', label: 'Pernikahan' },
    { value: 'ulang_tahun', label: 'Ulang Tahun' },
    { value: 'seminar', label: 'Seminar' },
    { value: 'wisuda', label: 'Wisuda' },
    { value: 'santri', label: 'Acara Santri' },
    { value: 'formal', label: 'Acara Formal' },
    { value: 'keluarga', label: 'Acara Keluarga' },
    { value: 'lainnya', label: 'Acara Lainnya' }
  ];

  onMount(async () => {
    try {
      const res = await api.getTemplates();
      templates = res.data;
    } catch {
      // not fatal for the form
    }
  });

  function onBannerChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    bannerFile = file;
    if (file) bannerPreview = URL.createObjectURL(file);
  }

  async function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    saving = true;
    error = '';
    fieldErrors = {};

    const payload = {
      name: name.trim(),
      event_type: eventType,
      organizer: organizer.trim(),
      event_date: eventDate,
      event_time: eventTime,
      location: location.trim(),
      address: address.trim() || null,
      description: description.trim() || null,
      template_id: templateId ? Number(templateId) : null
    };

    try {
      const res = isEdit
        ? await api.updateEvent(event!.id, payload)
        : await api.createEvent(payload);

      const saved = res.data;

      if (bannerFile) {
        await api.uploadEventBanner(saved.id, bannerFile);
      }

      toast.success(isEdit ? 'Acara berhasil diperbarui.' : 'Acara berhasil dibuat.');
      goto(`/admin/events/${saved.id}`);
    } catch (err: any) {
      error = err.message || 'Gagal menyimpan acara.';
      fieldErrors = err.errors ?? {};
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      saving = false;
    }
  }

  function fieldError(key: string) {
    return fieldErrors[key]?.[0];
  }
</script>

<form class="space-y-6" onsubmit={handleSubmit}>
  <div class="flex items-center justify-between gap-3">
    <a href={isEdit ? `/admin/events/${event!.id}` : '/admin/events'} class="btn-ghost text-sm">
      <ArrowLeft class="w-4 h-4" /> Kembali
    </a>
    <div class="flex items-center gap-2">
      <a href={isEdit ? `/admin/events/${event!.id}` : '/admin/events'} class="btn-secondary text-sm">Batal</a>
      <button type="submit" class="btn-primary text-sm" disabled={saving}>
        {#if saving}
          <Loader2 class="w-4 h-4 animate-spin" /> Menyimpan...
        {:else}
          {isEdit ? 'Simpan Perubahan' : 'Buat Acara'}
        {/if}
      </button>
    </div>
  </div>

  {#if error}
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {error}
      {#if Object.keys(fieldErrors).length}
        <ul class="mt-2 list-disc list-inside text-xs space-y-0.5">
          {#each Object.values(fieldErrors).flat() as message}
            <li>{message}</li>
          {/each}
        </ul>
      {/if}
    </div>
  {/if}

  <div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
      <section class="card p-5 sm:p-6">
        <h2 class="font-semibold text-stone-900">Informasi Acara</h2>
        <p class="text-sm text-stone-500 mt-0.5">Data ini akan tampil pada undangan tamu.</p>

        <div class="mt-5 grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="label" for="name">Nama Acara <span class="text-rose-500">*</span></label>
            <input id="name" class="input" bind:value={name} placeholder="Contoh: Pernikahan Rian & Sinta" required />
            {#if fieldError('name')}<p class="text-xs text-rose-600 mt-1">{fieldError('name')}</p>{/if}
          </div>

          <div>
            <label class="label" for="event_type">Jenis Acara <span class="text-rose-500">*</span></label>
            <select id="event_type" class="select" bind:value={eventType}>
              {#each eventTypes as type (type.value)}
                <option value={type.value}>{type.label}</option>
              {/each}
            </select>
          </div>

          <div>
            <label class="label" for="organizer">Penyelenggara <span class="text-rose-500">*</span></label>
            <input id="organizer" class="input" bind:value={organizer} placeholder="Nama keluarga / instansi" required />
            {#if fieldError('organizer')}<p class="text-xs text-rose-600 mt-1">{fieldError('organizer')}</p>{/if}
          </div>

          <div>
            <label class="label" for="event_date">Tanggal <span class="text-rose-500">*</span></label>
            <input id="event_date" type="date" class="input" bind:value={eventDate} required />
            {#if fieldError('event_date')}<p class="text-xs text-rose-600 mt-1">{fieldError('event_date')}</p>{/if}
          </div>

          <div>
            <label class="label" for="event_time">Waktu <span class="text-rose-500">*</span></label>
            <input id="event_time" type="time" class="input" bind:value={eventTime} required />
            {#if fieldError('event_time')}<p class="text-xs text-rose-600 mt-1">{fieldError('event_time')}</p>{/if}
          </div>

          <div class="sm:col-span-2">
            <label class="label" for="location">Lokasi <span class="text-rose-500">*</span></label>
            <input id="location" class="input" bind:value={location} placeholder="Nama gedung / tempat" required />
            {#if fieldError('location')}<p class="text-xs text-rose-600 mt-1">{fieldError('location')}</p>{/if}
          </div>

          <div class="sm:col-span-2">
            <label class="label" for="address">Alamat Lengkap</label>
            <textarea id="address" class="input min-h-20" bind:value={address} placeholder="Alamat lengkap untuk Google Maps"></textarea>
          </div>

          <div class="sm:col-span-2">
            <label class="label" for="description">Deskripsi Acara</label>
            <textarea
              id="description"
              class="input min-h-28"
              bind:value={description}
              placeholder="Tuliskan informasi tambahan atau pesan untuk tamu..."
            ></textarea>
          </div>
        </div>
      </section>
    </div>

    <div class="space-y-6">
      <section class="card p-5">
        <h2 class="font-semibold text-stone-900">Desain Undangan</h2>
        <p class="text-sm text-stone-500 mt-0.5">Pilih desain yang digunakan acara ini.</p>

        <div class="mt-4">
          <label class="label" for="template">Desain</label>
          <select id="template" class="select" bind:value={templateId}>
            <option value="">— Belum dipilih —</option>
            {#each templates as template (template.id)}
              <option value={String(template.id)}>
                {template.name} · {template.category?.name ?? 'Umum'}
              </option>
            {/each}
          </select>
        </div>

        {#if templateId}
          {@const selected = templates.find((t) => String(t.id) === templateId)}
          {#if selected?.preview_image}
            <a href="/admin/templates" class="mt-4 block rounded-xl overflow-hidden border border-stone-200 group">
              <img src={selected.preview_image} alt={selected.name} class="w-full h-40 object-cover group-hover:scale-[1.02] transition-transform" />
              <p class="px-3 py-2 text-xs text-stone-600 bg-stone-50">{selected.name}</p>
            </a>
          {/if}
        {/if}

        <a href="/admin/templates" class="mt-3 text-xs font-medium text-[#7b2d43] hover:underline inline-block">
          Lihat galeri desain →
        </a>
      </section>

      <section class="card p-5">
        <h2 class="font-semibold text-stone-900">Banner Acara</h2>
        <p class="text-sm text-stone-500 mt-0.5">Opsional. Format JPG, PNG, atau WEBP maks. 4MB.</p>

        <div class="mt-4 rounded-xl border-2 border-dashed border-stone-300 overflow-hidden">
          {#if bannerPreview}
            <img src={bannerPreview} alt="Preview banner" class="w-full h-40 object-cover" />
          {:else}
            <div class="h-40 flex flex-col items-center justify-center text-stone-400">
              <ImageIcon class="w-8 h-8 mb-2" />
              <p class="text-xs">Belum ada banner</p>
            </div>
          {/if}
        </div>

        <label class="btn-secondary w-full mt-3 text-sm cursor-pointer">
          <Upload class="w-4 h-4" /> Pilih Banner
          <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" onchange={onBannerChange} />
        </label>
        {#if fieldError('banner')}<p class="text-xs text-rose-600 mt-1">{fieldError('banner')}</p>{/if}
      </section>
    </div>
  </div>
</form>
