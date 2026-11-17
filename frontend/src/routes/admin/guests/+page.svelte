<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { EventModel, Guest, PaginationMeta } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import ConfirmModal from '$lib/components/ConfirmModal.svelte';
  import QrCodeModal from '$lib/components/QrCodeModal.svelte';
  import WhatsAppModal from '$lib/components/WhatsAppModal.svelte';
  import DeliveryBadge from '$lib/components/DeliveryBadge.svelte';
  import AttendanceBadge from '$lib/components/AttendanceBadge.svelte';
  import {
    Plus,
    Search,
    Upload,
    Download,
    QrCode,
    MessageCircle,
    Pencil,
    Trash2,
    Link2,
    Users,
    CalendarDays,
    Loader2,
    FileSpreadsheet,
    X,
    ExternalLink
  } from '@lucide/svelte';

  let events = $state<EventModel[]>([]);
  let selectedEventId = $state('');
  let guests = $state<Guest[]>([]);
  let meta = $state<PaginationMeta | null>(null);
  let loading = $state(true);
  let loadingEvents = $state(true);

  let search = $state('');
  let deliveryStatus = $state('');
  let attendanceStatus = $state('');
  let currentPage = $state(1);
  let reloadKey = $state(0);

  // Form state
  let showForm = $state(false);
  let editing = $state<Guest | null>(null);
  let formName = $state('');
  let formPhone = $state('');
  let formCategory = $state('');
  let saving = $state(false);
  let formError = $state('');
  let fieldErrors = $state<Record<string, string[]>>({});

  // Import state
  let showImport = $state(false);
  let importFile = $state<File | null>(null);
  let importing = $state(false);
  let importResult = $state<{ imported: number; skipped: number; errors: string[] } | null>(null);
  let importError = $state('');

  // Modals
  let qrGuest = $state<Guest | null>(null);
  let waGuest = $state<Guest | null>(null);
  let deleting = $state<Guest | null>(null);
  let removing = $state(false);

  let selectedEvent = $derived(events.find((event) => String(event.id) === selectedEventId) ?? null);

  async function loadEvents() {
    loadingEvents = true;
    try {
      const res = await api.getEvents();
      events = res.data;

      const queryEvent = page.url.searchParams.get('event');
      const initial = queryEvent && events.some((event) => String(event.id) === queryEvent)
        ? queryEvent
        : events[0]
          ? String(events[0].id)
          : '';

      selectedEventId = initial;
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat daftar acara.');
    } finally {
      loadingEvents = false;
    }
  }

  async function loadGuests() {
    if (!selectedEventId) {
      guests = [];
      meta = null;
      loading = false;
      return;
    }

    loading = true;
    try {
      const res = await api.getGuests(selectedEventId, {
        search: search || undefined,
        delivery_status: deliveryStatus || undefined,
        attendance_status: attendanceStatus || undefined,
        page: currentPage,
        per_page: 10
      });
      guests = res.data;
      meta = res.meta;
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat data tamu.');
    } finally {
      loading = false;
    }
  }

  onMount(loadEvents);

  $effect(() => {
    const eventId = selectedEventId;
    const q = search;
    const delivery = deliveryStatus;
    const attendance = attendanceStatus;
    const current = currentPage;
    reloadKey;

    if (!eventId) return;

    const timer = setTimeout(() => {
      loadGuests();
    }, 250);

    return () => clearTimeout(timer);
  });

  function changeEvent() {
    currentPage = 1;
    guests = [];
    meta = null;
  }

  function resetFilters() {
    search = '';
    deliveryStatus = '';
    attendanceStatus = '';
    currentPage = 1;
  }

  function reload() {
    reloadKey += 1;
  }

  function openCreate() {
    editing = null;
    formName = '';
    formPhone = '';
    formCategory = '';
    formError = '';
    fieldErrors = {};
    showForm = true;
  }

  function openEdit(guest: Guest) {
    editing = guest;
    formName = guest.name;
    formPhone = guest.whatsapp_number;
    formCategory = guest.category ?? '';
    formError = '';
    fieldErrors = {};
    showForm = true;
  }

  async function saveGuest(e: SubmitEvent) {
    e.preventDefault();
    if (!selectedEventId) return;

    saving = true;
    formError = '';
    fieldErrors = {};

    const payload = {
      name: formName.trim(),
      whatsapp_number: formPhone.trim(),
      category: formCategory.trim() || undefined
    };

    try {
      if (editing) {
        await api.updateGuest(editing.id, payload);
        toast.success('Data tamu berhasil diperbarui.');
      } else {
        await api.createGuest(selectedEventId, payload);
        toast.success('Tamu berhasil ditambahkan dan link undangan dibuat.');
      }
      showForm = false;
      reload();
    } catch (err: any) {
      formError = err.message || 'Gagal menyimpan data tamu.';
      fieldErrors = err.errors ?? {};
    } finally {
      saving = false;
    }
  }

  async function confirmDelete() {
    if (!deleting) return;
    removing = true;
    try {
      await api.deleteGuest(deleting.id);
      toast.success('Tamu berhasil dihapus.');
      deleting = null;
      reload();
    } catch (err: any) {
      toast.error(err.message || 'Gagal menghapus tamu.');
    } finally {
      removing = false;
    }
  }

  function onImportFile(e: Event) {
    const input = e.target as HTMLInputElement;
    importFile = input.files?.[0] ?? null;
    importResult = null;
    importError = '';
  }

  async function runImport() {
    if (!selectedEventId) return;

    if (!importFile) {
      importError = 'Pilih file CSV terlebih dahulu.';
      return;
    }

    importing = true;
    importError = '';
    importResult = null;

    try {
      const res = await api.importGuests(selectedEventId, importFile);
      importResult = res.data;
      toast.success(res.message ?? 'Import selesai.');
      reload();
    } catch (err: any) {
      importError = err.message || 'Import gagal.';
      if (err.payload?.data) {
        importResult = err.payload.data;
      }
    } finally {
      importing = false;
    }
  }

  function downloadTemplate() {
    const rows = [
      'name,whatsapp_number,category',
      'Budi Santoso,081234567890,Keluarga',
      'Dewi Anggraini,081298765432,Teman'
    ];
    const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'template-import-tamu.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  function copyLink(guest: Guest) {
    const url = guest.invitation?.invitation_url;
    if (!url) {
      toast.warning('Link undangan belum dibuat.');
      return;
    }
    navigator.clipboard
      .writeText(url)
      .then(() => toast.success('Link undangan disalin.'))
      .catch(() => toast.error('Gagal menyalin link.'));
  }

  function fieldError(key: string) {
    return fieldErrors[key]?.[0];
  }
</script>

<svelte:head><title>Data Tamu — Invitaku</title></svelte:head>

{#if loadingEvents}
  <Spinner label="Memuat daftar acara..." full />
{:else if events.length === 0}
  <div class="card">
    <EmptyState
      title="Belum ada acara"
      message="Buat acara terlebih dahulu sebelum menambahkan data tamu."
      icon={CalendarDays}
    >
      {#snippet action()}
        <a href="/admin/events/new" class="btn-primary text-sm"><Plus class="w-4 h-4" /> Buat Acara</a>
      {/snippet}
    </EmptyState>
  </div>
{:else}
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-stone-900">Data Tamu</h2>
        <p class="text-sm text-stone-500 mt-1">
          Kelola tamu, link undangan unik, QR Code, dan status pengiriman WhatsApp.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary text-sm" onclick={() => { showImport = true; importResult = null; importError = ''; importFile = null; }}>
          <Upload class="w-4 h-4" /> Import CSV
        </button>
        <button class="btn-primary text-sm" onclick={openCreate}><Plus class="w-4 h-4" /> Tambah Tamu</button>
      </div>
    </div>

    <div class="card p-4 space-y-3">
      <div class="flex flex-col lg:flex-row gap-3">
        <div class="flex-1">
          <label class="label" for="event-filter">Acara</label>
          <select id="event-filter" class="select" bind:value={selectedEventId} onchange={changeEvent}>
            {#each events as event (event.id)}
              <option value={String(event.id)}>{event.name}</option>
            {/each}
          </select>
        </div>

        <div class="flex-1">
          <label class="label" for="search">Cari tamu</label>
          <div class="relative">
            <Search class="w-4 h-4 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              id="search"
              class="input pl-10"
              placeholder="Cari nama tamu..."
              bind:value={search}
              oninput={() => (currentPage = 1)}
            />
          </div>
        </div>

        <div class="lg:w-48">
          <label class="label" for="delivery-filter">Status pengiriman</label>
          <select id="delivery-filter" class="select" bind:value={deliveryStatus} onchange={() => (currentPage = 1)}>
            <option value="">Semua</option>
            <option value="not_sent">Belum dikirim</option>
            <option value="queued">Menunggu</option>
            <option value="sent">Terkirim</option>
            <option value="failed">Gagal</option>
          </select>
        </div>

        <div class="lg:w-44">
          <label class="label" for="attendance-filter">Kehadiran</label>
          <select id="attendance-filter" class="select" bind:value={attendanceStatus} onchange={() => (currentPage = 1)}>
            <option value="">Semua</option>
            <option value="present">Hadir</option>
            <option value="absent">Belum hadir</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-between gap-2">
        <p class="text-xs text-stone-500">
          {#if selectedEvent}
            {selectedEvent.name} · {meta?.total ?? 0} tamu
          {/if}
        </p>
        <button class="text-xs text-stone-500 hover:text-stone-800 underline" onclick={resetFilters}>
          Reset filter
        </button>
      </div>
    </div>

    <div class="card overflow-hidden">
      {#if loading}
        <Spinner label="Memuat data tamu..." />
      {:else if guests.length === 0}
        <EmptyState
          title={meta?.total === 0 && !search && !deliveryStatus && !attendanceStatus ? 'Belum ada tamu' : 'Tamu tidak ditemukan'}
          message={meta?.total === 0 && !search && !deliveryStatus && !attendanceStatus
            ? 'Tambahkan tamu satu per satu atau import dari file CSV.'
            : 'Coba ubah kata kunci atau filter.'}
          icon={Users}
        >
          {#snippet action()}
            <button class="btn-primary text-sm" onclick={openCreate}><Plus class="w-4 h-4" /> Tambah Tamu</button>
          {/snippet}
        </EmptyState>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[1000px]">
            <thead>
              <tr>
                <th class="th w-12">No</th>
                <th class="th">Nama Tamu</th>
                <th class="th">Nomor WhatsApp</th>
                <th class="th">Acara</th>
                <th class="th">Link Undangan</th>
                <th class="th">Pengiriman</th>
                <th class="th">Kehadiran</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              {#each guests as guest, index (guest.id)}
                <tr class="hover:bg-stone-50/70">
                  <td class="td text-stone-400">
                    {((meta?.current_page ?? 1) - 1) * (meta?.per_page ?? 10) + index + 1}
                  </td>
                  <td class="td">
                    <p class="font-medium text-stone-800">{guest.name}</p>
                    {#if guest.category}
                      <p class="text-[11px] text-stone-400">{guest.category}</p>
                    {/if}
                  </td>
                  <td class="td text-stone-600 whitespace-nowrap">+{guest.whatsapp_number}</td>
                  <td class="td text-stone-500 max-w-[180px] truncate" title={selectedEvent?.name}>
                    {selectedEvent?.name ?? '-'}
                  </td>
                  <td class="td">
                    {#if guest.invitation}
                      <div class="flex items-center gap-1">
                        <a
                          href={guest.invitation.invitation_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          class="p-1.5 rounded-md text-stone-500 hover:bg-stone-100"
                          title="Buka undangan"
                        >
                          <ExternalLink class="w-4 h-4" />
                        </a>
                        <button
                          class="p-1.5 rounded-md text-stone-500 hover:bg-stone-100"
                          title="Salin link"
                          onclick={() => copyLink(guest)}
                        >
                          <Link2 class="w-4 h-4" />
                        </button>
                      </div>
                    {:else}
                      <span class="text-xs text-stone-400">Belum dibuat</span>
                    {/if}
                  </td>
                  <td class="td">
                    <DeliveryBadge status={guest.invitation?.delivery_status ?? 'not_sent'} />
                  </td>
                  <td class="td"><AttendanceBadge checkedIn={!!guest.attendance} /></td>
                  <td class="td">
                    <div class="flex items-center justify-end gap-1">
                      <button
                        class="p-1.5 rounded-md text-stone-500 hover:bg-stone-100"
                        title="QR Code"
                        onclick={() => (qrGuest = guest)}
                      >
                        <QrCode class="w-4 h-4" />
                      </button>
                      <button
                        class="p-1.5 rounded-md text-emerald-600 hover:bg-emerald-50"
                        title="Kirim WhatsApp"
                        onclick={() => (waGuest = guest)}
                      >
                        <MessageCircle class="w-4 h-4" />
                      </button>
                      <button
                        class="p-1.5 rounded-md text-stone-500 hover:bg-stone-100"
                        title="Edit"
                        onclick={() => openEdit(guest)}
                      >
                        <Pencil class="w-4 h-4" />
                      </button>
                      <button
                        class="p-1.5 rounded-md text-rose-500 hover:bg-rose-50"
                        title="Hapus"
                        onclick={() => (deleting = guest)}
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        {#if meta}
          <Pagination
            {meta}
            onpage={(pageNumber) => (currentPage = pageNumber)}
          />
        {/if}
      {/if}
    </div>
  </div>
{/if}

<!-- Add / edit guest modal -->
<Modal
  open={showForm}
  title={editing ? 'Edit Data Tamu' : 'Tambah Tamu'}
  description={selectedEvent?.name}
  onclose={() => (showForm = false)}
  size="sm"
>
  <form class="space-y-4" onsubmit={saveGuest}>
    {#if formError}
      <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700">{formError}</div>
    {/if}

    <div>
      <label class="label" for="guest-name">Nama Tamu <span class="text-rose-500">*</span></label>
      <input id="guest-name" class="input" bind:value={formName} placeholder="Nama lengkap tamu" required />
      {#if fieldError('name')}<p class="text-xs text-rose-600 mt-1">{fieldError('name')}</p>{/if}
    </div>

    <div>
      <label class="label" for="guest-phone">Nomor WhatsApp <span class="text-rose-500">*</span></label>
      <input
        id="guest-phone"
        class="input"
        bind:value={formPhone}
        placeholder="08xxxxxxxxxx atau +62xxxxxxxxxx"
        required
      />
      <p class="text-[11px] text-stone-400 mt-1">Sistem menormalkan nomor ke format internasional (62...).</p>
      {#if fieldError('whatsapp_number')}<p class="text-xs text-rose-600 mt-1">{fieldError('whatsapp_number')}</p>{/if}
    </div>

    <div>
      <label class="label" for="guest-category">Keterangan / Kategori</label>
      <input id="guest-category" class="input" bind:value={formCategory} placeholder="Contoh: Keluarga, Teman, VIP" />
    </div>

    <div class="flex items-center justify-end gap-2 pt-2">
      <button type="button" class="btn-secondary text-sm" onclick={() => (showForm = false)}>Batal</button>
      <button type="submit" class="btn-primary text-sm" disabled={saving}>
        {#if saving}
          <Loader2 class="w-4 h-4 animate-spin" /> Menyimpan...
        {:else}
          {editing ? 'Simpan Perubahan' : 'Tambah Tamu'}
        {/if}
      </button>
    </div>
  </form>
</Modal>

<!-- Import modal -->
<Modal
  open={showImport}
  title="Import Data Tamu"
  description="Unggah file CSV berisi data tamu."
  onclose={() => (showImport = false)}
  size="md"
>
  <div class="space-y-4">
    <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
      <div class="flex items-start gap-3">
        <FileSpreadsheet class="w-5 h-5 text-emerald-600 mt-0.5" />
        <div class="text-sm text-stone-600">
          <p class="font-medium text-stone-800">Format CSV</p>
          <p class="text-xs mt-1 leading-relaxed">
            Kolom: <code class="font-mono">name, whatsapp_number, category</code>. Baris pertama boleh berupa header.
            Nomor WhatsApp dapat menggunakan awalan 08 atau +62.
          </p>
          <button type="button" class="mt-2 text-xs font-medium text-[#7b2d43] hover:underline inline-flex items-center gap-1" onclick={downloadTemplate}>
            <Download class="w-3.5 h-3.5" /> Unduh template CSV
          </button>
        </div>
      </div>
    </div>

    <div>
      <label class="label" for="csv-file">File CSV</label>
      <input
        id="csv-file"
        type="file"
        accept=".csv,text/csv,text/plain"
        class="input file:mr-3 file:rounded-lg file:border-0 file:bg-[#7b2d43] file:text-white file:px-3 file:py-1.5 file:text-xs file:font-medium"
        onchange={onImportFile}
      />
    </div>

    {#if importError}
      <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700">{importError}</div>
    {/if}

    {#if importResult}
      <div class="rounded-xl border border-stone-200 bg-white p-4 space-y-2">
        <div class="flex gap-4 text-sm">
          <span class="text-emerald-700 font-medium">{importResult.imported} ditambahkan</span>
          <span class="text-amber-700 font-medium">{importResult.skipped} dilewati</span>
        </div>
        {#if importResult.errors.length}
          <div class="max-h-40 overflow-y-auto text-xs text-stone-600 space-y-1">
            {#each importResult.errors as error}
              <p class="flex gap-1.5"><X class="w-3.5 h-3.5 text-rose-500 shrink-0 mt-0.5" /> {error}</p>
            {/each}
          </div>
        {/if}
      </div>
    {/if}

    <div class="flex items-center justify-end gap-2">
      <button type="button" class="btn-secondary text-sm" onclick={() => (showImport = false)}>Tutup</button>
      <button type="button" class="btn-primary text-sm" onclick={runImport} disabled={importing || !importFile}>
        {#if importing}
          <Loader2 class="w-4 h-4 animate-spin" /> Mengimpor...
        {:else}
          <Upload class="w-4 h-4" /> Mulai Import
        {/if}
      </button>
    </div>
  </div>
</Modal>

<QrCodeModal
  open={!!qrGuest}
  guest={qrGuest}
  invitation={qrGuest?.invitation}
  eventName={selectedEvent?.name}
  onclose={() => (qrGuest = null)}
/>

<WhatsAppModal
  open={!!waGuest}
  guest={waGuest}
  invitation={waGuest?.invitation}
  eventName={selectedEvent?.name}
  onclose={() => (waGuest = null)}
  onupdated={reload}
/>

<ConfirmModal
  open={!!deleting}
  title="Hapus tamu?"
  message={`Data tamu "${deleting?.name ?? ''}", link undangan, QR Code, dan catatan kehadirannya akan dihapus permanen.`}
  confirmText={removing ? 'Menghapus...' : 'Ya, hapus'}
  type="danger"
  onConfirm={confirmDelete}
  onCancel={() => (deleting = null)}
/>
