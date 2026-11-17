<script lang="ts">
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import Modal from './Modal.svelte';
  import { Download, Copy, ExternalLink, QrCode } from '@lucide/svelte';
  import type { Guest, Invitation } from '$lib/types';

  interface Props {
    open?: boolean;
    guest: Guest | null;
    invitation?: Invitation | null;
    eventName?: string;
    onclose: () => void;
  }

  let { open = false, guest, invitation, eventName, onclose }: Props = $props();

  let downloading = $state(false);

  let url = $derived(
    invitation?.invitation_url ??
      (guest ? `${typeof window !== 'undefined' ? window.location.origin : ''}/undangan/${guest.unique_token}` : '')
  );
  let qrUrl = $derived(guest ? api.qrUrl(guest.unique_token) : '');

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(url);
      toast.success('Link undangan disalin ke clipboard.');
    } catch {
      toast.error('Gagal menyalin link. Salin manual dari kolom di bawah.');
    }
  }

  async function downloadPng() {
    if (!guest) return;
    downloading = true;
    try {
      const QRCode = (await import('qrcode')).default;
      const dataUrl = await QRCode.toDataURL(url, {
        width: 900,
        margin: 2,
        errorCorrectionLevel: 'H',
        color: { dark: '#221a1c', light: '#ffffff' }
      });

      const link = document.createElement('a');
      link.href = dataUrl;
      link.download = `qr-${guest.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      toast.success('QR Code berhasil diunduh sebagai PNG.');
    } catch {
      toast.error('Gagal membuat file PNG. Coba lagi.');
    } finally {
      downloading = false;
    }
  }
</script>

<Modal {open} title="QR Code Undangan" description="Tunjukkan QR Code ini kepada petugas saat registrasi kehadiran." onclose={onclose} size="sm">
  {#if guest}
    <div class="flex flex-col items-center text-center">
      <div class="w-full max-w-[280px] rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
        <img src={qrUrl} alt="QR Code undangan {guest.name}" class="w-full h-auto" />
      </div>

      <p class="mt-4 font-semibold text-stone-900">{guest.name}</p>
      {#if eventName}
        <p class="text-sm text-stone-500">{eventName}</p>
      {/if}

      <div class="w-full mt-4">
        <label class="label text-left" for="invitation-link">Link undangan</label>
        <input id="invitation-link" class="input text-xs" readonly value={url} />
      </div>

      <div class="grid grid-cols-2 gap-2 w-full mt-4">
        <button class="btn-secondary text-xs" onclick={copyLink}>
          <Copy class="w-4 h-4" /> Salin Link
        </button>
        <a class="btn-secondary text-xs" href={url} target="_blank" rel="noopener noreferrer">
          <ExternalLink class="w-4 h-4" /> Buka
        </a>
        <button class="btn-primary col-span-2 text-xs" onclick={downloadPng} disabled={downloading}>
          <Download class="w-4 h-4" />
          {downloading ? 'Membuat PNG...' : 'Unduh QR (PNG)'}
        </button>
        <a class="btn-ghost col-span-2 text-xs" href={qrUrl} download="qr-undangan.svg">
          <QrCode class="w-4 h-4" /> Unduh versi SVG
        </a>
      </div>

      <p class="text-[11px] text-stone-400 mt-4 leading-relaxed">
        QR Code hanya berisi token undangan unik, bukan data pribadi tamu.
      </p>
    </div>
  {/if}
</Modal>
