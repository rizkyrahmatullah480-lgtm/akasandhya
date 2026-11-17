<script lang="ts">
  import { page } from '$app/state';
  import { auth } from '$lib/stores/auth';
  import { goto } from '$app/navigation';
  import {
    LayoutDashboard,
    CalendarDays,
    Palette,
    Users,
    Mail,
    ScanLine,
    BarChart3,
    Settings,
    LogOut,
    X,
    Sparkles,
    GraduationCap
  } from '@lucide/svelte';
  import type { User } from '$lib/types';

  interface Props {
    open?: boolean;
    onclose?: () => void;
  }

  let { open = false, onclose }: Props = $props();

  const adminMenu = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard, exact: true },
    { label: 'Mode Santri', href: '/santri', icon: GraduationCap },
    { label: 'Acara', href: '/admin/events', icon: CalendarDays },
    { label: 'Desain Undangan', href: '/admin/templates', icon: Palette },
    { label: 'Data Tamu', href: '/admin/guests', icon: Users },
    { label: 'Undangan', href: '/admin/invitations', icon: Mail },
    { label: 'Scanner', href: '/admin/scanner', icon: ScanLine },
    { label: 'Laporan Kehadiran', href: '/admin/reports', icon: BarChart3 },
    { label: 'Pengaturan', href: '/admin/settings', icon: Settings }
  ];

  const scannerMenu = [
    { label: 'Scanner Kehadiran', href: '/admin/scanner', icon: ScanLine, exact: true },
    { label: 'Scan Santri', href: '/santri/scan', icon: GraduationCap, exact: true }
  ];

  let user = $derived($auth.user);
  let menu = $derived(user?.role === 'scanner' ? scannerMenu : adminMenu);

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

{#if open}
  <button
    type="button"
    class="fixed inset-0 z-40 bg-black/40 lg:hidden"
    onclick={onclose}
    aria-label="Tutup menu"
  ></button>
{/if}

<aside
  class="fixed inset-y-0 left-0 z-50 w-72 bg-[#221a1c] text-stone-200 flex flex-col transition-transform duration-200 lg:translate-x-0 lg:static lg:z-auto {open
    ? 'translate-x-0'
    : '-translate-x-full'}"
>
  <div class="flex items-center justify-between px-5 h-20 border-b border-white/10 shrink-0">
    <a href="/admin" class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-[#7b2d43] flex items-center justify-center shadow-lg shadow-black/20">
        <Sparkles class="w-5 h-5 text-white" />
      </div>
      <div>
        <p class="font-playfair text-lg font-semibold text-white leading-tight">Invitaku</p>
        <p class="text-[10px] uppercase tracking-[0.2em] text-stone-400">Invitation Suite</p>
      </div>
    </a>

    <button class="lg:hidden p-2 rounded-lg hover:bg-white/10" onclick={onclose} aria-label="Tutup">
      <X class="w-5 h-5" />
    </button>
  </div>

  <nav class="flex-1 overflow-y-auto px-3 py-5 space-y-1">
    <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-500">
      {user?.role === 'scanner' ? 'Menu Petugas' : 'Menu Utama'}
    </p>

    {#each menu as item (item.href)}
      <a
        href={item.href}
        onclick={onclose}
        class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {isActive(
          item.href,
          item.exact
        )
          ? 'bg-[#7b2d43] text-white shadow-sm'
          : 'text-stone-300 hover:bg-white/8 hover:text-white'}"
      >
        <item.icon class="w-[18px] h-[18px] shrink-0 {isActive(item.href, item.exact) ? 'text-white' : 'text-stone-400 group-hover:text-stone-200'}" />
        <span class="truncate">{item.label}</span>
      </a>
    {/each}
  </nav>

  <div class="p-4 border-t border-white/10">
    <div class="flex items-center gap-3 px-2 py-2">
      <div class="w-10 h-10 rounded-full bg-[#7b2d43] flex items-center justify-center text-white font-semibold shrink-0">
        {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-white truncate">{user?.name ?? 'Pengguna'}</p>
        <p class="text-[11px] text-stone-400 capitalize">
          {user?.role === 'admin' ? 'Administrator' : 'Petugas Scanner'}
        </p>
      </div>
      <button
        onclick={handleLogout}
        class="p-2 rounded-lg text-stone-400 hover:text-rose-300 hover:bg-white/10 transition-colors"
        title="Logout"
        aria-label="Logout"
      >
        <LogOut class="w-5 h-5" />
      </button>
    </div>
  </div>
</aside>
