<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import { readCache, writeCache } from '$lib/utils/cache';
  import type { PublicInvitationData } from '$lib/types';
  import {
    Sparkles,
    CalendarDays,
    Clock,
    MapPin,
    GraduationCap,
    Copy,
    Download,
    CheckCircle2,
    ArrowDown,
    UserRound,
    ShieldCheck,
    Link2Off,
    RefreshCw,
    BookOpen,
    Heart,
    Maximize2,
    X
  } from '@lucide/svelte';

  let data = $state<PublicInvitationData | null>(null);
  let loading = $state(true);
  let error = $state('');
  let opened = $state(false);
  let qrDataUrl = $state('');
  let generatingQr = $state(false);
  let copying = $state(false);
  let qrExpanded = $state(false);
  let now = $state(Date.now());
  let timer: ReturnType<typeof setInterval> | null = null;

  let invitationUrl = $derived(
    data?.invitation?.invitation_url ??
      (data && typeof window !== 'undefined'
        ? `${window.location.origin}/santri/undangan/${data.guest.unique_token}`
        : '')
  );

  let targetTime = $derived(
    data
      ? new Date(
          `${data.event.event_date.slice(0, 10)}T${(data.event.event_time || '00:00').slice(0, 5)}:00`
        ).getTime()
      : 0
  );
  let remaining = $derived(Math.max(0, targetTime - now));
  let days = $derived(Math.floor(remaining / 86_400_000));
  let hours = $derived(Math.floor((remaining % 86_400_000) / 3_600_000));
  let minutes = $derived(Math.floor((remaining % 3_600_000) / 60_000));
  let seconds = $derived(Math.floor((remaining % 60_000) / 1000));

  async function load(token: string) {
    if (!token) {
      loading = false;
      error = 'Tautan undangan tidak valid.';
      return;
    }

    loading = true;
    error = '';

    const cacheKey = `santri_invitation_${token}`;
    data = readCache<PublicInvitationData>(cacheKey, 24 * 60 * 60 * 1000);
    if (data) loading = false;

    try {
      const res = await api.getPublicInvitation(token);
      data = res.data;
      writeCache(cacheKey, res.data);
    } catch (err: any) {
      if (!data) {
        error =
          err.status === 404
            ? 'Undangan tidak ditemukan. Tautan mungkin salah, sudah kedaluwarsa, atau tamu telah dihapus.'
            : err.message || 'Gagal memuat undangan. Periksa koneksi internet Anda.';
      }
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    load(page.params.token ?? '');
    timer = setInterval(() => (now = Date.now()), 1000);
  });

  onDestroy(() => {
    if (timer) clearInterval(timer);
  });

  $effect(() => {
    if (!data || !invitationUrl) return;

    let cancelled = false;
    generatingQr = true;

    void (async () => {
      try {
        const QRCode = (await import('qrcode')).default;
        const result = await QRCode.toDataURL(invitationUrl, {
          width: 420,
          margin: 2,
          errorCorrectionLevel: 'H',
          color: { dark: '#221a1c', light: '#ffffff' }
        });
        if (!cancelled) qrDataUrl = result;
      } catch {
        if (!cancelled) error = 'QR Code tidak berhasil dibuat. Gunakan tautan undangan di bawah.';
      } finally {
        if (!cancelled) generatingQr = false;
      }
    })();

    return () => {
      cancelled = true;
    };
  });

  function formatLongDate(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value.slice(0, 10) + 'T00:00:00');
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  function formatTime(value?: string | null) {
    if (!value) return '-';
    return `${value.slice(0, 5)} WIB`;
  }

  function formatCheckedIn(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  async function copyLink() {
    copying = true;
    try {
      await navigator.clipboard.writeText(invitationUrl);
    } catch {
      // Fallback for browsers that block the clipboard API.
      const input = document.createElement('textarea');
      input.value = invitationUrl;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      input.remove();
    } finally {
      copying = false;
    }
  }

  function downloadQr() {
    if (!qrDataUrl || !data) return;
    const link = document.createElement('a');
    link.href = qrDataUrl;
    link.download = `qr-${(data.guest.santri_code || data.guest.name).toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

</script>

<svelte:head>
  <title>{data ? `Undangan ${data.guest.name} — ${data.event.name}` : 'Undangan Santri — Invitaku'}</title>
  <meta name="robots" content="noindex, nofollow" />
  <meta name="theme-color" content="#080808" />
</svelte:head>

{#if loading}
  <div class="min-h-screen bg-[#080808] flex items-center justify-center px-6">
    <div class="text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-[#d4af37]/30 text-[#d4af37]">
        <Sparkles class="h-7 w-7 animate-pulse" />
      </div>
      <p class="mt-6 font-playfair text-2xl text-[#fff5d6]">Menyiapkan undangan</p>
      <p class="mt-2 text-sm text-stone-500">Sebentar, kami memuat detail acara dan QR Anda.</p>
    </div>
  </div>
{:else if error || !data}
  <div class="min-h-screen bg-[#080808] flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-md text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-rose-500/30 bg-rose-500/10 text-rose-300">
        <Link2Off class="h-8 w-8" />
      </div>
      <h1 class="mt-6 font-playfair text-3xl text-[#fff5d6]">Undangan Tidak Dapat Dibuka</h1>
      <p class="mt-3 text-sm leading-7 text-stone-400">{error}</p>
      <button
        class="mt-6 inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-5 py-2.5 text-sm font-semibold text-[#080808]"
        onclick={() => load(page.params.token ?? '')}
      >
        <RefreshCw class="h-4 w-4" /> Coba Lagi
      </button>
    </div>
  </div>
{:else}
  <!-- Opening cover -->
  {#if !opened}
    <div class="relative min-h-screen overflow-hidden bg-[#080808] flex items-center justify-center px-6 py-12">
      <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-32 top-10 h-96 w-96 rounded-full bg-[#d4af37]/10 blur-[120px]"></div>
        <div class="absolute -right-24 bottom-0 h-96 w-96 rounded-full bg-[#d4af37]/5 blur-[100px]"></div>
        <div class="absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(#f3d57a 1px, transparent 1px); background-size: 28px 28px;"></div>
      </div>

      <div class="relative mx-auto w-full max-w-2xl text-center">
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border border-[#d4af37]/30 bg-[#d4af37]/5 text-[#d4af37]">
          <GraduationCap class="h-9 w-9" />
        </div>

        <p class="mt-8 text-[10px] font-bold uppercase tracking-[0.55em] text-[#d4af37]">Undangan Wisuda</p>
        <h1 class="mt-4 font-playfair text-4xl font-light leading-tight text-[#fff5d6] sm:text-6xl">
          {data.event.name}
        </h1>
        <p class="mx-auto mt-5 max-w-lg text-sm leading-8 text-stone-400">
          Menyemai Ilmu, Menuai Cahaya. Mengharap kehadiran dan doa restu Bapak/Ibu/Saudara/i pada momentum wisuda santri.
        </p>

        <div class="mx-auto mt-8 h-px w-28 bg-gradient-to-r from-transparent via-[#d4af37]/70 to-transparent"></div>

        <p class="mt-8 text-[10px] uppercase tracking-[0.35em] text-stone-500">Kepada Yth.</p>
        <p class="mt-2 font-playfair text-2xl text-[#f3d57a]">{data.guest.name}</p>
        <p class="mt-1 text-xs text-stone-500">
          {data.guest.santri_code} · {data.guest.category}{data.guest.category === 'Alumni' && data.guest.wali_angkatan ? ` · ${data.guest.wali_angkatan}` : ''}
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-stone-500">
          <span class="inline-flex items-center gap-2"><CalendarDays class="h-4 w-4 text-[#d4af37]" /> {formatLongDate(data.event.event_date)}</span>
          <span class="inline-flex items-center gap-2"><Clock class="h-4 w-4 text-[#d4af37]" /> {formatTime(data.event.event_time)}</span>
          <span class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-[#d4af37]" /> {data.event.location}</span>
        </div>

        <button
          class="group mt-10 inline-flex items-center gap-3 rounded-full bg-[#d4af37] px-7 py-3.5 text-sm font-semibold text-[#080808] shadow-xl shadow-[#d4af37]/10 transition hover:brightness-110"
          onclick={() => (opened = true)}
        >
          Buka Undangan
          <ArrowDown class="h-4 w-4 transition group-hover:translate-y-0.5" />
        </button>

        <p class="mt-6 text-[10px] uppercase tracking-[0.3em] text-stone-600">Invitaku · Mode Santri</p>
      </div>
    </div>
  {:else}
    <div class="min-h-screen bg-[#080808] text-stone-200">
      <!-- Hero -->
      <section class="relative overflow-hidden px-6 pb-20 pt-16 sm:pt-24">
        <div class="pointer-events-none absolute inset-0">
          <div class="absolute left-1/2 top-0 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-[#d4af37]/10 blur-[130px]"></div>
          <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(#f3d57a 1px, transparent 1px); background-size: 30px 30px;"></div>
        </div>

        <div class="relative mx-auto max-w-3xl text-center">
          <p class="text-[10px] font-bold uppercase tracking-[0.5em] text-[#d4af37]">Bismillahirrahmanirrahim</p>
          <h1 class="mt-5 font-playfair text-4xl font-light leading-tight text-[#fff5d6] sm:text-6xl">
            {data.event.name}
          </h1>
          <p class="mx-auto mt-4 max-w-xl text-sm leading-8 text-stone-400">
            {data.event.description || 'Menyemai Ilmu, Menuai Cahaya.'}
          </p>

          <div class="mx-auto mt-8 h-px w-32 bg-gradient-to-r from-transparent via-[#d4af37]/70 to-transparent"></div>

          <div class="mt-10 grid grid-cols-4 gap-2 sm:gap-4">
            {#each [
              { label: 'Hari', value: days },
              { label: 'Jam', value: hours },
              { label: 'Menit', value: minutes },
              { label: 'Detik', value: seconds }
            ] as item}
              <div class="rounded-2xl border border-[#d4af37]/15 bg-white/[0.02] px-2 py-4">
                <p class="font-playfair text-2xl text-[#f3d57a] sm:text-4xl">{String(item.value).padStart(2, '0')}</p>
                <p class="mt-1 text-[9px] uppercase tracking-[0.2em] text-stone-600">{item.label}</p>
              </div>
            {/each}
          </div>
        </div>
      </section>

      <!-- Story -->
      <section class="relative overflow-hidden border-y border-white/5 bg-[#0a0a0a] px-6 py-20">
        <div class="pointer-events-none absolute -left-20 top-10 h-80 w-80 rounded-full bg-[#d4af37]/5 blur-[100px]"></div>
        <div class="relative mx-auto max-w-2xl text-center">
          <BookOpen class="mx-auto h-7 w-7 text-[#d4af37]" />
          <p class="mt-5 text-[10px] font-bold uppercase tracking-[0.45em] text-[#d4af37]">Menyemai Ilmu, Menuai Cahaya</p>
          <p class="mt-5 font-cormorant text-xl leading-9 text-stone-300 sm:text-2xl">
            “Setiap langkah menuju ilmu adalah cahaya. Setiap doa yang dipanjatkan adalah bekal perjalanan.”
          </p>
          <p class="mt-5 text-sm leading-8 text-stone-500">
            Dengan penuh rasa syukur, kami mengundang Bapak/Ibu/Saudara/i untuk hadir dan memberikan doa restu
            pada momentum wisuda santri. Kehadiran Anda adalah kebahagiaan dan keberkahan bagi kami.
          </p>
        </div>
      </section>

      <!-- Event details -->
      <section id="acara" class="px-6 py-20">
        <div class="mx-auto max-w-5xl">
          <div class="text-center">
            <p class="text-[10px] font-bold uppercase tracking-[0.45em] text-[#d4af37]">Rangkaian Acara</p>
            <h2 class="mt-3 font-playfair text-3xl text-[#fff5d6] sm:text-4xl">Detail Acara</h2>
          </div>

          <div class="mt-10 grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl border border-[#d4af37]/15 bg-white/[0.02] p-6 text-center">
              <CalendarDays class="mx-auto h-6 w-6 text-[#d4af37]" />
              <p class="mt-4 text-[10px] uppercase tracking-[0.25em] text-stone-600">Tanggal</p>
              <p class="mt-2 text-sm font-medium leading-6 text-stone-200">{formatLongDate(data.event.event_date)}</p>
            </div>
            <div class="rounded-3xl border border-[#d4af37]/15 bg-white/[0.02] p-6 text-center">
              <Clock class="mx-auto h-6 w-6 text-[#d4af37]" />
              <p class="mt-4 text-[10px] uppercase tracking-[0.25em] text-stone-600">Waktu</p>
              <p class="mt-2 text-sm font-medium leading-6 text-stone-200">{formatTime(data.event.event_time)}</p>
            </div>
            <div class="rounded-3xl border border-[#d4af37]/15 bg-white/[0.02] p-6 text-center">
              <MapPin class="mx-auto h-6 w-6 text-[#d4af37]" />
              <p class="mt-4 text-[10px] uppercase tracking-[0.25em] text-stone-600">Lokasi</p>
              <p class="mt-2 text-sm font-medium leading-6 text-stone-200">{data.event.location}</p>
              {#if data.event.address}<p class="mt-1 text-xs leading-5 text-stone-500">{data.event.address}</p>{/if}
            </div>
          </div>

          {#if data.event.organizer}
            <p class="mt-8 text-center text-xs text-stone-500">Diselenggarakan oleh {data.event.organizer}</p>
          {/if}
        </div>
      </section>

      <!-- Guest card -->
      <section class="px-6 pb-20">
        <div class="mx-auto max-w-3xl overflow-hidden rounded-[2rem] border border-[#d4af37]/20 bg-gradient-to-br from-[#14110a] to-[#0a0a0a]">
          <div class="border-b border-white/5 px-6 py-5 text-center sm:px-10">
            <p class="text-[10px] font-bold uppercase tracking-[0.4em] text-[#d4af37]">Tamu Undangan</p>
          </div>

          <div class="px-6 py-8 text-center sm:px-10">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-[#d4af37]/25 bg-[#d4af37]/5 text-[#d4af37]">
              {#if data.guest.category === 'Alumni'}
                <UserRound class="h-7 w-7" />
              {:else}
                <GraduationCap class="h-7 w-7" />
              {/if}
            </div>

            <h3 class="mt-4 font-playfair text-3xl text-[#fff5d6]">{data.guest.name}</h3>
            <p class="mt-2 inline-flex items-center gap-2 rounded-full border border-[#d4af37]/20 bg-[#d4af37]/5 px-3 py-1 text-[11px] text-[#d4af37]">
              <ShieldCheck class="h-3.5 w-3.5" /> Kode {data.guest.santri_code || '-'}
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-stone-500">
              <span>{data.guest.category || 'Tamu'}</span>
              {#if data.guest.category === 'Alumni' && data.guest.wali_angkatan}<span>·</span><span>{data.guest.wali_angkatan}</span>{/if}
            </div>

            {#if data.checked_in && data.attendance}
              <div class="mx-auto mt-6 max-w-md rounded-2xl border border-emerald-500/20 bg-emerald-950/20 px-4 py-4 text-left">
                <div class="flex items-start gap-3">
                  <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                  <div>
                    <p class="text-sm font-semibold text-emerald-200">Kehadiran sudah tercatat</p>
                    <p class="mt-1 text-xs leading-5 text-emerald-200/70">
                      Check-in pada {formatCheckedIn(data.attendance.checked_in_at)}.
                      {#if data.attendance.checked_in_by}Tercatat oleh {data.attendance.checked_in_by}.{/if}
                    </p>
                  </div>
                </div>
              </div>
            {:else}
              <div class="mx-auto mt-6 max-w-md rounded-2xl border border-amber-500/15 bg-amber-950/10 px-4 py-4 text-left">
                <div class="flex items-start gap-3">
                  <Sparkles class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
                  <div>
                    <p class="text-sm font-semibold text-amber-200">Tunjukkan QR Code saat tiba</p>
                    <p class="mt-1 text-xs leading-5 text-amber-200/60">
                      Petugas akan memindai QR di bawah untuk mencatat kehadiran Anda.
                    </p>
                  </div>
                </div>
              </div>
            {/if}
          </div>
        </div>
      </section>

      <!-- QR -->
      <section id="qr" class="border-y border-white/5 bg-[#0a0a0a] px-6 py-20">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-[10px] font-bold uppercase tracking-[0.45em] text-[#d4af37]">QR Kehadiran</p>
          <h2 class="mt-3 font-playfair text-3xl text-[#fff5d6] sm:text-4xl">Simpan QR Code Anda</h2>
          <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-stone-500">
            Tunjukkan QR ini kepada petugas di meja registrasi. QR bersifat pribadi, mohon tidak membagikannya.
          </p>

          <div class="mt-8 flex justify-center">
            <button
              class="group relative rounded-3xl border border-[#d4af37]/25 bg-[#080808] p-4 transition hover:border-[#d4af37]/50"
              onclick={() => (qrExpanded = true)}
              aria-label="Perbesar QR Code"
            >
              {#if qrDataUrl}
                <img src={qrDataUrl} alt="QR Code kehadiran {data.guest.name}" class="h-64 w-64 rounded-2xl sm:h-72 sm:w-72" />
                <span class="absolute right-6 top-6 rounded-full bg-black/60 p-2 text-[#d4af37] opacity-0 transition group-hover:opacity-100">
                  <Maximize2 class="h-4 w-4" />
                </span>
              {:else}
                <div class="flex h-64 w-64 items-center justify-center rounded-2xl bg-white/5 px-6 text-xs text-stone-500 sm:h-72 sm:w-72">
                  {generatingQr ? 'Menyiapkan QR Code...' : 'QR Code belum tersedia.'}
                </div>
              {/if}
            </button>
          </div>

          <div class="mx-auto mt-6 flex max-w-md flex-col gap-2 sm:flex-row">
            <button
              class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-xs text-stone-300 hover:bg-white/5"
              onclick={copyLink}
            >
              <Copy class="h-4 w-4" /> {copying ? 'Tersalin...' : 'Salin Link Undangan'}
            </button>
            <button
              class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-4 py-2.5 text-xs font-semibold text-[#080808] disabled:opacity-50"
              onclick={downloadQr}
              disabled={!qrDataUrl}
            >
              <Download class="h-4 w-4" /> Unduh QR
            </button>
          </div>

          <p class="mt-5 break-all text-[10px] text-stone-600">{invitationUrl}</p>
        </div>
      </section>

      <!-- Closing -->
      <section class="px-6 py-20">
        <div class="mx-auto max-w-2xl text-center">
          <Heart class="mx-auto h-6 w-6 text-[#d4af37]" />
          <p class="mt-5 text-[10px] font-bold uppercase tracking-[0.45em] text-[#d4af37]">Terima Kasih</p>
          <p class="mt-4 font-cormorant text-2xl leading-9 text-stone-300">
            Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir.
          </p>
          <div class="mx-auto mt-8 h-px w-24 bg-gradient-to-r from-transparent via-[#d4af37]/60 to-transparent"></div>
          <p class="mt-6 text-xs text-stone-600">Kami yang berbahagia,<br />{data.event.organizer || 'Keluarga Besar Santri'}</p>
          <p class="mt-10 text-[10px] uppercase tracking-[0.3em] text-stone-700">Invitaku · Mode Santri</p>
        </div>
      </section>

      {#if qrExpanded && qrDataUrl}
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-black/95 p-6">
          <button class="absolute inset-0" onclick={() => (qrExpanded = false)} aria-label="Tutup QR"></button>
          <div class="relative text-center">
            <img src={qrDataUrl} alt="QR Code" class="mx-auto h-[min(80vw,420px)] w-[min(80vw,420px)] rounded-3xl" />
            <p class="mt-4 text-sm text-[#f3d57a]">{data.guest.name} · {data.guest.santri_code}</p>
            <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-xs text-stone-300" onclick={() => (qrExpanded = false)}>
              <X class="h-4 w-4" /> Tutup
            </button>
          </div>
        </div>
      {/if}
    </div>
  {/if}
{/if}
