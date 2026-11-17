<script lang="ts">
  import type { PublicInvitationData } from '$lib/types';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import { CalendarDays, Clock, MapPin, MapPinned, Download, CheckCircle2, QrCode, Sparkles } from '@lucide/svelte';

  interface Props {
    data: PublicInvitationData;
  }

  let { data }: Props = $props();

  let config = $derived(data.template?.configuration ?? {});
  let primary = $derived(config.primary_color ?? '#7b2d43');
  let secondary = $derived(config.secondary_color ?? '#a65d70');
  let accent = $derived(config.accent_color ?? '#d6b98c');
  let background = $derived(config.bg_color ?? '#faf8f6');
  let textColor = $derived(config.text_color ?? '#252323');
  let headingFont = $derived(`'${config.font_heading ?? 'Playfair Display'}', Georgia, serif`);
  let bodyFont = $derived(`'${config.font_body ?? 'Inter'}', sans-serif`);

  let qrUrl = $derived(api.qrUrl(data.guest.unique_token));
  let downloading = $state(false);

  const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];
  const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

  let eventDate = $derived.by(() => {
    const raw = data.event.event_date;
    if (!raw) return '-';
    const date = new Date(`${raw}T00:00:00`);
    if (isNaN(date.getTime())) return raw;
    return `${days[date.getDay()]}, ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
  });

  let eventTime = $derived(data.event.event_time ? `${data.event.event_time.slice(0, 5)} WIB` : '-');

  let checkedInAt = $derived.by(() => {
    const value = data.attendance?.checked_in_at;
    if (!value) return '';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}, ${String(date.getHours()).padStart(2, '0')}:${String(
      date.getMinutes()
    ).padStart(2, '0')} WIB`;
  });

  async function downloadPng() {
    downloading = true;
    try {
      const QRCode = (await import('qrcode')).default;
      const url = typeof window !== 'undefined' ? window.location.href : '';
      const dataUrl = await QRCode.toDataURL(url, {
        width: 900,
        margin: 2,
        errorCorrectionLevel: 'H',
        color: { dark: '#221a1c', light: '#ffffff' }
      });
      const link = document.createElement('a');
      link.href = dataUrl;
      link.download = `qr-${data.guest.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      toast.success('QR Code berhasil diunduh.');
    } catch {
      toast.error('Gagal mengunduh QR Code.');
    } finally {
      downloading = false;
    }
  }
</script>

<svelte:head>
  <title>{data.event.name} — Undangan untuk {data.guest.name}</title>
  <meta name="description" content="Undangan digital {data.event.name} untuk {data.guest.name}." />
</svelte:head>

<div
  class="min-h-screen w-full py-6 px-4 sm:py-12 sm:px-6"
  style="background: {background}; color: {textColor}; font-family: {bodyFont};"
>
  <main class="mx-auto w-full max-w-2xl">
    <article
      class="relative overflow-hidden rounded-3xl shadow-xl shadow-black/10"
      style="background: color-mix(in srgb, {background} 82%, white); border: 1px solid color-mix(in srgb, {accent} 45%, transparent);"
    >
      <!-- Decorative accents -->
      <div
        class="pointer-events-none absolute -top-24 -right-24 w-64 h-64 rounded-full opacity-10"
        style="background: {primary}"
      ></div>
      <div
        class="pointer-events-none absolute -bottom-28 -left-28 w-72 h-72 rounded-full opacity-10"
        style="background: {secondary}"
      ></div>

      <header class="relative px-6 pt-10 pb-8 sm:px-12 sm:pt-14 text-center">
        <div class="flex items-center justify-center gap-2 text-[11px] uppercase tracking-[0.32em] opacity-70">
          <Sparkles class="w-3.5 h-3.5" style="color: {primary}" />
          <span>Undangan Digital</span>
        </div>

        <p class="mt-6 text-sm sm:text-base opacity-80">{data.event.organizer}</p>

        <h1
          class="mt-3 text-3xl sm:text-5xl leading-tight font-semibold"
          style="font-family: {headingFont}; color: {primary};"
        >
          {data.event.name}
        </h1>

        <div class="mt-6 flex items-center justify-center gap-3">
          <span class="h-px w-16 sm:w-24" style="background: {accent}"></span>
          <span class="w-1.5 h-1.5 rotate-45" style="background: {accent}"></span>
          <span class="h-px w-16 sm:w-24" style="background: {accent}"></span>
        </div>

        <div class="mt-8 inline-block rounded-2xl px-6 py-4" style="background: color-mix(in srgb, {primary} 8%, transparent);">
          <p class="text-[11px] uppercase tracking-[0.2em] opacity-60">Kepada Yth.</p>
          <p class="mt-1 text-xl sm:text-2xl font-semibold" style="font-family: {headingFont}; color: {secondary};">
            {data.guest.name}
          </p>
          {#if data.guest.category}
            <p class="mt-1 text-xs opacity-60">{data.guest.category}</p>
          {/if}
        </div>
      </header>

      {#if data.event.banner_url}
        <div class="relative px-6 sm:px-10">
          <img
            src={data.event.banner_url}
            alt="Banner {data.event.name}"
            class="w-full h-52 sm:h-72 object-cover rounded-2xl shadow-md"
          />
        </div>
      {/if}

      <section class="relative px-6 sm:px-12 py-10">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl border p-4" style="border-color: color-mix(in srgb, {accent} 35%, transparent); background: color-mix(in srgb, white 70%, {background});">
            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wider opacity-60">
              <CalendarDays class="w-4 h-4" style="color: {primary}" /> Tanggal
            </div>
            <p class="mt-2 text-sm font-semibold">{eventDate}</p>
          </div>

          <div class="rounded-2xl border p-4" style="border-color: color-mix(in srgb, {accent} 35%, transparent); background: color-mix(in srgb, white 70%, {background});">
            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wider opacity-60">
              <Clock class="w-4 h-4" style="color: {primary}" /> Waktu
            </div>
            <p class="mt-2 text-sm font-semibold">{eventTime}</p>
          </div>

          <div class="sm:col-span-2 rounded-2xl border p-4" style="border-color: color-mix(in srgb, {accent} 35%, transparent); background: color-mix(in srgb, white 70%, {background});">
            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wider opacity-60">
              <MapPin class="w-4 h-4" style="color: {primary}" /> Lokasi
            </div>
            <p class="mt-2 text-sm font-semibold">{data.event.location}</p>
            {#if data.event.address}
              <p class="mt-1 text-xs opacity-70 leading-relaxed">{data.event.address}</p>
            {/if}
          </div>
        </div>

        {#if data.event.description}
          <p class="mt-8 text-center text-sm leading-relaxed opacity-80 whitespace-pre-line">
            {data.event.description}
          </p>
        {/if}

        {#if data.event.maps_url}
          <div class="mt-6 text-center">
            <a
              href={data.event.maps_url}
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-90"
              style="background: {primary};"
            >
              <MapPinned class="w-4 h-4" /> Buka Lokasi di Google Maps
            </a>
          </div>
        {/if}
      </section>

      <section class="relative px-6 sm:px-12 pb-12">
        <div class="rounded-3xl border bg-white p-6 text-center shadow-sm" style="border-color: color-mix(in srgb, {accent} 40%, transparent);">
          <div class="flex items-center justify-center gap-2 text-stone-800">
            <QrCode class="w-5 h-5" />
            <h2 class="text-base font-semibold">QR Code Kehadiran</h2>
          </div>
          <p class="text-xs text-stone-500 mt-1">
            Tunjukkan QR Code ini kepada petugas saat tiba di lokasi acara.
          </p>

          <div class="mt-5 mx-auto w-52 sm:w-60 rounded-2xl border border-stone-200 p-3">
            <img src={qrUrl} alt="QR Code kehadiran {data.guest.name}" class="w-full h-auto" />
          </div>

          <p class="mt-4 text-sm font-semibold text-stone-900">{data.guest.name}</p>
          <p class="text-xs text-stone-500">{data.event.name}</p>

          {#if data.checked_in}
            <div class="mt-5 flex items-start gap-3 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-left">
              <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
              <div>
                <p class="text-sm font-semibold text-emerald-800">Kehadiran sudah tercatat</p>
                <p class="text-xs text-emerald-700 mt-0.5">
                  Check-in pada {checkedInAt}{#if data.attendance?.checked_in_by} oleh {data.attendance.checked_in_by}{/if}.
                </p>
              </div>
            </div>
          {:else}
            <p class="mt-4 text-[11px] text-stone-400">
              QR Code bersifat unik dan hanya berlaku untuk tamu ini.
            </p>
          {/if}

          <button
            onclick={downloadPng}
            disabled={downloading}
            class="mt-5 inline-flex items-center gap-2 rounded-xl border border-stone-300 px-4 py-2 text-xs font-medium text-stone-700 hover:bg-stone-50 transition-colors disabled:opacity-50"
          >
            <Download class="w-4 h-4" />
            {downloading ? 'Membuat PNG...' : 'Unduh QR Code (PNG)'}
          </button>
        </div>
      </section>

      <footer class="relative px-6 pb-10 text-center">
        <div class="mx-auto mb-6 h-px w-24" style="background: {accent}"></div>
        <p class="text-xs opacity-70">Hormat kami,</p>
        <p class="mt-1 text-sm font-semibold" style="font-family: {headingFont};">{data.event.organizer}</p>
        <p class="mt-6 text-[10px] uppercase tracking-[0.25em] opacity-40">Invitaku · Digital Invitation</p>
      </footer>
    </article>

    <p class="mt-6 text-center text-[11px] opacity-50">
      Undangan ini dibuat khusus untuk {data.guest.name}. Mohon tidak membagikan QR Code kepada orang lain.
    </p>
  </main>
</div>
