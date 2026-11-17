export interface CameraOption {
  id: string;
  label: string;
}

export function cameraOptionsFromDevices(
  devices: Array<{ id: string; label?: string | null }>
): CameraOption[] {
  return devices.map((device, index) => ({
    id: device.id,
    label: device.label?.trim() || `Kamera ${index + 1}`
  }));
}

/**
 * Pick a camera that is likely to work on desktop as well as mobile.
 * A previously selected camera wins, then a rear/external camera label,
 * and finally the first available device.
 */
export function chooseCameraId(
  cameras: CameraOption[],
  preferredId: string,
  storageKey: string
): string {
  const savedId =
    typeof localStorage !== 'undefined' ? localStorage.getItem(storageKey) : null;

  const selectedId =
    cameras.find((camera) => camera.id === preferredId)?.id ??
    (savedId ? cameras.find((camera) => camera.id === savedId)?.id : undefined) ??
    cameras.find((camera) =>
      /back|rear|environment|usb|external|webcam/i.test(camera.label)
    )?.id ??
    cameras[0]?.id ??
    '';

  if (selectedId && typeof localStorage !== 'undefined') {
    localStorage.setItem(storageKey, selectedId);
  }

  return selectedId;
}

export function rememberCameraId(storageKey: string, cameraId: string): void {
  if (cameraId && typeof localStorage !== 'undefined') {
    localStorage.setItem(storageKey, cameraId);
  }
}

export function cameraErrorMessage(error: unknown): string {
  const err = error as { name?: string; message?: string } | null;
  const name = err?.name ?? '';
  const message = err?.message ?? '';

  if (name === 'NotAllowedError' || /notallowed|permission|denied/i.test(message)) {
    return 'Izin kamera ditolak. Klik ikon kamera di address bar browser, pilih Allow, lalu coba lagi.';
  }

  if (
    name === 'NotFoundError' ||
    /notfound|no camera|requested device not found/i.test(message)
  ) {
    return 'Tidak ada kamera yang terdeteksi. Pastikan webcam terhubung dan tidak dinonaktifkan di pengaturan Windows.';
  }

  if (
    name === 'NotReadableError' ||
    /notreadable|could not start video|in use|busy/i.test(message)
  ) {
    return 'Kamera sedang dipakai aplikasi lain. Tutup Zoom, Teams, OBS, atau aplikasi kamera lain lalu coba lagi.';
  }

  return 'Kamera tidak dapat dimulai. Pilih kamera lain pada daftar, atau gunakan input kode manual.';
}
