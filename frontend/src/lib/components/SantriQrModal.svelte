<script lang="ts">
  import { toast } from '$lib/stores/toast';
  import type { Guest } from '$lib/types';
  import { X, Download, Copy, ExternalLink, QrCode } from '@lucide/svelte';

  interface Props {
    open?: boolean;
    guest: Guest | null;
    eventName?: string;
    onclose: () => void;
  }

  let { open = false, guest, eventName, onclose }: Props = $props();

  let qrDataUrl = $state('');
  let generating = $state(false);
  let downloading = $state(false);

  let url = $derived(
    guest?.invitation?.invitation_url ??
      (guest && typeof window !== 'undefined'
        ? `${window.location.origin}/santri/undangan/${guest.unique_token}`
        : '')
  );

  $effect(() => {
    if (!open || !guest || !url) {
      qrDataUrl = '';
      return;
    }

    let cancelled = false;
    generating = true;

    void (async () => {
      try {
        const QRCode = (await import('qrcode')).default;
        const dataUrl = await QRCode.toDataURL(url, {
          width: 360,
          margin: 2,
          errorCorrectionLevel: 'H',
          color: { dark: '#221a1c', light: '#ffffff' }
        });

        if (!cancelled) qrDataUrl = dataUrl;
      } catch {
        if (!cancelled) toast.error('QR Code tidak berhasil dibuat.');
      } finally {
        if (!cancelled) generating = false;
      }
    })();

    return () => {
      cancelled = true;
    };
  });

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(url);
      toast.success('Link undangan santri disalin.');
    } catch {
      toast.error('Gagal menyalin link.');
    }
  }

  async function downloadPng() {
    if (!guest || !qrDataUrl) return;
    downloading = true;

    try {
      const link = document.createElement('a');
      link.href = qrDataUrl;
      link.download = `qr-santri-${(guest.santri_code || guest.name).toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
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

{#if open && guest}
  <div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
    <button
      type="button"
      class="absolute inset-0 bg-black/80 backdrop-blur-sm"
      onclick={onclose}
      aria-label="Tutup QR"
    ></button>

    <div class="relative w-full max-w-md rounded-3xl border border-[#d4af37]/20 bg-[#0e0d0b] p-6 shadow-2xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-[#d4af37]">QR Personal</p>
          <h3 class="mt-1 font-playfair text-2xl text-[#fff5d6]">QR Code Tamu</h3>
          <p class="mt-1 text-xs text-stone-500">Tunjukkan kepada petugas saat check-in.</p>
        </div>
        <button
          type="button"
          class="rounded-lg p-2 text-stone-500 hover:bg-white/5 hover:text-stone-200"
          onclick={onclose}
          aria-label="Tutup"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="mt-5 flex justify-center">
        <div class="rounded-2xl border border-[#d4af37]/25 bg-[#080808] p-4">
          {#if qrDataUrl}
            <img src={qrDataUrl} alt="QR code undangan {guest.name}" class="h-64 w-64 rounded-xl" />
          {:else}
            <div class="h-64 w-64 flex items-center justify-center rounded-xl bg-white/5 text-xs text-stone-500">
              {generating ? 'Menyiapkan QR...' : 'QR belum tersedia'}
            </div>
          {/if}
        </div>
      </div>

      <div class="mt-5 text-center">
        <p class="font-playfair text-xl text-[#fff5d6]">{guest.name}</p>
        <p class="mt-1 text-xs text-stone-500">
          {guest.santri_code || '-'} · {guest.category || 'Tamu'}{eventName ? ` · ${eventName}` : ''}
        </p>
      </div>

      <div class="mt-5">
        <label class="mb-1.5 block text-[11px] font-medium text-stone-500" for="santri-invitation-link">Link undangan</label>
        <input
          id="santri-invitation-link"
          class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs text-stone-400"
          readonly
          value={url}
        />
      </div>

      <div class="mt-4 grid grid-cols-2 gap-2">
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-3 py-2.5 text-xs text-stone-300 hover:bg-white/5"
          onclick={copyLink}
        >
          <Copy class="w-4 h-4" /> Salin Link
        </button>
        <a
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-3 py-2.5 text-xs text-stone-300 hover:bg-white/5"
          href={url}
          target="_blank"
          rel="noopener noreferrer"
        >
          <ExternalLink class="w-4 h-4" /> Buka
        </a>
        <button
          type="button"
          class="col-span-2 inline-flex items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-3 py-2.5 text-xs font-semibold text-[#080808] disabled:opacity-60"
          onclick={downloadPng}
          disabled={!qrDataUrl || downloading}
        >
          <Download class="w-4 h-4" /> {downloading ? 'Mengunduh...' : 'Unduh QR (PNG)'}
        </button>
      </div>

      <p class="mt-4 flex items-center justify-center gap-1.5 text-[10px] text-stone-600">
        <QrCode class="w-3 h-3" /> QR berisi link undangan, bukan data pribadi tamu.
      </p>
    </div>
  </div>
{/if}
