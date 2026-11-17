<script lang="ts">
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { auth } from '$lib/stores/auth';
  import {
    GraduationCap,
    LayoutDashboard,
    Users,
    UserPlus,
    ScanLine,
    ArrowLeft,
    LogOut,
    Menu,
    X,
    Sparkles
  } from '@lucide/svelte';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();

  let mobileOpen = $state(false);

  let user = $derived($auth.user);
  let ready = $derived($auth.initialized);
  let isPublic = $derived(page.url.pathname.startsWith('/santri/undangan'));

  const adminMenu = [
    { label: 'Dashboard', href: '/santri', icon: LayoutDashboard, exact: true },
    { label: 'Daftar Tamu', href: '/santri/tamu', icon: Users, exact: true },
    { label: 'Tambah Tamu', href: '/santri/tambah', icon: UserPlus, exact: true },
    { label: 'Scanner', href: '/santri/scan', icon: ScanLine, exact: true }
  ];

  const scannerMenu = [
    { label: 'Scan Santri', href: '/santri/scan', icon: ScanLine, exact: true }
  ];

  let menu = $derived(user?.role === 'scanner' ? scannerMenu : adminMenu);

  const titles: Record<string, string> = {
    '/santri': 'Dashboard Santri',
    '/santri/tamu': 'Daftar Tamu Santri',
    '/santri/tambah': 'Tambah Tamu Santri',
    '/santri/scan': 'Scanner Santri'
  };

  let title = $derived(titles[page.url.pathname] ?? 'Mode Santri');

  $effect(() => {
    if (isPublic || !ready) return;

    if (!user) {
      goto('/login');
      return;
    }

    if (user.role === 'scanner' && page.url.pathname !== '/santri/scan') {
      goto('/santri/scan');
    }
  });

  $effect(() => {
    page.url.pathname;
    mobileOpen = false;
  });

  function isActive(href: string, exact = false) {
    const current = page.url.pathname;
    if (exact) return current === href;
    return current === href || current.startsWith(href + '/');
  }

  async function handleLogout() {
    await auth.logout();
    goto('/login');
  }
</script>

<svelte:head>
  <title>{isPublic ? 'Undangan Santri' : `${title} — Invitaku`}</title>
</svelte:head>

{#if isPublic}
  {@render children()}
{:else if !ready || !user}
  <div class="min-h-screen bg-[#080808] flex items-center justify-center text-[#d4af37]">
    <div class="flex flex-col items-center gap-3">
      <Sparkles class="w-8 h-8 animate-pulse" />
      <p class="text-xs uppercase tracking-[0.35em] text-stone-500">Memuat mode santri...</p>
    </div>
  </div>
{:else}
  <div class="min-h-screen bg-[#080808] text-stone-100 flex">
    {#if mobileOpen}
      <button
        type="button"
        class="fixed inset-0 z-40 bg-black/70 lg:hidden"
        onclick={() => (mobileOpen = false)}
        aria-label="Tutup menu"
      ></button>
    {/if}

    <aside
      class="fixed inset-y-0 left-0 z-50 w-72 bg-[#0e0d0b] border-r border-[#d4af37]/15 flex flex-col transition-transform duration-200 lg:translate-x-0 lg:static lg:z-auto {mobileOpen
        ? 'translate-x-0'
        : '-translate-x-full'}"
    >
      <div class="flex items-center justify-between px-5 h-20 border-b border-white/5 shrink-0">
        <a href="/santri" class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-[#d4af37] flex items-center justify-center shadow-lg shadow-black/30">
            <GraduationCap class="w-5 h-5 text-[#080808]" />
          </div>
          <div>
            <p class="font-playfair text-lg font-semibold text-[#fff5d6] leading-tight">Mode Santri</p>
            <p class="text-[10px] uppercase tracking-[0.22em] text-stone-500">Wisuda &amp; Khidmah</p>
          </div>
        </a>

        <button
          class="lg:hidden p-2 rounded-lg hover:bg-white/10"
          onclick={() => (mobileOpen = false)}
          aria-label="Tutup"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-5 space-y-1">
        <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">
          Menu Santri
        </p>

        {#each menu as item (item.href)}
          <a
            href={item.href}
            onclick={() => (mobileOpen = false)}
            class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {isActive(
              item.href,
              item.exact
            )
              ? 'bg-[#d4af37] text-[#080808] shadow-sm font-semibold'
              : 'text-stone-400 hover:bg-white/5 hover:text-[#fff5d6]'}"
          >
            <item.icon class="w-[18px] h-[18px] shrink-0" />
            <span class="truncate">{item.label}</span>
          </a>
        {/each}

        <div class="pt-4 mt-4 border-t border-white/5">
          <a
            href={user.role === 'scanner' ? '/admin/scanner' : '/admin'}
            class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-stone-500 hover:bg-white/5 hover:text-stone-200 transition-colors"
          >
            <ArrowLeft class="w-[18px] h-[18px] shrink-0" />
            <span class="truncate">Kembali ke Invitaku</span>
          </a>
        </div>
      </nav>

      <div class="p-4 border-t border-white/5">
        <div class="flex items-center gap-3 px-2 py-2">
          <div class="w-10 h-10 rounded-full bg-[#d4af37] flex items-center justify-center text-[#080808] font-semibold shrink-0">
            {user.name.charAt(0).toUpperCase()}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-stone-100 truncate">{user.name}</p>
            <p class="text-[11px] text-stone-500">
              {user.role === 'admin' ? 'Administrator' : 'Petugas Scanner'}
            </p>
          </div>
          <button
            onclick={handleLogout}
            class="p-2 rounded-lg text-stone-500 hover:text-rose-300 hover:bg-white/5 transition-colors"
            title="Logout"
            aria-label="Logout"
          >
            <LogOut class="w-5 h-5" />
          </button>
        </div>
      </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="sticky top-0 z-30 bg-[#080808]/90 backdrop-blur border-b border-[#d4af37]/10">
        <div class="flex items-center justify-between gap-4 h-16 px-4 sm:px-6 lg:px-8">
          <div class="flex items-center gap-3 min-w-0">
            <button
              class="lg:hidden p-2 rounded-lg text-stone-300 hover:bg-white/5"
              onclick={() => (mobileOpen = true)}
              aria-label="Buka menu"
            >
              <Menu class="w-5 h-5" />
            </button>
            <div class="min-w-0">
              <h1 class="text-lg sm:text-xl font-semibold text-[#fff5d6] truncate">{title}</h1>
              <p class="hidden sm:block text-[11px] text-stone-500">Mode sederhana · database Invitaku</p>
            </div>
          </div>

          <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-[#d4af37]/25 bg-[#d4af37]/10 px-3 py-1 text-[11px] font-medium text-[#d4af37]">
            <Sparkles class="w-3 h-3" /> Santri
          </span>
        </div>
      </header>

      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        {@render children()}
      </main>

      <footer class="px-4 sm:px-6 lg:px-8 py-4 text-[11px] text-stone-600 border-t border-white/5">
        Invitaku · Mode Santri · Data tersimpan di MySQL akasandhya
      </footer>
    </div>
  </div>
{/if}
