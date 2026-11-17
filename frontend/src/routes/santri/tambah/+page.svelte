<script lang="ts">
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { Guest, SantriType } from '$lib/types';
  import {
    UserPlus,
    GraduationCap,
    UserRound,
    User,
    Phone,
    Loader2,
    CheckCircle2,
    Copy,
    Users
  } from '@lucide/svelte';

  let tipe = $state<SantriType>('Santri');
  let name = $state('');
  let waliAngkatan = $state('');
  let phone = $state('');
  let saving = $state(false);
  let error = $state('');
  let fieldErrors = $state<Record<string, string[]>>({});
  let created = $state<Guest | null>(null);

  const isSantri = $derived(tipe === 'Santri');

  function resetForm() {
    name = '';
    waliAngkatan = '';
    phone = '';
    error = '';
    fieldErrors = {};
  }

  function changeType(value: SantriType) {
    tipe = value;
    resetForm();
  }

  async function handleSubmit(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    error = '';
    fieldErrors = {};

    try {
      const res = await api.createSantriGuest({
        name: name.trim(),
        tipe,
        wali_angkatan: waliAngkatan.trim() || undefined,
        whatsapp_number: phone.trim()
      });

      created = res.data;
      toast.success(`Tamu ${res.data.name} berhasil ditambahkan.`);
      resetForm();
    } catch (err: any) {
      error = err.message || 'Gagal menambahkan tamu.';
      fieldErrors = err.errors ?? {};
      toast.error(error);
    } finally {
      saving = false;
    }
  }

  async function copyLink() {
    const url = created?.invitation?.invitation_url;
    if (!url) return;

    try {
      await navigator.clipboard.writeText(url);
      toast.success('Link undangan disalin.');
    } catch {
      toast.error('Gagal menyalin link.');
    }
  }

  function fieldError(key: string) {
    return fieldErrors[key]?.[0];
  }
</script>

<svelte:head><title>Tambah Tamu Santri — Invitaku</title></svelte:head>

<div class="max-w-3xl mx-auto space-y-5">
  <div>
    <p class="text-[10px] font-bold uppercase tracking-[0.4em] text-[#d4af37]">Tambah Tamu</p>
    <h2 class="mt-2 font-playfair text-3xl text-[#fff5d6]">Form input tamu undangan</h2>
    <p class="mt-2 text-sm leading-7 text-stone-500">
      Pilih tipe tamu terlebih dahulu, lalu isi data yang dibutuhkan. Kode tamu dan link undangan dibuat otomatis.
    </p>
  </div>

  {#if created}
    <div class="rounded-3xl border border-emerald-500/25 bg-emerald-950/20 p-5 sm:p-6">
      <div class="flex items-start gap-3">
        <div class="w-11 h-11 rounded-full bg-emerald-500/15 text-emerald-400 flex items-center justify-center shrink-0">
          <CheckCircle2 class="w-6 h-6" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-emerald-200">Tamu berhasil ditambahkan</p>
          <p class="mt-1 text-sm text-emerald-100/70">
            {created.name} · {created.category} · Kode <span class="font-semibold tracking-widest">{created.santri_code}</span>
          </p>

          {#if created.invitation?.invitation_url}
            <div class="mt-4 flex flex-col sm:flex-row gap-2">
              <input
                class="min-w-0 flex-1 rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs text-stone-300"
                readonly
                value={created.invitation.invitation_url}
              />
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-3 py-2 text-xs text-stone-300 hover:bg-white/5"
                onclick={copyLink}
              >
                <Copy class="w-3.5 h-3.5" /> Salin link
              </button>
            </div>
          {/if}

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-4 py-2 text-xs font-semibold text-[#080808]"
              onclick={() => (created = null)}
            >
              <UserPlus class="w-4 h-4" /> Tambah lagi
            </button>
            <a
              href="/santri/tamu"
              class="inline-flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 text-xs text-stone-300 hover:bg-white/5"
            >
              <Users class="w-4 h-4" /> Daftar tamu
            </a>
          </div>
        </div>
      </div>
    </div>
  {/if}

  <form class="rounded-3xl border border-white/5 bg-[#0e0d0b] p-5 sm:p-7 space-y-5" onsubmit={handleSubmit}>
    {#if error}
      <div class="rounded-2xl border border-rose-500/25 bg-rose-950/30 px-4 py-3 text-sm text-rose-200">
        {error}
        {#if Object.keys(fieldErrors).length}
          <ul class="mt-2 list-inside list-disc text-xs space-y-0.5 text-rose-300/80">
            {#each Object.values(fieldErrors).flat() as message}
              <li>{message}</li>
            {/each}
          </ul>
        {/if}
      </div>
    {/if}

    <div>
      <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Tipe Tamu</span>
      <div class="grid grid-cols-2 gap-3">
        <button
          type="button"
          class="flex items-center gap-3 rounded-2xl border px-4 py-4 text-left transition {isSantri
            ? 'border-[#d4af37]/60 bg-[#d4af37]/10 text-[#fff5d6]'
            : 'border-white/10 bg-black/10 text-stone-400 hover:border-white/20'}"
          onclick={() => changeType('Santri')}
        >
          <span class="w-10 h-10 rounded-xl flex items-center justify-center {isSantri ? 'bg-[#d4af37] text-[#080808]' : 'bg-white/5'}">
            <GraduationCap class="w-5 h-5" />
          </span>
          <span>
            <span class="block text-sm font-semibold">Santri</span>
            <span class="block text-[11px] opacity-60">Wali santri</span>
          </span>
        </button>

        <button
          type="button"
          class="flex items-center gap-3 rounded-2xl border px-4 py-4 text-left transition {!isSantri
            ? 'border-sky-400/50 bg-sky-400/10 text-sky-100'
            : 'border-white/10 bg-black/10 text-stone-400 hover:border-white/20'}"
          onclick={() => changeType('Alumni')}
        >
          <span class="w-10 h-10 rounded-xl flex items-center justify-center {!isSantri ? 'bg-sky-400 text-[#080808]' : 'bg-white/5'}">
            <UserRound class="w-5 h-5" />
          </span>
          <span>
            <span class="block text-sm font-semibold">Alumni</span>
            <span class="block text-[11px] opacity-60">Angkatan</span>
          </span>
        </button>
      </div>
    </div>

    <div>
      <label class="mb-1.5 block text-sm font-medium text-stone-300" for="santri-name">
        {isSantri ? 'Nama Santri' : 'Nama Alumni'} <span class="text-rose-400">*</span>
      </label>
      <div class="relative">
        <User class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-600" />
        <input
          id="santri-name"
          class="w-full rounded-xl border border-white/10 bg-black/20 py-3 pl-10 pr-3.5 text-sm text-stone-100 placeholder:text-stone-600 outline-none transition focus:border-[#d4af37]/60 focus:ring-2 focus:ring-[#d4af37]/10"
          placeholder={isSantri ? 'Contoh: Ahmad Fauzi' : 'Contoh: Fikri Maulana'}
          bind:value={name}
          required
        />
      </div>
      {#if fieldError('name')}<p class="mt-1 text-xs text-rose-400">{fieldError('name')}</p>{/if}
    </div>

    <div>
      <label class="mb-1.5 block text-sm font-medium text-stone-300" for="wali-angkatan">
        {isSantri ? 'Nama Orang Tua / Wali' : 'Angkatan'} <span class="text-stone-600">(opsional)</span>
      </label>
      <input
        id="wali-angkatan"
        class="w-full rounded-xl border border-white/10 bg-black/20 px-3.5 py-3 text-sm text-stone-100 placeholder:text-stone-600 outline-none transition focus:border-[#d4af37]/60 focus:ring-2 focus:ring-[#d4af37]/10"
        placeholder={isSantri ? 'Contoh: Bapak Ahmad Subki' : 'Contoh: Angkatan 2024'}
        bind:value={waliAngkatan}
      />
      {#if fieldError('wali_angkatan')}<p class="mt-1 text-xs text-rose-400">{fieldError('wali_angkatan')}</p>{/if}
      {#if isSantri}
        <p class="mt-1.5 text-[11px] text-stone-600">
          Dipakai sebagai nama sapaan di pesan WhatsApp. Undangan tetap menampilkan nama santri.
        </p>
      {/if}
    </div>

    <div>
      <label class="mb-1.5 block text-sm font-medium text-stone-300" for="phone">
        Nomor HP / WhatsApp <span class="text-rose-400">*</span>
      </label>
      <div class="relative">
        <Phone class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-600" />
        <input
          id="phone"
          type="tel"
          inputmode="tel"
          class="w-full rounded-xl border border-white/10 bg-black/20 py-3 pl-10 pr-3.5 text-sm text-stone-100 placeholder:text-stone-600 outline-none transition focus:border-[#d4af37]/60 focus:ring-2 focus:ring-[#d4af37]/10"
          placeholder="081234567890"
          bind:value={phone}
          required
        />
      </div>
      {#if fieldError('whatsapp_number')}<p class="mt-1 text-xs text-rose-400">{fieldError('whatsapp_number')}</p>{/if}
      <p class="mt-1.5 text-[11px] text-stone-600">
        {isSantri
          ? 'Nomor WhatsApp orang tua/wali. Tidak ditampilkan publik.'
          : 'Nomor dipakai untuk tombol kirim WhatsApp. Tidak ditampilkan publik.'}
      </p>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
      <a href="/santri/tamu" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-400 hover:bg-white/5">
        Batal
      </a>
      <button
        type="submit"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-5 py-2.5 text-sm font-semibold text-[#080808] transition hover:brightness-110 disabled:opacity-60"
        disabled={saving}
      >
        {#if saving}
          <Loader2 class="w-4 h-4 animate-spin" /> Menyimpan...
        {:else}
          <UserPlus class="w-4 h-4" /> Simpan Tamu
        {/if}
      </button>
    </div>
  </form>

  <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] px-5 py-4 text-xs leading-6 text-stone-500">
    Data disimpan ke tabel <code class="text-stone-300">guests</code> pada acara santri otomatis.
    Kode tamu memiliki format <span class="text-[#d4af37]">S001</span> untuk santri dan
    <span class="text-[#d4af37]">A001</span> untuk alumni.
  </div>
</div>
