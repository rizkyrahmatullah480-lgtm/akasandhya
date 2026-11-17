<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { Category, EventModel, Template } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import {
    Search,
    Palette,
    Eye,
    Check,
    Crown,
    Sparkles,
    CalendarDays,
    Loader2
  } from '@lucide/svelte';

  let templates = $state<Template[]>([]);
  let categories = $state<Category[]>([]);
  let events = $state<EventModel[]>([]);
  let loading = $state(true);
  let search = $state('');
  let activeCategory = $state('');

  let previewTemplate = $state<Template | null>(null);
  let selectTemplate = $state<Template | null>(null);
  let selectedEventId = $state('');
  let saving = $state(false);

  async function load() {
    loading = true;
    try {
      const [templatesRes, categoriesRes, eventsRes] = await Promise.all([
        api.getTemplates(),
        api.getTemplateCategories(),
        api.getEvents()
      ]);
      templates = templatesRes.data;
      categories = categoriesRes.data;
      events = eventsRes.data;

      const queryEvent = page.url.searchParams.get('event');
      if (queryEvent && events.some((event) => String(event.id) === queryEvent)) {
        selectedEventId = queryEvent;
      }
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat desain undangan.');
    } finally {
      loading = false;
    }
  }

  onMount(load);

  let filtered = $derived(
    templates.filter((template) => {
      const matchSearch =
        !search ||
        template.name.toLowerCase().includes(search.toLowerCase()) ||
        (template.description ?? '').toLowerCase().includes(search.toLowerCase());
      const matchCategory = !activeCategory || String(template.category_id) === activeCategory;
      return matchSearch && matchCategory;
    })
  );

  function openSelect(template: Template) {
    selectTemplate = template;
    if (!selectedEventId && events.length === 1) {
      selectedEventId = String(events[0].id);
    }
  }

  async function applyTemplate() {
    if (!selectTemplate || !selectedEventId) return;
    saving = true;
    try {
      await api.updateEvent(selectedEventId, { template_id: selectTemplate.id });
      const target = events.find((event) => String(event.id) === selectedEventId);
      toast.success(`Desain "${selectTemplate.name}" diterapkan ke acara ${target?.name ?? ''}.`);
      selectTemplate = null;
    } catch (err: any) {
      toast.error(err.message || 'Gagal menerapkan desain.');
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head><title>Desain Undangan — Invitaku</title></svelte:head>

<div class="space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-semibold text-stone-900">Galeri Desain Undangan</h2>
      <p class="text-sm text-stone-500 mt-1">
        Pilih desain elegan untuk acara Anda. Data acara akan dimasukkan otomatis ke dalam desain.
      </p>
    </div>
    <a href="/admin/events/new" class="btn-primary text-sm"><CalendarDays class="w-4 h-4" /> Buat Acara</a>
  </div>

  <div class="card p-4 space-y-3">
    <div class="relative">
      <Search class="w-4 h-4 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
      <input
        class="input pl-10"
        placeholder="Cari desain undangan..."
        bind:value={search}
      />
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        class="badge border {activeCategory === ''
          ? 'bg-[#7b2d43] text-white border-[#7b2d43]'
          : 'bg-white text-stone-600 border-stone-200 hover:border-stone-300'}"
        onclick={() => (activeCategory = '')}
      >
        Semua
      </button>
      {#each categories as category (category.id)}
        <button
          class="badge border {activeCategory === String(category.id)
            ? 'bg-[#7b2d43] text-white border-[#7b2d43]'
            : 'bg-white text-stone-600 border-stone-200 hover:border-stone-300'}"
          onclick={() => (activeCategory = String(category.id))}
        >
          {category.name}
          {#if category.templates_count !== undefined}
            <span class="{activeCategory === String(category.id) ? 'text-white/70' : 'text-stone-400'}">
              ({category.templates_count})
            </span>
          {/if}
        </button>
      {/each}
    </div>
  </div>

  {#if loading}
    <Spinner label="Memuat galeri desain..." full />
  {:else if filtered.length === 0}
    <div class="card">
      <EmptyState
        title="Desain tidak ditemukan"
        message="Coba ubah kata kunci atau pilih kategori lain."
        icon={Palette}
      />
    </div>
  {:else}
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-5">
      {#each filtered as template (template.id)}
        <article class="card overflow-hidden group flex flex-col">
          <div class="relative h-56 bg-stone-100 overflow-hidden">
            {#if template.preview_image}
              <img
                src={template.preview_image}
                alt={template.name}
                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300"
                loading="lazy"
              />
            {:else}
              <div class="w-full h-full flex items-center justify-center text-[#7b2d43] bg-gradient-to-br from-[#7b2d43]/10 to-[#d6b98c]/20">
                <Palette class="w-10 h-10" />
              </div>
            {/if}

            {#if template.is_premium}
              <span class="absolute top-3 right-3 badge bg-amber-400/95 text-amber-950 border border-amber-300 shadow-sm">
                <Crown class="w-3 h-3" /> Premium
              </span>
            {/if}

            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
              <button
                class="btn bg-white/95 text-stone-800 text-xs shadow-lg"
                onclick={() => (previewTemplate = template)}
              >
                <Eye class="w-4 h-4" /> Preview
              </button>
            </div>
          </div>

          <div class="p-4 flex-1 flex flex-col">
            <div class="flex items-start justify-between gap-2">
              <div>
                <h3 class="font-semibold text-stone-900">{template.name}</h3>
                <p class="text-xs text-stone-500 mt-0.5">{template.category?.name ?? 'Umum'}</p>
              </div>
              <div class="flex gap-1 shrink-0 mt-1">
                <span class="w-3 h-3 rounded-full border border-black/10" style="background: {template.configuration?.primary_color ?? '#7b2d43'}"></span>
                <span class="w-3 h-3 rounded-full border border-black/10" style="background: {template.configuration?.accent_color ?? '#d6b98c'}"></span>
              </div>
            </div>

            <p class="text-xs text-stone-500 mt-2 line-clamp-2 flex-1">{template.description}</p>

            <div class="mt-4 grid grid-cols-2 gap-2">
              <button class="btn-secondary text-xs px-3" onclick={() => (previewTemplate = template)}>
                <Eye class="w-3.5 h-3.5" /> Preview
              </button>
              <button class="btn-primary text-xs px-3" onclick={() => openSelect(template)}>
                <Check class="w-3.5 h-3.5" /> Pilih Desain
              </button>
            </div>
          </div>
        </article>
      {/each}
    </div>
  {/if}
</div>

<!-- Preview modal -->
<Modal
  open={!!previewTemplate}
  title={previewTemplate?.name ?? 'Preview Desain'}
  description={previewTemplate?.category?.name}
  onclose={() => (previewTemplate = null)}
  size="lg"
>
  {#if previewTemplate}
    <div class="space-y-4">
      <div class="rounded-2xl overflow-hidden border border-stone-200 bg-stone-100">
        {#if previewTemplate.preview_image}
          <img src={previewTemplate.preview_image} alt={previewTemplate.name} class="w-full max-h-[60vh] object-contain bg-stone-100" />
        {/if}
      </div>

      <div class="flex flex-wrap items-center gap-3 text-xs text-stone-600">
        <span class="badge bg-stone-100 border border-stone-200">{previewTemplate.category?.name ?? 'Umum'}</span>
        {#if previewTemplate.is_premium}
          <span class="badge bg-amber-50 text-amber-700 border border-amber-200"><Crown class="w-3 h-3" /> Premium</span>
        {/if}
        <span class="flex items-center gap-1.5">
          <span class="w-4 h-4 rounded border border-black/10" style="background: {previewTemplate.configuration?.primary_color}"></span>
          {previewTemplate.configuration?.primary_color}
        </span>
        <span class="flex items-center gap-1.5">
          <span class="w-4 h-4 rounded border border-black/10" style="background: {previewTemplate.configuration?.accent_color}"></span>
          {previewTemplate.configuration?.accent_color}
        </span>
        <span>Font: {previewTemplate.configuration?.font_heading ?? 'Inter'}</span>
      </div>

      <p class="text-sm text-stone-600 leading-relaxed">{previewTemplate.description}</p>

      <button
        class="btn-primary w-full"
        onclick={() => {
          selectTemplate = previewTemplate;
          previewTemplate = null;
        }}
      >
        <Sparkles class="w-4 h-4" /> Gunakan desain ini
      </button>
    </div>
  {/if}
</Modal>

<!-- Select event modal -->
<Modal
  open={!!selectTemplate}
  title="Pilih Acara"
  description="Desain akan diterapkan pada acara yang dipilih."
  onclose={() => (selectTemplate = null)}
  size="sm"
>
  {#if selectTemplate}
    <div class="space-y-4">
      <div class="flex items-center gap-3 rounded-xl bg-stone-50 border border-stone-200 p-3">
        {#if selectTemplate.preview_image}
          <img src={selectTemplate.preview_image} alt={selectTemplate.name} class="w-14 h-14 rounded-lg object-cover" />
        {/if}
        <div class="min-w-0">
          <p class="text-sm font-medium text-stone-900 truncate">{selectTemplate.name}</p>
          <p class="text-xs text-stone-500">{selectTemplate.category?.name ?? 'Umum'}</p>
        </div>
      </div>

      {#if events.length === 0}
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Belum ada acara. Buat acara terlebih dahulu untuk menggunakan desain ini.
        </div>
        <a href="/admin/events/new" class="btn-primary w-full">Buat Acara</a>
      {:else}
        <div>
          <label class="label" for="event-select">Acara</label>
          <select id="event-select" class="select" bind:value={selectedEventId}>
            <option value="">— Pilih acara —</option>
            {#each events as event (event.id)}
              <option value={String(event.id)}>{event.name}</option>
            {/each}
          </select>
        </div>

        <button class="btn-primary w-full" onclick={applyTemplate} disabled={!selectedEventId || saving}>
          {#if saving}
            <Loader2 class="w-4 h-4 animate-spin" /> Menerapkan...
          {:else}
            <Check class="w-4 h-4" /> Terapkan Desain
          {/if}
        </button>
      {/if}
    </div>
  {/if}
</Modal>
