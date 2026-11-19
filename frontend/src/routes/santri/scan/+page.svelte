<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import { readCache, writeCache } from '$lib/utils/cache';
  import type { EventModel, SantriOverview, ScanResult } from '$lib/types';
  import {
    cameraErrorMessage,
    cameraOptionsFromDevices,
    chooseCameraId,
    rememberCameraId,
    type CameraOption
  } from '$lib/utils/camera';
  import Spinner from '$lib/components/Spinner.svelte';
  import {
    ScanLine,
    Camera,
    CameraOff,
    Keyboard,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Loader2,
    User,
    CalendarDays,
    Clock,
    RefreshCw,
    ShieldCheck,
    QrCode,
    Image,
    GraduationCap,
    Phone
  } from '@lucide/svelte';
  import type { Html5Qrcode } from 'html5-qrcode';

  type ResultKind = 'idle' | 'valid' | 'already' | 'invalid' | 'wrong_event' | 'checked_in' | 'error';

  let overview = $state<SantriOverview | null>(null);
  let loading = $state(true);
  let refreshing = $state(false);
  let loadError = $state('');

  let scanner: Html5Qrcode | null = null;
  let isScanning = $state(false);
  let scannerError = $state('');
  let starting = $state(false);
  let cameras = $state<CameraOption[]>([]);
  let selectedCameraId = $state('');
  let loadingCameras = $state(false);
  const CAMERA_STORAGE_KEY = 'invitaku_santri_scanner_camera';

  let manualMode = $state(false);
  let manualCode = $state('');

  let fileScanner: Html5Qrcode | null = null;
  let photoInput = $state<HTMLInputElement>();
  let photoScanning = $state(false);
  let photoError = $state('');

  let processing = $state(false);
  let confirming = $state(false);
  let resultKind = $state<ResultKind>('idle');
  let result = $state<ScanResult | null>(null);
  let currentCode = $state('');
  let lastScannedCode = '';
  let lastScannedAt = 0;
  let autoCheckIn = $state(true);
  const DUPLICATE_SCAN_COOLDOWN_MS = 2500;
  const OVERVIEW_CACHE_KEY = 'santri_overview';
  const AUTO_CHECKIN_KEY = 'santri_auto_checkin';

  let event = $derived<EventModel | null>(overview?.event ?? null);

  async function loadData(showSpinner = true) {
    if (showSpinner) loading = true;
    else refreshing = true;
    loadError = '';

    overview = readCache<SantriOverview>(OVERVIEW_CACHE_KEY, 10 * 60 * 1000);
    if (overview) loading = false;

    try {
      const res = await api.getSantriOverview();
      overview = res.data;
      writeCache(OVERVIEW_CACHE_KEY, res.data);
    } catch (err: any) {
      if (!overview) {
        loadError = err.message || 'Gagal memuat data scanner santri.';
        toast.error(loadError);
      }
    } finally {
      loading = false;
      refreshing = false;
    }
  }

  onMount(() => {
    autoCheckIn = localStorage.getItem(AUTO_CHECKIN_KEY) !== '0';
    void loadData();
  });

  function toggleAutoCheckIn() {
    localStorage.setItem(AUTO_CHECKIN_KEY, autoCheckIn ? '1' : '0');
  }

  function applyLocalAttendance(already?: boolean) {
    if (already || !overview) return;

    const present = overview.stats.present + 1;
    const total = overview.stats.total || 0;
    overview = {
      ...overview,
      stats: {
        ...overview.stats,
        present,
        absent: Math.max(0, overview.stats.absent - 1),
        attendance_percentage: total ? Math.round((present / total) * 100) : 0
      }
    };
    writeCache(OVERVIEW_CACHE_KEY, overview, 10 * 60 * 1000);
  }

  function extractCode(text: string): string {
    const trimmed = text.trim();

    const pathMatch = trimmed.match(/\/undangan\/([^/?#\s]+)/i);
    if (pathMatch) return decodeURIComponent(pathMatch[1]);

    const queryMatch = trimmed.match(/[?&]kode=([^&#\s]+)/i);
    if (queryMatch) return decodeURIComponent(queryMatch[1]);

    return trimmed;
  }

  async function detectCameras() {
    scannerError = '';
    loadingCameras = true;

    try {
      const { Html5Qrcode } = await import('html5-qrcode');
      const devices = await Html5Qrcode.getCameras();
      cameras = cameraOptionsFromDevices(devices);
      selectedCameraId = chooseCameraId(cameras, selectedCameraId, CAMERA_STORAGE_KEY);

      if (!cameras.length) {
        scannerError =
          'Tidak ada kamera yang terdeteksi. Pastikan webcam terhubung dan tidak dinonaktifkan di pengaturan Windows.';
      }
    } catch (err) {
      scannerError = cameraErrorMessage(err);
    } finally {
      loadingCameras = false;
    }
  }

  async function changeCamera(event: Event) {
    const cameraId = (event.currentTarget as HTMLSelectElement).value;
    selectedCameraId = cameraId;
    rememberCameraId(CAMERA_STORAGE_KEY, cameraId);

    if (isScanning) {
      await stopScanner();
      await startScanner();
    }
  }

  async function stopScanner() {
    if (scanner) {
      try {
        await scanner.stop();
        scanner.clear();
      } catch {
        // Scanner may already be stopped.
      }
      scanner = null;
    }
    isScanning = false;
  }

  async function startScanner() {
    scannerError = '';
    starting = true;

    try {
      const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');
      await stopScanner();

      if (!cameras.length) {
        await detectCameras();
      }

      if (!selectedCameraId) {
        return;
      }

      scanner = new Html5Qrcode('qr-reader', {
        verbose: false,
        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        useBarCodeDetectorIfSupported: true
      });

      await scanner.start(
        selectedCameraId,
        {
          fps: 12,
          qrbox: (width, height) => {
            const size = Math.floor(Math.min(width, height) * 0.72);
            return { width: size, height: size };
          },
          aspectRatio: 1,
          videoConstraints: {
            deviceId: { exact: selectedCameraId },
            width: { ideal: 1280 },
            height: { ideal: 720 },
            frameRate: { ideal: 30, max: 30 }
          }
        },
        (decodedText) => {
          handleDecoded(decodedText);
        },
        () => {
          // Per-frame decode errors are expected while aiming the camera.
        }
      );

      isScanning = true;
    } catch (err: any) {
      try {
        scanner?.clear();
      } catch {
        // The scanner may not have started yet.
      }
      scanner = null;
      isScanning = false;
      scannerError = cameraErrorMessage(err);
    } finally {
      starting = false;
    }
  }

  async function handleDecoded(decodedText: string) {
    if (processing || confirming || resultKind !== 'idle') return;

    const code = extractCode(decodedText);
    const now = Date.now();

    // Keep the camera running, but ignore the same QR while it is still
    // in front of the lens after a result or a reset.
    if (code === lastScannedCode && now - lastScannedAt < DUPLICATE_SCAN_COOLDOWN_MS) {
      return;
    }

    lastScannedCode = code;
    lastScannedAt = now;
    await validateCode(code);
  }

  async function validateCode(code: string) {
    if (!code) return;

    processing = true;
    result = null;
    currentCode = code;

    try {
      if (autoCheckIn) {
        // Satu request untuk validasi + check-in. Ini menghemat request dan
        // kuota internet dibanding alur validasi lalu konfirmasi terpisah.
        const res = await api.checkInSantri(code);
        result = res;
        resultKind = res.already_checked_in ? 'already' : 'checked_in';
        applyLocalAttendance(res.already_checked_in);
        toast.success(res.already_checked_in ? 'Tamu sudah tercatat hadir.' : 'Kehadiran berhasil dicatat.');
        return;
      }

      const res = await api.validateSantriScan(code);
      result = res;
      resultKind = res.already_checked_in ? 'already' : 'valid';
    } catch (err: any) {
      const payload = err.payload;
      const codeValue = payload?.code;

      if (codeValue === 'wrong_event') {
        resultKind = 'wrong_event';
      } else if (codeValue === 'invalid_token') {
        resultKind = 'invalid';
      } else {
        resultKind = 'error';
      }

      result = {
        success: false,
        code: codeValue,
        message: payload?.message ?? err.message ?? 'Kode tidak valid.',
        data: payload?.data
      };
    } finally {
      processing = false;
    }
  }

  async function submitManual(event: SubmitEvent) {
    event.preventDefault();
    await validateCode(extractCode(manualCode));
  }

  async function scanPhoto() {
    if (!photoInput) return;
    const file = photoInput.files?.[0];
    photoInput.value = '';
    if (!file || processing || confirming) return;

    photoError = '';
    photoScanning = true;

    try {
      const { Html5Qrcode } = await import('html5-qrcode');
      if (!fileScanner) {
        fileScanner = new Html5Qrcode('qr-file-reader', { verbose: false });
      }
      const decodedText = await fileScanner.scanFile(file, false);
      await validateCode(extractCode(decodedText));
    } catch {
      photoError = 'QR Code tidak terbaca dari foto. Pastikan foto jelas dan QR Code terlihat penuh.';
    } finally {
      photoScanning = false;
    }
  }

  async function confirmCheckIn() {
    if (!currentCode) return;

    confirming = true;

    try {
      const res = await api.checkInSantri(currentCode);
      result = res;
      resultKind = res.already_checked_in ? 'already' : 'checked_in';
      toast.success(res.already_checked_in ? 'Tamu sudah tercatat hadir.' : 'Kehadiran berhasil dicatat.');

      // Perbarui statistik lokal agar tidak perlu fetch overview lagi.
      applyLocalAttendance(res.already_checked_in);
    } catch (err: any) {
      const payload = err.payload;
      resultKind = payload?.code === 'wrong_event' ? 'wrong_event' : 'error';
      result = {
        success: false,
        code: payload?.code,
        message: payload?.message ?? err.message ?? 'Gagal mencatat kehadiran.',
        data: payload?.data
      };
    } finally {
      confirming = false;
    }
  }

  function resetResult() {
    result = null;
    resultKind = 'idle';
    currentCode = '';
    manualCode = '';
    lastScannedAt = Date.now();
  }

  async function rescan() {
    resetResult();
    manualMode = false;

    // The camera is intentionally kept running between guests.
    if (!isScanning) {
      await startScanner();
    }
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

  onDestroy(() => {
    stopScanner();
    fileScanner?.clear();
  });
</script>

<svelte:head><title>Scanner Santri — Invitaku</title></svelte:head>

{#if loading}
  <Spinner label="Memuat scanner santri..." full />
{:else if loadError}
  <div class="rounded-3xl border border-rose-500/30 bg-rose-950/30 p-8 text-center">
    <p class="font-semibold text-rose-200">{loadError}</p>
    <button
      class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-5 py-2.5 text-sm font-semibold text-[#080808]"
      onclick={() => loadData()}
    >
      <RefreshCw class="w-4 h-4" /> Coba lagi
    </button>
  </div>
{:else}
  <div class="space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.4em] text-[#d4af37]">Petugas</p>
        <h2 class="mt-2 font-playfair text-3xl text-[#fff5d6]">Scanner kehadiran santri</h2>
        <p class="mt-2 text-sm leading-7 text-stone-500">
          Pindai QR undangan atau masukkan kode S001/A001. Kamera memerlukan localhost atau HTTPS.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-3.5 py-2.5 text-xs text-stone-400 hover:bg-white/5 disabled:opacity-50"
        onclick={() => loadData(false)}
        disabled={refreshing}
      >
        <RefreshCw class="w-4 h-4 {refreshing ? 'animate-spin' : ''}" /> Muat ulang
      </button>
    </div>

    {#if event}
      <div class="rounded-2xl border border-[#d4af37]/15 bg-[#0e0d0b] p-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-[#d4af37]/10 text-[#d4af37] flex items-center justify-center shrink-0">
              <GraduationCap class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-[#fff5d6] truncate">{event.name}</p>
              <p class="text-xs text-stone-500 truncate">
                {event.event_date?.slice(0, 10)} · {event.event_time?.slice(0, 5)} WIB · {event.location}
              </p>
            </div>
          </div>

          <div class="flex gap-5 sm:ml-auto text-sm">
            <div>
              <p class="text-[10px] uppercase tracking-wider text-stone-600">Hadir</p>
              <p class="font-semibold text-emerald-300">{overview?.stats.present ?? 0}</p>
            </div>
            <div>
              <p class="text-[10px] uppercase tracking-wider text-stone-600">Belum</p>
              <p class="font-semibold text-amber-300">{overview?.stats.absent ?? 0}</p>
            </div>
            <div>
              <p class="text-[10px] uppercase tracking-wider text-stone-600">Total</p>
              <p class="font-semibold text-stone-200">{overview?.stats.total ?? 0}</p>
            </div>
          </div>
        </div>
      </div>
    {/if}

    <div class="grid lg:grid-cols-2 gap-5">
      <!-- Camera panel -->
      <div class="rounded-2xl border border-white/5 bg-[#0e0d0b] overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <div class="flex items-center gap-2">
            <ScanLine class="w-5 h-5 text-[#d4af37]" />
            <h3 class="font-semibold text-[#fff5d6]">Kamera Scanner</h3>
          </div>
          <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] {isScanning
            ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300'
            : 'border-white/10 bg-white/5 text-stone-500'}">
            <span class="h-1.5 w-1.5 rounded-full {isScanning ? 'bg-emerald-400 animate-pulse' : 'bg-stone-600'}"></span>
            {isScanning ? 'Aktif' : 'Nonaktif'}
          </span>
        </div>

        <div class="p-4 sm:p-5">
          <div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-2xl bg-black">
            <div id="qr-reader" class="w-full [&_video]:rounded-2xl"></div>
            <div id="qr-file-reader" class="hidden"></div>

            {#if !isScanning}
              <div class="absolute inset-0 flex flex-col items-center justify-center px-6 text-center text-stone-500">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-white/10">
                  <QrCode class="w-8 h-8" />
                </div>
                <p class="text-sm font-medium text-stone-300">Kamera belum aktif</p>
                <p class="mt-1 max-w-xs text-xs leading-relaxed">
                  Tekan mulai scanner dan izinkan akses kamera. Jika memakai webcam eksternal, pilih perangkatnya pada daftar kamera di bawah.
                </p>
                <button
                  class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#d4af37] px-5 py-2.5 text-sm font-semibold text-[#080808] disabled:opacity-60"
                  onclick={startScanner}
                  disabled={starting}
                >
                  {#if starting}
                    <Loader2 class="w-4 h-4 animate-spin" /> Menyiapkan...
                  {:else}
                    <Camera class="w-4 h-4" /> Mulai Scanner
                  {/if}
                </button>
              </div>
            {/if}
          </div>

          <div class="mt-4">
            <label class="block text-xs font-medium text-stone-400" for="santri-camera">Kamera yang digunakan</label>
            <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
              <select
                id="santri-camera"
                class="min-w-0 flex-1 rounded-xl border border-white/10 bg-black/30 px-3.5 py-2.5 text-sm text-stone-100 outline-none focus:border-[#d4af37]/60 disabled:opacity-60"
                value={selectedCameraId}
                onchange={changeCamera}
                disabled={starting || loadingCameras}
              >
                {#if loadingCameras}
                  <option value="">Memuat daftar kamera...</option>
                {:else if cameras.length === 0}
                  <option value="">Kamera belum terdeteksi</option>
                {:else}
                  {#each cameras as camera (camera.id)}
                    <option value={camera.id}>{camera.label}</option>
                  {/each}
                {/if}
              </select>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5 disabled:opacity-50"
                onclick={detectCameras}
                disabled={starting || loadingCameras}
              >
                {#if loadingCameras}
                  <Loader2 class="w-4 h-4 animate-spin" />
                {:else}
                  <RefreshCw class="w-4 h-4" />
                {/if}
                Deteksi Kamera
              </button>
            </div>
            <p class="mt-1.5 text-[11px] text-stone-600">
              Pilih webcam eksternal di sini jika kamera bawaan tidak terbaca.
            </p>
          </div>

          {#if scannerError}
            <div class="mt-3 flex gap-2 rounded-xl border border-rose-500/20 bg-rose-950/30 px-4 py-3 text-sm text-rose-200">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
              <span>{scannerError}</span>
            </div>
          {/if}

          {#if photoError}
            <div class="mt-3 flex gap-2 rounded-xl border border-rose-500/20 bg-rose-950/30 px-4 py-3 text-sm text-rose-200">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
              <span>{photoError}</span>
            </div>
          {/if}

          <div class="mt-4 flex flex-wrap gap-2">
            {#if isScanning}
              <button
                class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
                onclick={stopScanner}
              >
                <CameraOff class="w-4 h-4" /> Hentikan
              </button>
            {:else}
              <button
                class="inline-flex items-center gap-2 rounded-xl bg-[#d4af37] px-4 py-2.5 text-sm font-semibold text-[#080808] disabled:opacity-60"
                onclick={startScanner}
                disabled={starting}
              >
                <Camera class="w-4 h-4" /> {starting ? 'Menyiapkan...' : 'Mulai Scanner'}
              </button>
            {/if}

            <button
              class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
              onclick={() => (manualMode = !manualMode)}
            >
              <Keyboard class="w-4 h-4" /> Input Kode Manual
            </button>

            <button
              class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5 disabled:opacity-50"
              onclick={() => photoInput?.click()}
              disabled={photoScanning || processing || confirming}
            >
              {#if photoScanning}
                <Loader2 class="w-4 h-4 animate-spin" /> Membaca foto...
              {:else}
                <Image class="w-4 h-4" /> Scan dari Foto
              {/if}
            </button>
            <input
              bind:this={photoInput}
              type="file"
              accept="image/*"
              class="hidden"
              onchange={scanPhoto}
            />

            {#if resultKind !== 'idle'}
              <button
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm text-stone-500 hover:bg-white/5"
                onclick={resetResult}
              >
                <RefreshCw class="w-4 h-4" /> Reset
              </button>
            {/if}
          </div>

          <label class="mt-3 flex items-start gap-2 text-xs leading-relaxed text-stone-500">
            <input
              type="checkbox"
              class="mt-0.5 h-4 w-4 accent-[#d4af37]"
              bind:checked={autoCheckIn}
              onchange={toggleAutoCheckIn}
            />
            <span>
              <span class="font-medium text-stone-300">Scan cepat (disarankan)</span> — langsung catat kehadiran dalam
              <span class="text-[#d4af37]">1 request</span>. Matikan jika ingin melihat data tamu dulu sebelum konfirmasi.
            </span>
          </label>

          {#if manualMode}
            <form class="mt-4 space-y-2 rounded-xl border border-white/10 bg-black/20 p-3" onsubmit={submitManual}>
              <label class="block text-xs font-medium text-stone-400" for="manual-code">Kode / URL undangan</label>
              <div class="flex flex-col sm:flex-row gap-2">
                <input
                  id="manual-code"
                  class="min-w-0 flex-1 rounded-xl border border-white/10 bg-black/30 px-3.5 py-2.5 text-sm text-stone-100 placeholder:text-stone-600 outline-none focus:border-[#d4af37]/60"
                  placeholder="Contoh: S001 atau tempel URL undangan"
                  bind:value={manualCode}
                  required
                />
                <button
                  type="submit"
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-4 py-2.5 text-sm font-semibold text-[#080808] disabled:opacity-60"
                  disabled={processing}
                >
                  {#if processing}
                    <Loader2 class="w-4 h-4 animate-spin" /> Memeriksa...
                  {:else}
                    Validasi
                  {/if}
                </button>
              </div>
              <p class="text-[11px] text-stone-600">Alternatif jika kamera tidak tersedia. URL lengkap juga dapat ditempel.</p>
            </form>
          {/if}

          <div class="mt-4 flex gap-2 rounded-xl border border-sky-500/10 bg-sky-950/20 px-3 py-2.5 text-[11px] text-sky-200/70">
            <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Check-in ganda dicegah oleh transaksi dan unique constraint di backend.</span>
          </div>
        </div>
      </div>

      <!-- Result panel -->
      <div class="flex min-h-[420px] flex-col overflow-hidden rounded-2xl border border-white/5 bg-[#0e0d0b]">
        <div class="border-b border-white/5 px-5 py-4">
          <h3 class="font-semibold text-[#fff5d6]">Hasil Scan</h3>
          <p class="mt-0.5 text-xs text-stone-500">Informasi tamu dan konfirmasi kehadiran.</p>
        </div>

        <div class="flex-1 p-4 sm:p-5">
          {#if processing}
            <div class="flex h-full min-h-[300px] flex-col items-center justify-center text-stone-500">
              <Loader2 class="w-8 h-8 animate-spin text-[#d4af37]" />
              <p class="mt-3 text-sm">Memvalidasi kode tamu...</p>
            </div>
          {:else if resultKind === 'idle'}
            <div class="flex h-full min-h-[300px] flex-col items-center justify-center px-6 text-center text-stone-500">
              <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-white/10">
                <ScanLine class="w-8 h-8" />
              </div>
              <p class="text-sm font-medium text-stone-400">Belum ada hasil scan</p>
              <p class="mt-1 max-w-xs text-xs leading-relaxed">
                Arahkan kamera ke QR Code pada undangan tamu, atau masukkan kode S001/A001.
              </p>
            </div>
          {:else if result}
            {@const guest = result.data?.guest}
            {@const attendance = result.data?.attendance}

            {#if resultKind === 'valid'}
              <div class="rounded-2xl border border-emerald-500/20 bg-emerald-950/20 p-5">
                <div class="flex items-start gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                    <CheckCircle2 class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-emerald-200">Kode Valid</p>
                    <p class="mt-0.5 text-xs text-emerald-200/70">Tamu belum tercatat hadir. Konfirmasi kehadiran di bawah.</p>
                  </div>
                </div>

                <div class="mt-4 rounded-xl border border-white/5 bg-black/20 p-4">
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/5 text-stone-400">
                      <User class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                      <p class="truncate font-semibold text-stone-100">{guest?.name ?? '-'}</p>
                      <p class="text-xs text-stone-500">
                        {guest?.santri_code ?? '-'} · {guest?.category ?? 'Tamu'}
                      </p>
                    </div>
                  </div>

                  <div class="mt-4 grid grid-cols-1 gap-2 text-xs text-stone-400">
                    <p class="flex items-center gap-2">
                      <GraduationCap class="h-3.5 w-3.5 text-stone-600" />
                      {guest?.wali_angkatan ?? '-'}
                    </p>
                    <p class="flex items-center gap-2">
                      <Phone class="h-3.5 w-3.5 text-stone-600" /> +{guest?.whatsapp_number ?? '-'}
                    </p>
                    <p class="flex items-center gap-2">
                      <CalendarDays class="h-3.5 w-3.5 text-stone-600" /> {event?.name ?? '-'}
                    </p>
                  </div>
                </div>

                <button
                  class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#d4af37] px-4 py-3 text-sm font-semibold text-[#080808] disabled:opacity-60"
                  onclick={confirmCheckIn}
                  disabled={confirming}
                >
                  {#if confirming}
                    <Loader2 class="w-4 h-4 animate-spin" /> Mencatat kehadiran...
                  {:else}
                    <CheckCircle2 class="w-4 h-4" /> Konfirmasi Kehadiran
                  {/if}
                </button>
              </div>
            {:else if resultKind === 'checked_in' || resultKind === 'already'}
              <div class="rounded-2xl border border-amber-500/20 bg-amber-950/20 p-5">
                <div class="flex items-start gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-300">
                    <AlertTriangle class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-amber-200">
                      {resultKind === 'checked_in' ? 'Kehadiran Tercatat' : 'Sudah Hadir'}
                    </p>
                    <p class="mt-0.5 text-xs text-amber-200/70">
                      {resultKind === 'checked_in'
                        ? 'Kehadiran tamu berhasil dicatat. Tidak perlu scan ulang.'
                        : 'Kode ini sudah pernah digunakan untuk check-in. Kehadiran tidak ditambahkan dua kali.'}
                    </p>
                  </div>
                </div>

                <div class="mt-4 space-y-2 rounded-xl border border-white/5 bg-black/20 p-4">
                  <p class="font-semibold text-stone-100">{guest?.name ?? '-'}</p>
                  <p class="text-xs text-stone-500">{guest?.santri_code ?? '-'} · {guest?.category ?? 'Tamu'}</p>
                  <p class="flex items-center gap-1.5 text-xs text-stone-500">
                    <Clock class="h-3.5 w-3.5" /> Check-in: {formatDateTime(attendance?.checked_in_at)}
                  </p>
                  {#if attendance?.officer?.name}
                    <p class="text-xs text-stone-500">Petugas: {attendance.officer.name}</p>
                  {/if}
                </div>

                <button
                  class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
                  onclick={rescan}
                >
                  <ScanLine class="w-4 h-4" /> Scan Tamu Berikutnya
                </button>
              </div>
            {:else if resultKind === 'wrong_event'}
              <div class="rounded-2xl border border-amber-500/20 bg-amber-950/20 p-5">
                <div class="flex items-start gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-300">
                    <AlertTriangle class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-amber-200">Undangan Tidak Sesuai Acara</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-200/70">{result.message}</p>
                  </div>
                </div>

                <button
                  class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
                  onclick={rescan}
                >
                  <ScanLine class="w-4 h-4" /> Scan Ulang
                </button>
              </div>
            {:else}
              <div class="rounded-2xl border border-rose-500/20 bg-rose-950/20 p-5">
                <div class="flex items-start gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rose-500/15 text-rose-300">
                    <XCircle class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-rose-200">Kode Tidak Valid</p>
                    <p class="mt-1 text-xs leading-relaxed text-rose-200/70">{result.message}</p>
                  </div>
                </div>

                <button
                  class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm text-stone-300 hover:bg-white/5"
                  onclick={rescan}
                >
                  <ScanLine class="w-4 h-4" /> Scan Ulang
                </button>
              </div>
            {/if}
          {/if}
        </div>
      </div>
    </div>
  </div>
{/if}
