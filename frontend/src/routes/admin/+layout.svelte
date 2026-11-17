<script lang="ts">
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { auth } from '$lib/stores/auth';
  import AdminSidebar from '$lib/components/AdminSidebar.svelte';
  import Spinner from '$lib/components/Spinner.svelte';
  import { Menu, Calendar } from '@lucide/svelte';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();

  let mobileOpen = $state(false);

  let user = $derived($auth.user);
  let ready = $derived($auth.initialized);

  $effect(() => {
    if (!ready) return;

    if (!user) {
      goto('/login');
      return;
    }

    if (user.role === 'scanner' && page.url.pathname !== '/admin/scanner') {
      goto('/admin/scanner');
    }
  });

  $effect(() => {
    page.url.pathname;
    mobileOpen = false;
  });

  const titles: Record<string, string> = {
    '/admin': 'Dashboard',
    '/admin/events': 'Manajemen Acara',
    '/admin/templates': 'Desain Undangan',
    '/admin/guests': 'Data Tamu',
    '/admin/invitations': 'Undangan',
    '/admin/scanner': 'Scanner Kehadiran',
    '/admin/reports': 'Laporan Kehadiran',
    '/admin/settings': 'Pengaturan'
  };

  let title = $derived.by(() => {
    const path = page.url.pathname;
    if (path.startsWith('/admin/events/')) return 'Detail Acara';
    return titles[path] ?? 'Dashboard';
  });

  let today = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
</script>

<svelte:head>
  <title>{title} — Invitaku Admin</title>
</svelte:head>

{#if !ready || !user}
  <div class="min-h-screen flex items-center justify-center bg-[#f7f5f2]">
    <Spinner label="Memuat sesi..." full />
  </div>
{:else}
  <div class="min-h-screen bg-[#f7f5f2] flex">
    <AdminSidebar open={mobileOpen} onclose={() => (mobileOpen = false)} />

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="sticky top-0 z-30 bg-[#f7f5f2]/90 backdrop-blur border-b border-stone-200/80">
        <div class="flex items-center justify-between gap-4 h-16 px-4 sm:px-6 lg:px-8">
          <div class="flex items-center gap-3 min-w-0">
            <button
              class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-200"
              onclick={() => (mobileOpen = true)}
              aria-label="Buka menu"
            >
              <Menu class="w-5 h-5" />
            </button>
            <div class="min-w-0">
              <h1 class="text-lg sm:text-xl font-semibold text-stone-900 truncate">{title}</h1>
              <p class="hidden sm:flex items-center gap-1.5 text-[11px] text-stone-500">
                <Calendar class="w-3 h-3" /> {today}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <span class="hidden sm:inline-flex badge bg-white border border-stone-200 text-stone-600">
              {user.role === 'admin' ? 'Administrator' : 'Petugas Scanner'}
            </span>
            <div class="w-9 h-9 rounded-full bg-[#7b2d43] text-white flex items-center justify-center text-sm font-semibold">
              {user.name.charAt(0).toUpperCase()}
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        {@render children()}
      </main>

      <footer class="px-4 sm:px-6 lg:px-8 py-4 text-[11px] text-stone-400 border-t border-stone-200/70">
        Invitaku · Sistem Undangan Digital & Check-in QR Code
      </footer>
    </div>
  </div>
{/if}
