<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import type { PublicInvitationData } from '$lib/types';
  import InvitationRenderer from '$lib/components/InvitationRenderer.svelte';
  import Spinner from '$lib/components/Spinner.svelte';
  import { Link2Off, RefreshCw, Sparkles, AlertCircle } from '@lucide/svelte';

  let data = $state<PublicInvitationData | null>(null);
  let loading = $state(true);
  let error = $state('');

  async function load(token: string) {
    if (!token) {
      loading = false;
      error = 'Tautan undangan tidak valid.';
      return;
    }

    loading = true;
    error = '';
    data = null;

    try {
      const res = await api.getPublicInvitation(token);
      data = res.data;
    } catch (err: any) {
      error =
        err.status === 404
          ? 'Undangan tidak ditemukan. Tautan mungkin salah, sudah kedaluwarsa, atau tamu telah dihapus.'
          : err.message || 'Gagal memuat undangan. Periksa koneksi internet Anda.';
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    load(page.params.token ?? '');
  });
</script>

<svelte:head>
  <title>Undangan Digital — Invitaku</title>
  <meta name="robots" content="noindex, nofollow" />
</svelte:head>

{#if loading}
  <div class="min-h-screen flex items-center justify-center bg-[#faf8f6] px-6">
    <div class="text-center">
      <div class="mx-auto w-14 h-14 rounded-2xl bg-[#7b2d43] text-white flex items-center justify-center shadow-lg shadow-[#7b2d43]/20">
        <Sparkles class="w-7 h-7" />
      </div>
      <p class="mt-5 font-playfair text-2xl text-stone-800">Menyiapkan undangan Anda</p>
      <div class="mt-4">
        <Spinner label="Memuat undangan..." />
      </div>
    </div>
  </div>
{:else if error || !data}
  <div class="min-h-screen flex items-center justify-center bg-[#faf8f6] px-6 py-12">
    <div class="w-full max-w-md text-center">
      <div class="mx-auto w-16 h-16 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center border border-rose-100">
        <Link2Off class="w-8 h-8" />
      </div>

      <h1 class="mt-6 font-playfair text-2xl sm:text-3xl font-semibold text-stone-900">
        Undangan Tidak Dapat Dibuka
      </h1>
      <p class="mt-3 text-sm text-stone-600 leading-relaxed">{error}</p>

      <div class="mt-6 rounded-2xl border border-stone-200 bg-white p-4 text-left">
        <div class="flex gap-2 text-xs text-stone-500 leading-relaxed">
          <AlertCircle class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
          <span>
            Pastikan Anda membuka tautan lengkap yang diterima dari penyelenggara acara. Setiap tamu
            memiliki tautan dan QR Code unik. Jika masalah berlanjut, hubungi penyelenggara.
          </span>
        </div>
      </div>

      <button class="btn-primary mt-6 text-sm" onclick={() => load(page.params.token ?? '')}>
        <RefreshCw class="w-4 h-4" /> Coba Lagi
      </button>

      <div class="mt-10 flex items-center justify-center gap-2 text-[11px] uppercase tracking-[0.25em] text-stone-400">
        <Sparkles class="w-3.5 h-3.5" /> Invitaku
      </div>
    </div>
  </div>
{:else}
  <InvitationRenderer data={data} />
{/if}
