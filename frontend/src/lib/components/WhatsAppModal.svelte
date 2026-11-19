<script lang="ts">
  import { untrack } from 'svelte';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { Guest, Invitation } from '$lib/types';
  import Modal from './Modal.svelte';
  import DeliveryBadge from './DeliveryBadge.svelte';
  import {
    MessageCircle,
    Send,
    ExternalLink,
    Loader2,
    CheckCircle2,
    AlertTriangle,
    Smartphone,
    Server
  } from '@lucide/svelte';

  interface Props {
    open?: boolean;
    guest: Guest | null;
    invitation?: Invitation | null;
    eventName?: string;
    onclose: () => void;
    onupdated?: () => void;
  }

  let { open = false, guest, invitation, eventName, onclose, onupdated }: Props = $props();

  const initialInvitation = untrack(() => invitation);

  let mode = $state<'click' | 'api'>('click');
  let sending = $state(false);
  let error = $state('');
  let waUrl = $state('');
  let messagePreview = $state('');
  let currentInvitation = $state<Invitation | null>(initialInvitation ?? null);
  let markedSent = $state(false);

  $effect(() => {
    if (open) {
      currentInvitation = invitation ?? guest?.invitation ?? null;
      error = '';
      waUrl = '';
      messagePreview = '';
      markedSent = false;
      mode = 'click';
    }
  });

  async function handleSend() {
    if (!guest) return;
    sending = true;
    error = '';

    // Buka tab kosong selagi masih dalam user gesture, supaya popup blocker
    // tidak menolak window.open setelah await fetch selesai.
    const pendingWin = mode === 'click' ? window.open('about:blank', '_blank') : null;

    try {
      const res = await api.sendWhatsApp(guest.id, { mode });
      currentInvitation = res.data.invitation;

      if (mode === 'click' && res.data.wa_url) {
        waUrl = res.data.wa_url;
        messagePreview = res.data.message ?? '';
        let opened = false;
        try {
          if (pendingWin && !pendingWin.closed && pendingWin.location) {
            pendingWin.location.href = waUrl;
            pendingWin.opener = null;
            opened = true;
          }
        } catch {
          opened = false;
        }
        if (!opened) {
          window.open(waUrl, '_blank', 'noopener,noreferrer');
        }
        toast.success('WhatsApp dibuka. Tekan kirim, lalu tandai terkirim di panel ini.');
      } else {
        toast.success('Undangan berhasil dikirim melalui WhatsApp Business API.');
        markedSent = true;
      }

      onupdated?.();
    } catch (err: any) {
      pendingWin?.close();
      error = err.message || 'Gagal mengirim undangan.';
      if (err.payload?.data?.invitation) {
        currentInvitation = err.payload.data.invitation;
      }
      onupdated?.();
    } finally {
      sending = false;
    }
  }

  async function markSent() {
    if (!currentInvitation) return;
    sending = true;
    try {
      const res = await api.markInvitationSent(currentInvitation.id);
      currentInvitation = res.data;
      markedSent = true;
      toast.success('Status pengiriman ditandai terkirim.');
      onupdated?.();
      onclose();
    } catch (err: any) {
      toast.error(err.message || 'Gagal memperbarui status.');
    } finally {
      sending = false;
    }
  }
</script>

<Modal
  {open}
  title="Kirim Undangan WhatsApp"
  description={guest ? `Kirim undangan untuk ${guest.name}` : undefined}
  onclose={onclose}
  size="md"
  footer={undefined}
>
  {#if guest}
    <div class="space-y-5">
      <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3">
        <p class="text-sm font-semibold text-stone-900">{guest.name}</p>
        <p class="text-xs text-stone-500 mt-0.5">+{guest.whatsapp_number} · {eventName ?? guest.event?.name ?? '-'}</p>
        <div class="mt-2">
          <DeliveryBadge status={currentInvitation?.delivery_status ?? 'not_sent'} />
        </div>
      </div>

      {#if !waUrl}
        <div>
          <p class="label">Mode pengiriman</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <button
              type="button"
              class="rounded-xl border p-3 text-left transition-colors {mode === 'click'
                ? 'border-[#7b2d43] bg-[#7b2d43]/5 ring-1 ring-[#7b2d43]/20'
                : 'border-stone-200 hover:border-stone-300'}"
              onclick={() => (mode = 'click')}
            >
              <Smartphone class="w-5 h-5 text-[#7b2d43]" />
              <p class="mt-2 text-sm font-semibold text-stone-900">Click to Chat</p>
              <p class="text-[11px] text-stone-500 mt-1 leading-relaxed">
                Membuka WhatsApp dengan pesan terisi. Admin menekan kirim secara manual.
              </p>
            </button>

            <button
              type="button"
              class="rounded-xl border p-3 text-left transition-colors {mode === 'api'
                ? 'border-[#7b2d43] bg-[#7b2d43]/5 ring-1 ring-[#7b2d43]/20'
                : 'border-stone-200 hover:border-stone-300'}"
              onclick={() => (mode = 'api')}
            >
              <Server class="w-5 h-5 text-[#7b2d43]" />
              <p class="mt-2 text-sm font-semibold text-stone-900">WhatsApp Business API</p>
              <p class="text-[11px] text-stone-500 mt-1 leading-relaxed">
                Pengiriman otomatis melalui provider resmi (perlu konfigurasi backend).
              </p>
            </button>
          </div>
        </div>

        {#if mode === 'api'}
          <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-[12px] text-sky-800 leading-relaxed">
            Mode API hanya berfungsi jika <code class="font-mono">WHATSAPP_API_ENABLED</code> dan kredensial
            WhatsApp Business Cloud API sudah diisi pada <code class="font-mono">backend/.env</code>.
            Status <span class="font-medium">Terkirim</span> hanya disimpan setelah provider merespons sukses.
          </div>
        {/if}

        {#if error}
          <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 flex gap-2">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        {/if}

        <button class="btn-primary w-full" onclick={handleSend} disabled={sending}>
          {#if sending}
            <Loader2 class="w-4 h-4 animate-spin" /> Memproses...
          {:else if mode === 'click'}
            <MessageCircle class="w-4 h-4" /> Buka WhatsApp & Kirim
          {:else}
            <Send class="w-4 h-4" /> Kirim via API
          {/if}
        </button>
      {:else}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex gap-2">
          <CheckCircle2 class="w-4 h-4 shrink-0 mt-0.5" />
          <span>
            WhatsApp sudah dibuka di tab baru. Setelah pesan benar-benar terkirim, tandai statusnya di bawah.
          </span>
        </div>

        <div class="grid gap-2">
          <a class="btn-secondary w-full" href={waUrl} target="_blank" rel="noopener noreferrer">
            <ExternalLink class="w-4 h-4" /> Buka WhatsApp Lagi
          </a>
          <button class="btn-primary w-full" onclick={markSent} disabled={sending || markedSent}>
            {#if sending}
              <Loader2 class="w-4 h-4 animate-spin" /> Menyimpan...
            {:else if markedSent}
              <CheckCircle2 class="w-4 h-4" /> Sudah Ditandai Terkirim
            {:else}
              <CheckCircle2 class="w-4 h-4" /> Tandai Sudah Terkirim
            {/if}
          </button>
        </div>

        {#if messagePreview}
          <details class="rounded-xl border border-stone-200 bg-white">
            <summary class="px-4 py-2.5 text-xs font-medium text-stone-600 cursor-pointer">Lihat pesan yang disiapkan</summary>
            <pre class="px-4 pb-4 text-[11px] text-stone-600 whitespace-pre-wrap font-sans leading-relaxed">{messagePreview}</pre>
          </details>
        {/if}
      {/if}
    </div>
  {/if}
</Modal>
