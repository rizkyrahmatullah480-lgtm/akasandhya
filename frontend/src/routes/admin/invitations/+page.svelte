<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { EventModel, Guest, Invitation, PaginationMeta } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import QrCodeModal from '$lib/components/QrCodeModal.svelte';
  import WhatsAppModal from '$lib/components/WhatsAppModal.svelte';
  import DeliveryBadge from '$lib/components/DeliveryBadge.svelte';
  import AttendanceBadge from '$lib/components/AttendanceBadge.svelte';
  import {
    Search,
    Mail,
    QrCode,
    MessageCircle,
    ExternalLink,
    CheckCircle2,
    Copy,
    Loader2,
    CalendarDays,
    AlertTriangle,
    RefreshCw
  } from '@lucide/svelte';

  let events = $state<EventModel[]>([]);
  let selectedEventId = $state('');
  let invitations = $state<Invitation[]>([]);
  let meta = $state<PaginationMeta | null>(null);
  let loading = $state(true);
  let loadingEvents = $state(true);

  let search = $state('');
  let deliveryStatus = $state('');
  let attendanceStatus = $state('');
  let currentPage = $state(1);
  let reloadKey = $state(0);
  let updatingId = $state<number | null>(null);

  let qrGuest = $state<Guest | null>(null);
  let waGuest = $state<Guest | null>(null);
  let waInvitation = $state<Invitation | null>(null);

  let selectedEvent = $derived(events.find((event) => String(event.id) === selectedEventId) ?? null);

  async function loadEvents() {
    loadingEvents = true;
    try {
      const res = await api.getEvents();
      events = res.data;
      const queryEvent = page.url.searchParams.get('event');
      selectedEventId = queryEvent && events.some((event) => String(event.id) === queryEvent)
        ? queryEvent
        : '';
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat acara.');
    } finally {
      loadingEvents = false;
    }
  }

  async function loadInvitations() {
    loading = true;
    try {
      const res = await api.getInvitations({
        event_id: selectedEventId || undefined,
        delivery_status: deliveryStatus || undefined,
        attendance_status: attendanceStatus || undefined,
        search: search || undefined,
        page: currentPage,
        per_page: 15
      });
      invitations = res.data;
      meta = res.meta;
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat undangan.');
    } finally {
      loading = false;
    }
  }

  onMount(loadEvents);

  $effect(() => {
    const eventId = selectedEventId;
    const status = deliveryStatus;
    const attendance = attendanceStatus;
    const q = search;
    const current = currentPage;
    reloadKey;

    const timer = setTimeout(() => {
      loadInvitations();
    }, 250);

    return () => clearTimeout(timer);
  });

  function reload() {
    reloadKey += 1;
  }

  async function markSent(invitation: Invitation) {
    updatingId = invitation.id;
    try {
      const res = await api.markInvitationSent(invitation.id);
      toast.success('Status pengiriman ditandai terkirim.');
      invitations = invitations.map((item) => (item.id === invitation.id ? { ...item, ...res.data } : item));
      reload();
    } catch (err: any) {
      toast.error(err.message || 'Gagal memperbarui status.');
    } finally {
      updatingId = null;
    }
  }

  function copyLink(invitation: Invitation) {
    navigator.clipboard
      .writeText(invitation.invitation_url)
      .then(() => toast.success('Link undangan disalin.'))
      .catch(() => toast.error('Gagal menyalin link.'));
  }

  function openQr(invitation: Invitation) {
    qrGuest = invitation.guest ?? null;
  }

  function openWa(invitation: Invitation) {
    waGuest = invitation.guest ?? null;
    waInvitation = invitation;
  }

  function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
</script>

<svelte:head><title>Undangan — Invitaku</title></svelte:head>

{#if loadingEvents}
  <Spinner label="Memuat acara..." full />
{:else}
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-stone-900">Undangan & Pengiriman</h2>
        <p class="text-sm text-stone-500 mt-1">
          Pantau status pengiriman WhatsApp, link undangan, dan QR Code setiap tamu.
        </p>
      </div>
      <a href="/admin/guests" class="btn-secondary text-sm"><Mail class="w-4 h-4" /> Kelola Data Tamu</a>
    </div>

    <div class="card p-4 space-y-3">
      <div class="flex flex-col lg:flex-row gap-3">
        <div class="flex-1">
          <label class="label" for="event-filter">Acara</label>
          <select id="event-filter" class="select" bind:value={selectedEventId} onchange={() => (currentPage = 1)}>
            <option value="">Semua acara</option>
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
    </div>

    <div class="card overflow-hidden">
      {#if loading}
        <Spinner label="Memuat undangan..." />
      {:else if invitations.length === 0}
        <EmptyState
          title="Belum ada undangan"
          message={events.length === 0
            ? 'Buat acara dan tambahkan tamu untuk menghasilkan undangan.'
            : 'Tidak ada undangan yang cocok dengan filter saat ini.'}
          icon={Mail}
        />
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[1050px]">
            <thead>
              <tr>
                <th class="th w-12">No</th>
                <th class="th">Tamu</th>
                <th class="th">Acara</th>
                <th class="th">Link Undangan</th>
                <th class="th">Status</th>
                <th class="th">Waktu Kirim</th>
                <th class="th">Kehadiran</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              {#each invitations as invitation, index (invitation.id)}
                <tr class="hover:bg-stone-50/70">
                  <td class="td text-stone-400">
                    {((meta?.current_page ?? 1) - 1) * (meta?.per_page ?? 15) + index + 1}
                  </td>
                  <td class="td">
                    <p class="font-medium text-stone-800">{invitation.guest?.name ?? 'Tamu'}</p>
                    {#if invitation.guest?.whatsapp_number}
                      <p class="text-[11px] text-stone-400">+{invitation.guest.whatsapp_number}</p>
                    {/if}
                  </td>
                  <td class="td text-stone-500 max-w-[170px] truncate">
                    {invitation.event?.name ?? selectedEvent?.name ?? '-'}
                  </td>
                  <td class="td">
                    <div class="flex items-center gap-1">
                      <a
                        href={invitation.invitation_url}
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
                        onclick={() => copyLink(invitation)}
                      >
                        <Copy class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                  <td class="td">
                    <DeliveryBadge status={invitation.delivery_status} />
                    {#if invitation.delivery_error}
                      <p class="mt-1 text-[10px] text-rose-500 max-w-[180px] leading-snug" title={invitation.delivery_error}>
                        <AlertTriangle class="w-3 h-3 inline mr-0.5" />{invitation.delivery_error}
                      </p>
                    {/if}
                  </td>
                  <td class="td text-stone-500 whitespace-nowrap text-xs">
                    {formatDateTime(invitation.sent_at)}
                  </td>
                  <td class="td">
                    <AttendanceBadge checkedIn={!!invitation.attendance} />
                  </td>
                  <td class="td">
                    <div class="flex items-center justify-end gap-1">
                      <button
                        class="p-1.5 rounded-md text-stone-500 hover:bg-stone-100"
                        title="QR Code"
                        onclick={() => openQr(invitation)}
                      >
                        <QrCode class="w-4 h-4" />
                      </button>
                      <button
                        class="p-1.5 rounded-md text-emerald-600 hover:bg-emerald-50"
                        title="Kirim WhatsApp"
                        onclick={() => openWa(invitation)}
                      >
                        <MessageCircle class="w-4 h-4" />
                      </button>
                      {#if invitation.delivery_status !== 'sent'}
                        <button
                          class="p-1.5 rounded-md text-sky-600 hover:bg-sky-50 disabled:opacity-40"
                          title="Tandai terkirim"
                          disabled={updatingId === invitation.id}
                          onclick={() => markSent(invitation)}
                        >
                          {#if updatingId === invitation.id}
                            <Loader2 class="w-4 h-4 animate-spin" />
                          {:else}
                            <CheckCircle2 class="w-4 h-4" />
                          {/if}
                        </button>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        {#if meta}
          <Pagination {meta} onpage={(pageNumber) => (currentPage = pageNumber)} />
        {/if}
      {/if}
    </div>

    <div class="flex items-center gap-2 text-[11px] text-stone-500">
      <RefreshCw class="w-3.5 h-3.5" />
      Status <span class="font-medium text-emerald-600">Terkirim</span> pada mode API hanya disimpan setelah provider WhatsApp merespons sukses.
    </div>
  </div>
{/if}

<QrCodeModal
  open={!!qrGuest}
  guest={qrGuest}
  invitation={qrGuest?.invitation}
  eventName={qrGuest?.event?.name ?? ''}
  onclose={() => (qrGuest = null)}
/>

<WhatsAppModal
  open={!!waGuest}
  guest={waGuest}
  invitation={waInvitation}
  eventName={waInvitation?.event?.name ?? waGuest?.event?.name ?? ''}
  onclose={() => {
    waGuest = null;
    waInvitation = null;
  }}
  onupdated={reload}
/>
