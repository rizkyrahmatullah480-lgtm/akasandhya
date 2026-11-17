<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { SantriOverview } from '$lib/types';
  import {
    Users,
    UserCheck,
    GraduationCap,
    UserRound,
    UserPlus,
    ScanLine,
    CalendarDays,
    MapPin,
    Clock,
    ArrowRight,
    RefreshCw,
    CheckCircle2
  } from '@lucide/svelte';

  let data = $state<SantriOverview | null>(null);
  let loading = $state(true);
  let error = $state('');

  async function load() {
    loading = true;
    error = '';

    try {
      const res = await api.getSantriOverview();
      data = res.data;
    } catch (err: any) {
      error = err.message || 'Gagal memuat data mode santri.';
      toast.error(error);
    } finally {
      loading = false;
    }
  }

  onMount(load);

  function formatDate(value?: string | null) {
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

<svelte:head><title>Dashboard Santri — Invitaku</title></svelte:head>

{#if loading}
  <div class="min-h-[60vh] flex flex-col items-center justify-center text-stone-500">
    <RefreshCw class="w-8 h-8 animate-spin text-[#d4af37]" />
    <p class="mt-3 text-sm">Memuat data santri...</p>
  </div>
{:else if error}
  <div class="rounded-3xl border border-rose-500/30 bg-rose-950/30 p-8 text-center">
    <p class="font-semibold text-rose-200">{error}</p>
    <button
      class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-5 py-2.5 text-sm font-semibold text-[#080808]"
      onclick={load}
    >
      <RefreshCw class="w-4 h-4" /> Coba lagi
    </button>
  </div>
{:else if data}
  <div class="space-y-6">
    <!-- Event hero -->
    <section class="relative overflow-hidden rounded-3xl border border-[#d4af37]/20 bg-gradient-to-br from-[#14110a] via-[#0d0d0d] to-[#080808] p-6 sm:p-8">
      <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[#d4af37]/10 blur-3xl"></div>
      <div class="absolute -left-16 bottom-0 h-48 w-48 rounded-full bg-[#d4af37]/5 blur-2xl"></div>

      <div class="relative flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-[0.45em] text-[#d4af37]">
            Majelis Khidmah · Wisuda Santri
          </p>
          <h2 class="mt-3 font-playfair text-3xl sm:text-4xl font-light text-[#fff5d6]">
            {data.event.name}
          </h2>
          <p class="mt-3 text-sm leading-7 text-stone-400">
            {data.event.description || 'Kelola tamu dan kehadiran acara santri dari satu halaman sederhana.'}
          </p>

          <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs text-stone-400">
            <span class="inline-flex items-center gap-2">
              <CalendarDays class="w-4 h-4 text-[#d4af37]" /> {formatDate(data.event.event_date)}
            </span>
            <span class="inline-flex items-center gap-2">
              <Clock class="w-4 h-4 text-[#d4af37]" /> {data.event.event_time?.slice(0, 5)} WIB
            </span>
            <span class="inline-flex items-center gap-2">
              <MapPin class="w-4 h-4 text-[#d4af37]" /> {data.event.location}
            </span>
          </div>
        </div>

        <div class="shrink-0 rounded-2xl border border-[#d4af37]/20 bg-[#d4af37]/5 p-5 text-center">
          <p class="text-[10px] uppercase tracking-[0.3em] text-[#d4af37]">Kehadiran</p>
          <p class="mt-2 font-playfair text-5xl font-light text-[#fff5d6]">{data.stats.attendance_percentage}%</p>
          <p class="mt-1 text-xs text-stone-500">{data.stats.present} dari {data.stats.total} tamu</p>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
      <a href="/santri/tamu" class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-5 transition hover:border-[#d4af37]/30">
        <div class="flex items-center justify-between text-stone-500">
          <span class="text-[10px] font-semibold uppercase tracking-[0.2em]">Total Tamu</span>
          <Users class="w-5 h-5 text-[#d4af37]" />
        </div>
        <p class="mt-3 font-playfair text-4xl text-[#fff5d6]">{data.stats.total}</p>
        <p class="mt-1 text-[11px] text-stone-600">Semua tamu terdaftar</p>
      </a>

      <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-5">
        <div class="flex items-center justify-between text-stone-500">
          <span class="text-[10px] font-semibold uppercase tracking-[0.2em]">Santri</span>
          <GraduationCap class="w-5 h-5 text-[#d4af37]" />
        </div>
        <p class="mt-3 font-playfair text-4xl text-[#fff5d6]">{data.stats.santri}</p>
        <p class="mt-1 text-[11px] text-stone-600">Tipe tamu santri</p>
      </div>

      <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-5">
        <div class="flex items-center justify-between text-stone-500">
          <span class="text-[10px] font-semibold uppercase tracking-[0.2em]">Alumni</span>
          <UserRound class="w-5 h-5 text-sky-400" />
        </div>
        <p class="mt-3 font-playfair text-4xl text-[#fff5d6]">{data.stats.alumni}</p>
        <p class="mt-1 text-[11px] text-stone-600">Tipe tamu alumni</p>
      </div>

      <div class="rounded-2xl border border-emerald-500/10 bg-[#0e0d0b] p-5">
        <div class="flex items-center justify-between text-stone-500">
          <span class="text-[10px] font-semibold uppercase tracking-[0.2em]">Hadir</span>
          <UserCheck class="w-5 h-5 text-emerald-400" />
        </div>
        <p class="mt-3 font-playfair text-4xl text-emerald-300">{data.stats.present}</p>
        <p class="mt-1 text-[11px] text-stone-600">Sudah check-in</p>
      </div>

      <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] p-5">
        <div class="flex items-center justify-between text-stone-500">
          <span class="text-[10px] font-semibold uppercase tracking-[0.2em]">Belum</span>
          <Clock class="w-5 h-5 text-amber-400" />
        </div>
        <p class="mt-3 font-playfair text-4xl text-amber-200">{data.stats.absent}</p>
        <p class="mt-1 text-[11px] text-stone-600">Belum check-in</p>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="grid sm:grid-cols-3 gap-4">
      <a
        href="/santri/tambah"
        class="group rounded-2xl border border-[#d4af37]/20 bg-[#d4af37]/5 p-5 transition hover:bg-[#d4af37]/10"
      >
        <div class="flex items-center justify-between">
          <div class="w-10 h-10 rounded-xl bg-[#d4af37] text-[#080808] flex items-center justify-center">
            <UserPlus class="w-5 h-5" />
          </div>
          <ArrowRight class="w-4 h-4 text-[#d4af37] transition group-hover:translate-x-1" />
        </div>
        <p class="mt-4 font-semibold text-[#fff5d6]">Tambah Tamu</p>
        <p class="mt-1 text-xs leading-6 text-stone-500">Input tamu santri/alumni dengan kode otomatis.</p>
      </a>

      <a
        href="/santri/tamu"
        class="group rounded-2xl border border-white/5 bg-[#0e0d0b] p-5 transition hover:border-[#d4af37]/30"
      >
        <div class="flex items-center justify-between">
          <div class="w-10 h-10 rounded-xl border border-white/10 text-[#d4af37] flex items-center justify-center">
            <Users class="w-5 h-5" />
          </div>
          <ArrowRight class="w-4 h-4 text-stone-600 transition group-hover:translate-x-1" />
        </div>
        <p class="mt-4 font-semibold text-[#fff5d6]">Daftar Tamu</p>
        <p class="mt-1 text-xs leading-6 text-stone-500">Cari tamu, salin link, kirim WA, dan buka QR.</p>
      </a>

      <a
        href="/santri/scan"
        class="group rounded-2xl border border-white/5 bg-[#0e0d0b] p-5 transition hover:border-[#d4af37]/30"
      >
        <div class="flex items-center justify-between">
          <div class="w-10 h-10 rounded-xl border border-white/10 text-[#d4af37] flex items-center justify-center">
            <ScanLine class="w-5 h-5" />
          </div>
          <ArrowRight class="w-4 h-4 text-stone-600 transition group-hover:translate-x-1" />
        </div>
        <p class="mt-4 font-semibold text-[#fff5d6]">Scanner Kehadiran</p>
        <p class="mt-1 text-xs leading-6 text-stone-500">Pindai QR atau masukkan kode S001/A001.</p>
      </a>
    </div>

    <!-- Recent check-ins -->
    <section class="rounded-2xl border border-white/5 bg-[#0e0d0b] overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
        <div>
          <h3 class="font-semibold text-[#fff5d6]">Check-in Terbaru</h3>
          <p class="text-xs text-stone-500 mt-0.5">Tamu yang baru tercatat hadir.</p>
        </div>
        <a href="/santri/tamu" class="text-xs font-medium text-[#d4af37] hover:underline">Lihat semua</a>
      </div>

      {#if data.recent.length === 0}
        <div class="p-8 text-center text-sm text-stone-500">
          Belum ada tamu yang check-in. Buka scanner atau tandai hadir dari daftar tamu.
        </div>
      {:else}
        <div class="divide-y divide-white/5">
          {#each data.recent as item (item.id)}
            <div class="flex items-center gap-3 px-5 py-3.5">
              <div class="w-9 h-9 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                <CheckCircle2 class="w-4 h-4" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-stone-200 truncate">{item.guest?.name ?? '-'}</p>
                <p class="text-[11px] text-stone-500">
                  {item.guest?.santri_code ?? '-'} · {item.guest?.category ?? 'Tamu'}
                  {#if item.guest?.wali_angkatan} · {item.guest.wali_angkatan}{/if}
                </p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[11px] text-stone-400">{formatDateTime(item.checked_in_at)}</p>
                {#if item.officer?.name}
                  <p class="text-[10px] text-stone-600">oleh {item.officer.name}</p>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </section>
  </div>
{/if}
