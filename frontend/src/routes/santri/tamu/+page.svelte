<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import { readCache, writeCache } from '$lib/utils/cache';
  import SantriQrModal from '$lib/components/SantriQrModal.svelte';
  import type { Guest } from '$lib/types';
  import {
    Search,
    Copy,
    MessageCircle,
    QrCode,
    Trash2,
    CheckCircle2,
    Clock3,
    RefreshCw,
    Users,
    Loader2,
    Phone,
    Plus,
    ShieldCheck
  } from '@lucide/svelte';

  let guests = $state<Guest[]>([]);
  let loading = $state(true);
  let refreshing = $state(false);
  let search = $state('');
  let filterTipe = $state<'semua' | 'Santri' | 'Alumni'>('semua');
  let filterStatus = $state<'semua' | 'hadir' | 'belum'>('semua');
  let deleting = $state<number | null>(null);
  let toggling = $state<number | null>(null);
  let sending = $state<number | null>(null);
  let qrGuest = $state<Guest | null>(null);
  let lastWa = $state<{ name: string; url: string } | null>(null);
  let waCache = $state<Record<number, string>>({});
  const GUESTS_CACHE_KEY = 'santri_guests';

  // wa_url hanya dibuat saat tombol diklik. Tidak ada request saat hover,
  // supaya kuota internet tidak terbuang untuk tombol yang tidak dipakai.

  let filtered = $derived.by(() => {
    const term = search.trim().toLowerCase();

    return guests.filter((guest) => {
      const matchesSearch =
        !term ||
        guest.name.toLowerCase().includes(term) ||
        (guest.santri_code ?? '').toLowerCase().includes(term) ||
        guest.whatsapp_number.includes(term) ||
        (guest.wali_angkatan ?? '').toLowerCase().includes(term);

      const matchesTipe = filterTipe === 'semua' || guest.category === filterTipe;
      const hadir = !!guest.attendance;
      const matchesStatus =
        filterStatus === 'semua' ||
        (filterStatus === 'hadir' && hadir) ||
        (filterStatus === 'belum' && !hadir);

      return matchesSearch && matchesTipe && matchesStatus;
    });
  });

  let total = $derived(guests.length);
  let hadir = $derived(guests.filter((guest) => !!guest.attendance).length);
  let santri = $derived(guests.filter((guest) => guest.category === 'Santri').length);
  let alumni = $derived(guests.filter((guest) => guest.category === 'Alumni').length);

  async function load(showSpinner = true) {
    if (showSpinner) loading = true;
    else refreshing = true;

    const cached = readCache<Guest[]>(GUESTS_CACHE_KEY, 5 * 60 * 1000);
    if (cached) {
      guests = cached;
      if (showSpinner) loading = false;
    }

    try {
      // compact=1 menghilangkan relasi invitation/officer yang tidak dipakai
      // di halaman ini, sehingga response lebih kecil di internet lambat.
      const res = await api.getSantriGuests({ compact: true });
      guests = res.data;
      writeCache(GUESTS_CACHE_KEY, res.data, 5 * 60 * 1000);
    } catch (err: any) {
      if (!guests.length) {
        toast.error(err.message || 'Gagal memuat daftar tamu santri.');
      }
    } finally {
      loading = false;
      refreshing = false;
    }
  }

  onMount(() => load());

  function invitationUrl(guest: Guest) {
    if (guest.invitation?.invitation_url) return guest.invitation.invitation_url;
    if (typeof window !== 'undefined') return `${window.location.origin}/santri/undangan/${guest.unique_token}`;
    return '';
  }



  async function copyLink(guest: Guest) {
    try {
      await navigator.clipboard.writeText(invitationUrl(guest));
      toast.success(`Link undangan ${guest.name} disalin.`);
    } catch {
      toast.error('Gagal menyalin link. Buka QR lalu salin manual.');
    }
  }

  async function sendWhatsApp(guest: Guest) {
    const cached = waCache[guest.id];
    if (cached) {
      lastWa = { name: guest.name, url: cached };
      window.open(cached, '_blank', 'noopener,noreferrer');
      toast.success('WhatsApp dibuka. Tekan kirim di aplikasi WhatsApp.');
      return;
    }

    sending = guest.id;

    // Buka tab kosong selagi masih dalam user gesture, supaya popup blocker
    // tidak menolak window.open setelah await fetch selesai.
    const pendingWin = window.open('about:blank', '_blank');

    try {
      const res = await api.sendWhatsApp(guest.id, { mode: 'click' });

      if (res.data.wa_url) {
        waCache[guest.id] = res.data.wa_url;
        lastWa = { name: guest.name, url: res.data.wa_url };
        let opened = false;
        try {
          if (pendingWin && !pendingWin.closed && pendingWin.location) {
            pendingWin.location.href = res.data.wa_url;
            pendingWin.opener = null;
            opened = true;
          }
        } catch {
          opened = false;
        }
        if (!opened) {
          window.open(res.data.wa_url, '_blank', 'noopener,noreferrer');
        }
      }

      if (res.data.invitation) {
        guests = guests.map((item) =>
          item.id === guest.id ? { ...item, invitation: res.data.invitation } : item
        );
      }

      toast.success('WhatsApp dibuka. Tekan kirim di aplikasi WhatsApp.');
    } catch (err: any) {
      pendingWin?.close();
      toast.error(err.message || 'Gagal menyiapkan pesan WhatsApp.');
    } finally {
      sending = null;
    }
  }

  async function toggleStatus(guest: Guest) {
    toggling = guest.id;

    try {
      const res = await api.toggleSantriAttendance(guest.id);
      guests = guests.map((item) =>
        item.id === guest.id ? { ...item, attendance: res.data.attendance } : item
      );
      toast.success(res.message ?? 'Status kehadiran diperbarui.');
    } catch (err: any) {
      toast.error(err.message || 'Gagal mengubah status kehadiran.');
    } finally {
      toggling = null;
    }
  }

  async function removeGuest(guest: Guest) {
    if (!window.confirm(`Hapus tamu "${guest.name}"? Link undangan dan riwayat kehadirannya juga akan dihapus.`)) {
      return;
    }

    deleting = guest.id;

    try {
      await api.deleteSantriGuest(guest.id);
      guests = guests.filter((item) => item.id !== guest.id);
      toast.success(`Tamu ${guest.name} dihapus.`);
    } catch (err: any) {
      toast.error(err.message || 'Gagal menghapus tamu.');
    } finally {
      deleting = null;
    }
  }
</script>

<svelte:head><title>Daftar Tamu Santri — Invitaku</title></svelte:head>

<div class="space-y-5">
  <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
      <p class="text-[10px] font-bold uppercase tracking-[0.4em] text-[#d4af37]">Daftar Tamu</p>
      <h2 class="mt-2 font-playfair text-3xl text-[#fff5d6]">Data tamu undangan</h2>
      <p class="mt-2 text-sm leading-7 text-stone-500">
        Cari nama atau kode, filter tipe, lalu salin link, kirim WhatsApp, atau buka QR dari tabel.
      </p>
    </div>

    <div class="flex items-center gap-2">
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-3.5 py-2.5 text-xs text-stone-400 hover:bg-white/5 disabled:opacity-50"
        onclick={() => load(false)}
        disabled={refreshing}
      >
        <RefreshCw class="w-4 h-4 {refreshing ? 'animate-spin' : ''}" /> Muat ulang
      </button>
      <a
        href="/santri/tambah"
        class="inline-flex items-center gap-2 rounded-xl bg-[#d4af37] px-4 py-2.5 text-xs font-semibold text-[#080808] hover:brightness-110"
      >
        <Plus class="w-4 h-4" /> Tambah Tamu
      </a>
    </div>
  </div>

  {#if lastWa}
    <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
      <p class="text-sm text-emerald-200 flex-1">
        Pesan WhatsApp untuk <span class="font-semibold">{lastWa.name}</span> sudah disiapkan.
        Popup diblokir browser? Klik tombol di samping.
      </p>
      <div class="flex items-center gap-2 shrink-0">
        <a
          href={lastWa.url}
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 rounded-xl bg-[#d4af37] px-4 py-2 text-xs font-semibold text-[#080808] hover:brightness-110"
        >
          <MessageCircle class="w-4 h-4" /> Buka WhatsApp
        </a>
        <button
          type="button"
          class="rounded-xl border border-white/10 px-3 py-2 text-xs text-stone-400 hover:bg-white/5"
          onclick={() => (lastWa = null)}
        >
          Tutup
        </button>
      </div>
    </div>
  {/if}

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-4">
      <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Total</p>
      <p class="mt-2 font-playfair text-3xl text-[#fff5d6]">{total}</p>
    </div>
    <div class="rounded-2xl border border-[#d4af37]/10 bg-[#0e0d0b] p-4">
      <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Santri</p>
      <p class="mt-2 font-playfair text-3xl text-[#d4af37]">{santri}</p>
    </div>
    <div class="rounded-2xl border border-sky-500/10 bg-[#0e0d0b] p-4">
      <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Alumni</p>
      <p class="mt-2 font-playfair text-3xl text-sky-300">{alumni}</p>
    </div>
    <div class="rounded-2xl border border-emerald-500/10 bg-[#0e0d0b] p-4">
      <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Hadir</p>
      <p class="mt-2 font-playfair text-3xl text-emerald-300">{hadir}</p>
    </div>
  </div>

  <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-4">
    <div class="flex flex-col lg:flex-row gap-3">
      <div class="relative flex-1">
        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-600" />
        <input
          class="w-full rounded-xl border border-white/10 bg-black/20 py-2.5 pl-10 pr-3.5 text-sm text-stone-100 placeholder:text-stone-600 outline-none transition focus:border-[#d4af37]/60 focus:ring-2 focus:ring-[#d4af37]/10"
          placeholder="Cari nama, kode, wali/angkatan, atau nomor HP..."
          bind:value={search}
        />
      </div>

      <select
        class="rounded-xl border border-white/10 bg-[#0a0a0a] px-3.5 py-2.5 text-sm text-stone-300 outline-none focus:border-[#d4af37]/60"
        bind:value={filterTipe}
      >
        <option value="semua">Semua tipe</option>
        <option value="Santri">Santri</option>
        <option value="Alumni">Alumni</option>
      </select>

      <select
        class="rounded-xl border border-white/10 bg-[#0a0a0a] px-3.5 py-2.5 text-sm text-stone-300 outline-none focus:border-[#d4af37]/60"
        bind:value={filterStatus}
      >
        <option value="semua">Semua status</option>
        <option value="hadir">Hadir</option>
        <option value="belum">Belum hadir</option>
      </select>
    </div>
  </div>

  {#if loading}
    <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-12 flex flex-col items-center justify-center text-stone-500">
      <Loader2 class="w-8 h-8 animate-spin text-[#d4af37]" />
      <p class="mt-3 text-sm">Memuat daftar tamu...</p>
    </div>
  {:else if guests.length === 0}
    <div class="rounded-2xl border border-dashed border-[#d4af37]/20 bg-[#0e0d0b] p-12 text-center">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-[#d4af37]/20 text-[#d4af37]">
        <Users class="w-7 h-7" />
      </div>
      <p class="mt-4 font-semibold text-[#fff5d6]">Belum ada tamu santri</p>
      <p class="mt-1 text-sm text-stone-500">Tambahkan tamu pertama untuk membuat link dan QR undangan.</p>
      <a
        href="/santri/tambah"
        class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-5 py-2.5 text-sm font-semibold text-[#080808]"
      >
        <Plus class="w-4 h-4" /> Tambah Tamu
      </a>
    </div>
  {:else if filtered.length === 0}
    <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-10 text-center text-sm text-stone-500">
      Tidak ada tamu yang cocok dengan pencarian atau filter.
    </div>
  {:else}
    <div class="overflow-hidden rounded-2xl border border-white/5 bg-[#0e0d0b]">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
          <thead>
            <tr class="border-b border-white/5 bg-white/[0.02]">
              <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Kode</th>
              <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Tipe</th>
              <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Nama</th>
              <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Nama Wali / Angkatan</th>
              <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">No HP</th>
              <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Status</th>
              <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            {#each filtered as guest (guest.id)}
              {@const isHadir = !!guest.attendance}
              <tr class="hover:bg-white/[0.02] transition-colors">
                <td class="px-4 py-3.5">
                  <span class="font-mono text-xs tracking-widest text-[#d4af37]">{guest.santri_code || '-'}</span>
                </td>
                <td class="px-4 py-3.5">
                  <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold {guest.category === 'Santri'
                    ? 'border-[#d4af37]/25 bg-[#d4af37]/10 text-[#d4af37]'
                    : 'border-sky-400/25 bg-sky-400/10 text-sky-300'}">
                    {guest.category || 'Tamu'}
                  </span>
                </td>
                <td class="px-4 py-3.5">
                  <p class="font-medium text-stone-200">{guest.name}</p>
                </td>
                <td class="px-4 py-3.5 text-xs text-stone-400">{guest.wali_angkatan || '-'}</td>
                <td class="px-4 py-3.5">
                  <span class="inline-flex items-center gap-1.5 text-xs text-stone-400">
                    <Phone class="w-3.5 h-3.5 text-stone-600" /> +{guest.whatsapp_number}
                  </span>
                </td>
                <td class="px-4 py-3.5">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-semibold transition disabled:opacity-50 {isHadir
                      ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/15'
                      : 'border-amber-500/20 bg-amber-500/10 text-amber-300 hover:bg-amber-500/15'}"
                    onclick={() => toggleStatus(guest)}
                    disabled={toggling === guest.id}
                    title="Klik untuk mengubah status kehadiran"
                  >
                    {#if toggling === guest.id}
                      <Loader2 class="w-3 h-3 animate-spin" />
                    {:else if isHadir}
                      <CheckCircle2 class="w-3 h-3" />
                    {:else}
                      <Clock3 class="w-3 h-3" />
                    {/if}
                    {isHadir ? 'Hadir' : 'Belum'}
                  </button>
                </td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-end gap-1">
                    <button
                      type="button"
                      class="rounded-lg p-2 text-stone-500 hover:bg-white/5 hover:text-[#d4af37]"
                      onclick={() => copyLink(guest)}
                      title="Salin link"
                      aria-label="Salin link"
                    >
                      <Copy class="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg p-2 text-stone-500 hover:bg-white/5 hover:text-emerald-300 disabled:opacity-40"
                      onclick={() => sendWhatsApp(guest)}
                      disabled={sending === guest.id}
                      title={guest.category === 'Santri' ? 'Kirim WhatsApp ke orang tua/wali' : 'Kirim WhatsApp'}
                      aria-label="Kirim WhatsApp"
                    >
                      {#if sending === guest.id}
                        <Loader2 class="w-4 h-4 animate-spin" />
                      {:else}
                        <MessageCircle class="w-4 h-4" />
                      {/if}
                    </button>
                    <button
                      type="button"
                      class="rounded-lg p-2 text-stone-500 hover:bg-white/5 hover:text-sky-300"
                      onclick={() => (qrGuest = guest)}
                      title="Buka QR"
                      aria-label="Buka QR"
                    >
                      <QrCode class="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg p-2 text-stone-500 hover:bg-rose-500/10 hover:text-rose-300 disabled:opacity-40"
                      onclick={() => removeGuest(guest)}
                      disabled={deleting === guest.id}
                      title="Hapus tamu"
                      aria-label="Hapus tamu"
                    >
                      {#if deleting === guest.id}
                        <Loader2 class="w-4 h-4 animate-spin" />
                      {:else}
                        <Trash2 class="w-4 h-4" />
                      {/if}
                    </button>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    </div>

    <p class="flex items-center gap-2 text-[11px] text-stone-600">
      <ShieldCheck class="w-3.5 h-3.5" />
      Menampilkan {filtered.length} dari {guests.length} tamu. Status kehadiran tersimpan di tabel attendance.
    </p>
  {/if}
</div>

<SantriQrModal open={!!qrGuest} guest={qrGuest} onclose={() => (qrGuest = null)} />
