<script lang="ts">
  import { goto } from '$app/navigation';
  import { api } from '$lib/services/api';
  import { auth } from '$lib/stores/auth';
  import { toast } from '$lib/stores/toast';
  import { Eye, EyeOff, Loader2, Lock, Mail, Sparkles, ArrowRight, ShieldCheck } from '@lucide/svelte';

  let email = $state('');
  let password = $state('');
  let showPassword = $state(false);
  let loading = $state(false);
  let error = $state('');

  $effect(() => {
    if ($auth.initialized && $auth.user) {
      goto($auth.user.role === 'admin' ? '/admin' : '/admin/scanner');
    }
  });

  function fillDemo(type: 'admin' | 'scanner') {
    email = type === 'admin' ? 'admin@invitaku.com' : 'scanner@invitaku.com';
    password = type === 'admin' ? 'admin123' : 'scanner123';
    error = '';
  }

  async function handleSubmit(event: SubmitEvent) {
    event.preventDefault();
    error = '';
    loading = true;

    try {
      const res = await api.login({ email, password });
      auth.setAuth(res.data.user, res.data.token);
      toast.success(res.message ?? 'Login berhasil.', 'Selamat datang');
      goto(res.data.user.role === 'admin' ? '/admin' : '/admin/scanner');
    } catch (err: any) {
      error = err.message || 'Login gagal. Periksa email dan password Anda.';
    } finally {
      loading = false;
    }
  }
</script>

<svelte:head>
  <title>Login — Invitaku</title>
</svelte:head>

<div class="min-h-screen grid lg:grid-cols-2">
  <!-- Brand panel -->
  <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-[#221a1c] text-white p-12">
    <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-[#7b2d43]/40 blur-2xl"></div>
    <div class="absolute bottom-0 -left-24 w-80 h-80 rounded-full bg-[#d6b98c]/10 blur-2xl"></div>

    <div class="relative flex items-center gap-3">
      <div class="w-11 h-11 rounded-2xl bg-[#7b2d43] flex items-center justify-center">
        <Sparkles class="w-6 h-6" />
      </div>
      <div>
        <p class="font-playfair text-2xl font-semibold leading-tight">Invitaku</p>
        <p class="text-[10px] uppercase tracking-[0.3em] text-stone-400">Digital Invitation Suite</p>
      </div>
    </div>

    <div class="relative max-w-md">
      <p class="text-xs uppercase tracking-[0.3em] text-[#d6b98c]">Platform Undangan Terkelola</p>
      <h1 class="mt-5 font-playfair text-5xl leading-tight">
        Kelola undangan,<br />pantau kehadiran.
      </h1>
      <p class="mt-5 text-sm leading-relaxed text-stone-300">
        Admin membuat desain dan data tamu, setiap tamu menerima link undangan dengan QR Code unik,
        dan petugas memindai QR Code untuk mencatat kehadiran secara real-time.
      </p>

      <div class="mt-8 grid grid-cols-3 gap-3">
        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
          <p class="text-xl font-semibold">QR</p>
          <p class="mt-1 text-[10px] uppercase tracking-wider text-stone-400">Check-in</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
          <p class="text-xl font-semibold">WA</p>
          <p class="mt-1 text-[10px] uppercase tracking-wider text-stone-400">Kirim Undangan</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
          <p class="text-xl font-semibold">Live</p>
          <p class="mt-1 text-[10px] uppercase tracking-wider text-stone-400">Laporan</p>
        </div>
      </div>
    </div>

    <p class="relative text-xs text-stone-500">© {new Date().getFullYear()} Invitaku. Semua hak dilindungi.</p>
  </div>

  <!-- Form panel -->
  <div class="flex items-center justify-center p-6 sm:p-10 bg-[#f7f5f2]">
    <div class="w-full max-w-md">
      <div class="lg:hidden mb-8 flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-[#7b2d43] flex items-center justify-center text-white">
          <Sparkles class="w-6 h-6" />
        </div>
        <div>
          <p class="font-playfair text-2xl font-semibold text-stone-900">Invitaku</p>
          <p class="text-[10px] uppercase tracking-[0.25em] text-stone-500">Digital Invitation Suite</p>
        </div>
      </div>

      <h2 class="text-2xl sm:text-3xl font-semibold text-stone-900">Masuk ke Dashboard</h2>
      <p class="mt-2 text-sm text-stone-500">
        Gunakan akun admin atau petugas scanner yang telah diberikan.
      </p>

      {#if error}
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      {/if}

      <form class="mt-6 space-y-4" onsubmit={handleSubmit}>
        <div>
          <label class="label" for="email">Email</label>
          <div class="relative">
            <Mail class="w-4 h-4 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              id="email"
              type="email"
              class="input pl-10"
              placeholder="nama@email.com"
              autocomplete="email"
              bind:value={email}
              required
            />
          </div>
        </div>

        <div>
          <label class="label" for="password">Password</label>
          <div class="relative">
            <Lock class="w-4 h-4 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              class="input pl-10 pr-10"
              placeholder="••••••••"
              autocomplete="current-password"
              bind:value={password}
              required
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700"
              onclick={() => (showPassword = !showPassword)}
              aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
            >
              {#if showPassword}
                <EyeOff class="w-4 h-4" />
              {:else}
                <Eye class="w-4 h-4" />
              {/if}
            </button>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full py-3" disabled={loading}>
          {#if loading}
            <Loader2 class="w-4 h-4 animate-spin" /> Memproses...
          {:else}
            Masuk <ArrowRight class="w-4 h-4" />
          {/if}
        </button>
      </form>

      <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-4">
        <div class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase tracking-wider">
          <ShieldCheck class="w-4 h-4 text-emerald-600" /> Akun demo
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <button
            type="button"
            onclick={() => fillDemo('admin')}
            class="rounded-xl border border-stone-200 px-3 py-2.5 text-left hover:border-[#7b2d43]/40 hover:bg-[#7b2d43]/5 transition-colors"
          >
            <p class="text-xs font-semibold text-stone-800">Admin</p>
            <p class="text-[11px] text-stone-500 mt-0.5">admin@invitaku.com</p>
            <p class="text-[11px] text-stone-400">admin123</p>
          </button>
          <button
            type="button"
            onclick={() => fillDemo('scanner')}
            class="rounded-xl border border-stone-200 px-3 py-2.5 text-left hover:border-[#7b2d43]/40 hover:bg-[#7b2d43]/5 transition-colors"
          >
            <p class="text-xs font-semibold text-stone-800">Petugas Scanner</p>
            <p class="text-[11px] text-stone-500 mt-0.5">scanner@invitaku.com</p>
            <p class="text-[11px] text-stone-400">scanner123</p>
          </button>
        </div>
        <p class="mt-3 text-[11px] text-stone-400">Klik salah satu akun untuk mengisi form otomatis.</p>
      </div>
    </div>
  </div>
</div>
