<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { AttendanceStats, EventModel, Guest, ScanResult } from '$lib/types';
  import {
    cameraErrorMessage,
    cameraOptionsFromDevices,
    chooseCameraId,
    rememberCameraId,
    type CameraOption
  } from '$lib/utils/camera';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
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
    Users
  } from '@lucide/svelte';
  import type { Html5Qrcode } from 'html5-qrcode';

  type ResultKind = 'idle' | 'valid' | 'already' | 'invalid' | 'wrong_event' | 'checked_in' | 'error';

  let events = $state<EventModel[]>([]);
  let selectedEventId = $state('');
  let loadingEvents = $state(true);

  let scanner: Html5Qrcode | null = null;
  let isScanning = $state(false);
  let scannerError = $state('');
  let starting = $state(false);
  let cameras = $state<CameraOption[]>([]);
  let selectedCameraId = $state('');
  let loadingCameras = $state(false);
  const CAMERA_STORAGE_KEY = 'invitaku_admin_scanner_camera';

  let manualMode = $state(false);
  let manualToken = $state('');

  let fileScanner: Html5Qrcode | null = null;
  let photoInput = $state<HTMLInputElement>();
  let photoScanning = $state(false);
  let photoError = $state('');

  let processing = $state(false);
  let confirming = $state(false);
  let resultKind = $state<ResultKind>('idle');
  let result = $state<ScanResult | null>(null);
  let currentToken = $state('');
  let lastScannedToken = '';
  let lastScannedAt = 0;
  const DUPLICATE_SCAN_COOLDOWN_MS = 2500;

  let selectedEvent = $derived(events.find((event) => String(event.id) === selectedEventId) ?? null);

  let attendanceData = $state<{
    stats: AttendanceStats;
    present: Guest[];
    absent: Guest[];
  } | null>(null);
  let loadingAttendance = $state(false);

  async function loadAttendance() {
    if (!selectedEventId) {
      attendanceData = null;
      return;
    }

    loadingAttendance = true;
    try {
      const res = await api.getAttendance(selectedEventId);
      attendanceData = res.data;
    } catch {
      attendanceData = null;
    } finally {
      loadingAttendance = false;
    }
  }

  function onEventChange() {
    resetResult();
    loadAttendance();
  }

  async function loadEvents() {
    loadingEvents = true;
    try {
      const res = await api.getScannerEvents();
      events = res.data;

      const queryEvent = page.url.searchParams.get('event');
      if (queryEvent && events.some((event) => String(event.id) === queryEvent)) {
        selectedEventId = queryEvent;
      } else {
        const today = new Date().toISOString().slice(0, 10);
        const todaysEvent = events.find((event) => event.event_date?.slice(0, 10) === today);
        selectedEventId = todaysEvent ? String(todaysEvent.id) : events[0] ? String(events[0].id) : '';
      }

      loadAttendance();
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat daftar acara.');
    } finally {
      loadingEvents = false;
    }
  }

  onMount(loadEvents);

  function extractToken(text: string): string {
    const trimmed = text.trim();
    const match = trimmed.match(/\/undangan\/([^/?#\s]+)/i);
    if (match) return decodeURIComponent(match[1]);
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
        // scanner already stopped
      }
      scanner = null;
    }
    isScanning = false;
  }

  async function startScanner() {
    if (!selectedEventId) {
      toast.warning('Pilih acara terlebih dahulu.');
      return;
    }

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
            const size = Math.floor(Math.min(width, height) * 0.7);
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
          // per-frame decode errors are expected while aiming the camera
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

    const token = extractToken(decodedText);
    const now = Date.now();

    // Keep the camera running, but ignore the same QR while it is still
    // in front of the lens after a result or a reset.
    if (token === lastScannedToken && now - lastScannedAt < DUPLICATE_SCAN_COOLDOWN_MS) {
      return;
    }

    lastScannedToken = token;
    lastScannedAt = now;
    await validateToken(token);
  }

  async function validateToken(token: string) {
    if (!token) return;

    if (!selectedEventId) {
      toast.warning('Pilih acara terlebih dahulu.');
      return;
    }

    processing = true;
    result = null;
    currentToken = token;

    try {
      const res = await api.validateScan(selectedEventId, token);
      result = res;
      resultKind = res.already_checked_in ? 'already' : 'valid';
    } catch (err: any) {
      const payload = err.payload;
      const code = payload?.code;

      if (code === 'wrong_event') {
        resultKind = 'wrong_event';
      } else if (code === 'invalid_token') {
        resultKind = 'invalid';
      } else {
        resultKind = 'error';
      }

      result = {
        success: false,
        code,
        message: payload?.message ?? err.message ?? 'QR Code tidak valid.',
        data: payload?.data
      };
    } finally {
      processing = false;
    }
  }

  async function submitManual(e: SubmitEvent) {
    e.preventDefault();
    await validateToken(extractToken(manualToken));
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
      await validateToken(extractToken(decodedText));
    } catch {
      photoError = 'QR Code tidak terbaca dari foto. Pastikan foto jelas dan QR Code terlihat penuh.';
    } finally {
      photoScanning = false;
    }
  }

  async function confirmCheckIn() {
    if (!currentToken || !selectedEventId) return;

    confirming = true;
    try {
      const res = await api.checkIn(selectedEventId, currentToken);
      result = res;
      resultKind = res.already_checked_in ? 'already' : 'checked_in';
      toast.success(res.already_checked_in ? 'Tamu sudah tercatat hadir.' : 'Kehadiran berhasil dicatat.');
      loadEvents();
      loadAttendance();
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
    currentToken = '';
    manualToken = '';
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
      month: 'long',
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

<svelte:head><title>Scanner Kehadiran — Invitaku</title></svelte:head>

{#if loadingEvents}
  <Spinner label="Memuat acara..." full />
{:else if events.length === 0}
  <div class="card">
    <EmptyState
      title="Belum ada acara"
      message="Scanner membutuhkan acara aktif. Hubungi admin untuk membuat acara terlebih dahulu."
      icon={CalendarDays}
    />
  </div>
{:else}
  <div class="space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-stone-900">Scanner Kehadiran</h2>
        <p class="text-sm text-stone-500 mt-1">
          Pilih acara, lalu pindai QR Code undangan tamu untuk mencatat kehadiran.
        </p>
      </div>

      <div class="w-full lg:w-80">
        <label class="label" for="scanner-event">Acara sedang berlangsung</label>
        <select
          id="scanner-event"
          class="select"
          bind:value={selectedEventId}
          disabled={isScanning}
          onchange={onEventChange}
        >
          {#each events as event (event.id)}
            <option value={String(event.id)}>{event.name}</option>
          {/each}
        </select>
      </div>
    </div>

    {#if selectedEvent}
      <div class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-[#7b2d43]/10 text-[#7b2d43] flex items-center justify-center shrink-0">
            <CalendarDays class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-stone-900 truncate">{selectedEvent.name}</p>
            <p class="text-xs text-stone-500 truncate">
              {selectedEvent.event_date?.slice(0, 10)} · {selectedEvent.event_time?.slice(0, 5)} WIB · {selectedEvent.location}
            </p>
          </div>
        </div>
        <div class="flex gap-5 sm:ml-auto text-sm">
          <div>
            <p class="text-xs text-stone-500">Tamu</p>
            <p class="font-semibold text-stone-900">{selectedEvent.guests_count ?? 0}</p>
          </div>
          <div>
            <p class="text-xs text-stone-500">Hadir</p>
            <p class="font-semibold text-emerald-600">{selectedEvent.attended_count ?? 0}</p>
          </div>
        </div>
      </div>
    {/if}

    <div class="grid lg:grid-cols-2 gap-5">
      <!-- Camera panel -->
      <div class="card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
          <div class="flex items-center gap-2">
            <ScanLine class="w-5 h-5 text-[#7b2d43]" />
            <h3 class="font-semibold text-stone-900">Kamera Scanner</h3>
          </div>
          <span class="badge {isScanning ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-stone-100 text-stone-500 border border-stone-200'}">
            <span class="w-1.5 h-1.5 rounded-full {isScanning ? 'bg-emerald-500 animate-pulse' : 'bg-stone-400'}"></span>
            {isScanning ? 'Aktif' : 'Nonaktif'}
          </span>
        </div>

        <div class="p-4 sm:p-5">
          <div class="relative rounded-2xl bg-[#141011] overflow-hidden min-h-[320px] flex items-center justify-center">
            <div id="qr-reader" class="w-full [&_video]:rounded-2xl"></div>
            <div id="qr-file-reader" class="hidden"></div>

            {#if !isScanning}
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 text-stone-400">
                <div class="w-16 h-16 rounded-2xl border border-white/15 flex items-center justify-center mb-4">
                  <QrCode class="w-8 h-8" />
                </div>
                <p class="text-sm font-medium text-stone-300">Kamera belum aktif</p>
                <p class="text-xs mt-1 max-w-xs leading-relaxed">
                  Tekan mulai scanner dan izinkan akses kamera. Jika memakai webcam eksternal, pilih perangkatnya pada daftar kamera di bawah.
                </p>
                <button class="btn-primary mt-5 text-sm" onclick={startScanner} disabled={starting}>
                  {#if starting}
                    <Loader2 class="w-4 h-4 animate-spin" /> Menyiapkan kamera...
                  {:else}
                    <Camera class="w-4 h-4" /> Mulai Scanner
                  {/if}
                </button>
              </div>
            {/if}
          </div>

          <div class="mt-4">
            <label class="label" for="admin-scanner-camera">Kamera yang digunakan</label>
            <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
              <select
                id="admin-scanner-camera"
                class="input min-w-0 flex-1"
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
                class="btn-secondary text-sm"
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
            <p class="mt-1.5 text-[11px] text-stone-400">
              Pilih webcam eksternal di sini jika kamera bawaan tidak terbaca.
            </p>
          </div>

          {#if scannerError}
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 flex gap-2">
              <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
              <span>{scannerError}</span>
            </div>
          {/if}

          {#if photoError}
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 flex gap-2">
              <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
              <span>{photoError}</span>
            </div>
          {/if}

          <div class="mt-4 flex flex-wrap gap-2">
            {#if isScanning}
              <button class="btn-secondary text-sm" onclick={stopScanner}>
                <CameraOff class="w-4 h-4" /> Hentikan Scanner
              </button>
            {:else}
              <button class="btn-primary text-sm" onclick={startScanner} disabled={starting}>
                <Camera class="w-4 h-4" /> {starting ? 'Menyiapkan...' : 'Mulai Scanner'}
              </button>
            {/if}

            <button class="btn-secondary text-sm" onclick={() => (manualMode = !manualMode)}>
              <Keyboard class="w-4 h-4" /> Input Token Manual
            </button>

            <button
              class="btn-secondary text-sm"
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
              <button class="btn-ghost text-sm" onclick={resetResult}>
                <RefreshCw class="w-4 h-4" /> Reset
              </button>
            {/if}
          </div>

          {#if manualMode}
            <form class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-3 space-y-2" onsubmit={submitManual}>
              <label class="label" for="manual-token">Token / URL undangan</label>
              <div class="flex flex-col sm:flex-row gap-2">
                <input
                  id="manual-token"
                  class="input"
                  placeholder="Tempel token atau URL undangan tamu"
                  bind:value={manualToken}
                  required
                />
                <button type="submit" class="btn-primary text-sm shrink-0" disabled={processing}>
                  {#if processing}
                    <Loader2 class="w-4 h-4 animate-spin" /> Memeriksa...
                  {:else}
                    Validasi
                  {/if}
                </button>
              </div>
              <p class="text-[11px] text-stone-400">
                Alternatif jika kamera tidak tersedia. URL lengkap juga dapat ditempel.
              </p>
            </form>
          {/if}

          <div class="mt-4 rounded-xl bg-sky-50 border border-sky-100 px-3 py-2.5 text-[11px] text-sky-800 flex gap-2">
            <ShieldCheck class="w-4 h-4 shrink-0 mt-0.5" />
            <span>Kamera memerlukan akses aman: <code class="font-mono">localhost</code> atau HTTPS. Check-in ganda dicegah oleh transaksi &amp; unique constraint di backend.</span>
          </div>
        </div>
      </div>

      <!-- Result panel -->
      <div class="card overflow-hidden min-h-[420px] flex flex-col">
        <div class="px-5 py-4 border-b border-stone-100">
          <h3 class="font-semibold text-stone-900">Hasil Scan</h3>
          <p class="text-xs text-stone-500 mt-0.5">Informasi tamu dan konfirmasi kehadiran.</p>
        </div>

        <div class="flex-1 p-4 sm:p-5">
          {#if processing}
            <div class="h-full min-h-[300px] flex flex-col items-center justify-center text-stone-500">
              <Loader2 class="w-8 h-8 animate-spin text-[#7b2d43]" />
              <p class="mt-3 text-sm">Memvalidasi QR Code...</p>
            </div>
          {:else if resultKind === 'idle'}
            <div class="h-full min-h-[300px] flex flex-col items-center justify-center text-center text-stone-400 px-6">
              <div class="w-16 h-16 rounded-2xl bg-stone-100 flex items-center justify-center mb-4">
                <ScanLine class="w-8 h-8" />
              </div>
              <p class="text-sm font-medium text-stone-600">Belum ada hasil scan</p>
              <p class="text-xs mt-1 max-w-xs leading-relaxed">
                Arahkan kamera ke QR Code pada undangan tamu, atau gunakan input token manual.
              </p>
            </div>
          {:else if result}
            {@const guest = result.data?.guest}
            {@const attendance = result.data?.attendance}
            {@const eventName = result.data?.event?.name ?? selectedEvent?.name}

            {#if resultKind === 'valid'}
              <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <div class="flex items-start gap-3">
                  <div class="w-11 h-11 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <CheckCircle2 class="w-6 h-6" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-800">Undangan Valid</p>
                    <p class="text-xs text-emerald-700 mt-0.5">Tamu belum tercatat hadir. Konfirmasi kehadiran di bawah.</p>
                  </div>
                </div>

                <div class="mt-4 rounded-xl bg-white p-4 space-y-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-stone-100 text-stone-600 flex items-center justify-center">
                      <User class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                      <p class="font-semibold text-stone-900 truncate">{guest?.name ?? '-'}</p>
                      <p class="text-xs text-stone-500">{guest?.category ?? 'Tamu'} · +{guest?.whatsapp_number ?? '-'}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-2 text-xs text-stone-600">
                    <p class="flex items-center gap-2"><CalendarDays class="w-3.5 h-3.5 text-stone-400" /> {eventName}</p>
                    <p class="flex items-center gap-2"><Clock class="w-3.5 h-3.5 text-stone-400" /> {selectedEvent?.event_date?.slice(0, 10)} · {selectedEvent?.event_time?.slice(0, 5)} WIB</p>
                  </div>
                </div>

                <button class="btn-primary w-full mt-4" onclick={confirmCheckIn} disabled={confirming}>
                  {#if confirming}
                    <Loader2 class="w-4 h-4 animate-spin" /> Mencatat kehadiran...
                  {:else}
                    <CheckCircle2 class="w-4 h-4" /> Konfirmasi Kehadiran
                  {/if}
                </button>
              </div>
            {:else if resultKind === 'checked_in' || resultKind === 'already'}
              <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex items-start gap-3">
                  <div class="w-11 h-11 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <AlertTriangle class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-amber-900">
                      {resultKind === 'checked_in' ? 'Kehadiran Tercatat' : 'Sudah Hadir'}
                    </p>
                    <p class="text-xs text-amber-800 mt-0.5">
                      {resultKind === 'checked_in'
                        ? 'Kehadiran tamu berhasil dicatat. Tidak perlu scan ulang.'
                        : 'QR Code ini sudah pernah digunakan untuk check-in. Kehadiran tidak ditambahkan dua kali.'}
                    </p>
                  </div>
                </div>

                <div class="mt-4 rounded-xl bg-white p-4 space-y-2">
                  <p class="font-semibold text-stone-900">{guest?.name ?? '-'}</p>
                  <p class="text-xs text-stone-600">{eventName}</p>
                  <p class="text-xs text-stone-500 flex items-center gap-1.5">
                    <Clock class="w-3.5 h-3.5" /> Check-in: {formatDateTime(attendance?.checked_in_at)}
                  </p>
                  {#if attendance?.officer?.name}
                    <p class="text-xs text-stone-500">Petugas: {attendance.officer.name}</p>
                  {/if}
                </div>

                <button class="btn-secondary w-full mt-4" onclick={rescan}>
                  <ScanLine class="w-4 h-4" /> Scan Tamu Berikutnya
                </button>
              </div>
            {:else if resultKind === 'wrong_event'}
              <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex items-start gap-3">
                  <div class="w-11 h-11 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <AlertTriangle class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-amber-900">Undangan Tidak Sesuai Acara</p>
                    <p class="text-xs text-amber-800 mt-1 leading-relaxed">{result.message}</p>
                  </div>
                </div>

                {#if result.data?.invitation_event}
                  <div class="mt-4 rounded-xl bg-white p-4 text-xs text-stone-600 space-y-1">
                    <p>Acara dipilih: <span class="font-medium">{selectedEvent?.name}</span></p>
                    <p>Acara pada undangan: <span class="font-medium">{result.data.invitation_event}</span></p>
                  </div>
                {/if}

                <button class="btn-secondary w-full mt-4" onclick={rescan}>
                  <ScanLine class="w-4 h-4" /> Scan Ulang
                </button>
              </div>
            {:else}
              <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                <div class="flex items-start gap-3">
                  <div class="w-11 h-11 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                    <XCircle class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-rose-900">QR Code Tidak Valid</p>
                    <p class="text-xs text-rose-800 mt-1 leading-relaxed">{result.message}</p>
                  </div>
                </div>

                <button class="btn-secondary w-full mt-4" onclick={rescan}>
                  <ScanLine class="w-4 h-4" /> Scan Ulang
                </button>
              </div>
            {/if}
          {/if}
        </div>
      </div>
    </div>

    <!-- Guest attendance lists -->
    <div class="grid lg:grid-cols-2 gap-5">
      <section class="card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
          <div class="flex items-center gap-2">
            <CheckCircle2 class="w-5 h-5 text-emerald-600" />
            <h3 class="font-semibold text-stone-900">Tamu Hadir</h3>
          </div>
          <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">
            {attendanceData?.present.length ?? 0}
          </span>
        </div>

        <div class="max-h-[420px] overflow-y-auto divide-y divide-stone-100">
          {#if loadingAttendance}
            <div class="flex items-center justify-center gap-2 px-5 py-10 text-sm text-stone-400">
              <Loader2 class="w-4 h-4 animate-spin" /> Memuat daftar tamu...
            </div>
          {:else if !attendanceData || attendanceData.present.length === 0}
            <p class="px-5 py-10 text-center text-sm text-stone-400">Belum ada tamu yang check-in.</p>
          {:else}
            {#each attendanceData.present as guest (guest.id)}
              <div class="flex items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-stone-800">{guest.name}</p>
                  <p class="truncate text-xs text-stone-500">
                    {guest.category ?? 'Tamu'} · +{guest.whatsapp_number}
                  </p>
                </div>
                <div class="shrink-0 text-right">
                  <p class="text-[11px] text-stone-400">{formatDateTime(guest.attendance?.checked_in_at)}</p>
                  {#if guest.attendance?.officer?.name}
                    <p class="text-[11px] text-stone-400">Petugas: {guest.attendance.officer.name}</p>
                  {/if}
                </div>
              </div>
            {/each}
          {/if}
        </div>
      </section>

      <section class="card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
          <div class="flex items-center gap-2">
            <Users class="w-5 h-5 text-amber-600" />
            <h3 class="font-semibold text-stone-900">Belum Hadir</h3>
          </div>
          <span class="badge bg-amber-50 text-amber-700 border border-amber-200">
            {attendanceData?.absent.length ?? 0}
          </span>
        </div>

        <div class="max-h-[420px] overflow-y-auto divide-y divide-stone-100">
          {#if loadingAttendance}
            <div class="flex items-center justify-center gap-2 px-5 py-10 text-sm text-stone-400">
              <Loader2 class="w-4 h-4 animate-spin" /> Memuat daftar tamu...
            </div>
          {:else if !attendanceData || attendanceData.absent.length === 0}
            <p class="px-5 py-10 text-center text-sm text-stone-400">Semua tamu sudah check-in.</p>
          {:else}
            {#each attendanceData.absent as guest (guest.id)}
              <div class="flex items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-stone-800">{guest.name}</p>
                  <p class="truncate text-xs text-stone-500">
                    {guest.category ?? 'Tamu'} · +{guest.whatsapp_number}
                  </p>
                </div>
                {#if guest.santri_code}
                  <span class="shrink-0 font-mono text-[10px] tracking-widest text-stone-400">{guest.santri_code}</span>
                {/if}
              </div>
            {/each}
          {/if}
        </div>
      </section>
    </div>
  </div>
{/if}
